<?php

namespace App\Services\Thndr;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Independent EGX EMA Breakout scalping scanner.
 *
 * Entry timeframes are evaluated independently (1m and 5m). Trend is accepted
 * when either the last CLOSED 15m candle OR the last CLOSED 1h candle has
 * EMA20 > EMA34 > EMA50. 15m candles are aggregated from Thndr 5m candles.
 *
 * This service only scans and builds trade plans. It does not place live orders.
 */
class EmaBreakoutScalpingService
{
    private const EMA_FAST = 20;
    private const EMA_MIDDLE = 34;
    private const EMA_SLOW = 50;
    private const VOLUME_PERIOD = 20;
    private const STOCH_K_PERIOD = 14;
    private const STOCH_K_SMOOTH = 3;
    private const STOCH_D_SMOOTH = 3;
    private const STOCH_LOWER = 20.0;
    private const STOCH_UPPER = 80.0;
    private const TARGET_1_R = 1.5;
    private const TARGET_2_R = 2.0;
    private const MARKET_TZ = 'Africa/Cairo';
    private const SESSION_CUTOFF = '14:20';

    public function __construct(private readonly ThndrApi $api)
    {
    }

    /**
     * Scan the requested Thndr asset IDs.
     *
     * @param array<int, string|int> $assetIds
     * @return array<int, array<string, mixed>>
     */
    public function analyzeAssetIds(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('strval', $assetIds))));

        if ($assetIds === []) {
            return [];
        }

        $marketWatch = $this->api->fetchMarketWatch();
        if ($marketWatch === null) {
            return array_map(fn (string $id) => $this->errorResult($id, 'MARKETWATCH_REQUEST_FAILED_CHECK_THNDR_TOKEN'), $assetIds);
        }

        $marketAssets = [];
        foreach ($marketWatch['assets'] ?? [] as $asset) {
            $id = (string) ($asset['asset_id'] ?? $asset['id'] ?? '');
            if ($id !== '' && in_array($id, $assetIds, true)) {
                $marketAssets[$id] = $asset;
            }
        }

        $results = [];
        foreach ($assetIds as $assetId) {
            if (! isset($marketAssets[$assetId])) {
                $results[] = $this->errorResult($assetId, 'ASSET_NOT_FOUND_IN_MARKETWATCH');
                continue;
            }

            try {
                $details = $this->api->fetchAsset($assetId) ?? [];
                $stock = array_merge($details, $marketAssets[$assetId], ['asset_id' => $assetId]);
                $results[] = $this->analyzeStock($stock);
            } catch (\Throwable $e) {
                Log::error('EMA Breakout scalping analysis failed', [
                    'asset_id' => $assetId,
                    'message' => $e->getMessage(),
                ]);
                $results[] = $this->errorResult($assetId, 'ANALYSIS_ERROR', $e->getMessage());
            }
        }

        usort($results, function (array $a, array $b): int {
            $rank = ['QUALIFIED' => 0, 'REJECTED' => 1, 'ERROR' => 2];
            return ($rank[$a['status'] ?? 'ERROR'] ?? 3) <=> ($rank[$b['status'] ?? 'ERROR'] ?? 3);
        });

        return $results;
    }

    private function analyzeStock(array $stock): array
    {
        $base = $this->baseResult($stock);

        if (($stock['is_tradable'] ?? true) !== true) {
            return array_merge($base, ['status' => 'REJECTED', 'reason' => 'NOT_TRADABLE']);
        }
        if (($stock['is_otc'] ?? false) === true) {
            return array_merge($base, ['status' => 'REJECTED', 'reason' => 'OTC_STOCK']);
        }
        if (($stock['is_right'] ?? false) === true) {
            return array_merge($base, ['status' => 'REJECTED', 'reason' => 'SUBSCRIPTION_RIGHT']);
        }

        $now = time();
        $hourly = $this->fetchClosedCandles($base['asset_id'], '1HR', 60 * 86400, 3600, $now, false);
        $fiveMinute = $this->fetchClosedCandles($base['asset_id'], '5MIN', 7 * 86400, 300, $now, true);
        $oneMinute = $this->fetchClosedCandles($base['asset_id'], '1MIN', 3 * 86400, 60, $now, true);
        $fifteenMinute = $this->aggregateCandles($fiveMinute, 900, 3, $now);

        $trend15 = $this->trendSnapshot($fifteenMinute);
        $trend1h = $this->trendSnapshot($hourly);
        $trendConfirmed = ($trend15['bullish'] ?? false) || ($trend1h['bullish'] ?? false);

        $timeframeResults = [
            '1m' => $this->evaluateEntryTimeframe($oneMinute, '1m', $trendConfirmed),
            '5m' => $this->evaluateEntryTimeframe($fiveMinute, '5m', $trendConfirmed),
        ];

        $qualified = array_values(array_filter($timeframeResults, fn (array $item) => ($item['status'] ?? null) === 'QUALIFIED'));
        $status = $qualified !== [] ? 'QUALIFIED' : 'REJECTED';

        $base['status'] = $status;
        $base['strategy'] = 'EMA_BREAKOUT_SCALPING';
        $base['trend'] = $trendConfirmed ? 'BULLISH' : 'NOT_BULLISH';
        $base['trend_filter'] = [
            'rule' => 'EMA20 > EMA34 > EMA50 on 15m OR 1h',
            '15m' => $trend15,
            '1h' => $trend1h,
            'confirmed' => $trendConfirmed,
        ];
        $base['entry_timeframes'] = $timeframeResults;
        $base['qualified_timeframes'] = array_column($qualified, 'timeframe');
        $base['signals'] = array_map(fn (array $item) => $item['trade'] ?? null, $qualified);
        $base['reason'] = $status === 'QUALIFIED'
            ? 'EMA_BREAKOUT_CONFIRMED'
            : ($trendConfirmed ? 'NO_ENTRY_TIMEFRAME_CONFIRMED' : 'HIGHER_TIMEFRAME_TREND_NOT_CONFIRMED');

        // Legacy-compatible fields used by the current generic Analysis result view.
        $primary = $qualified[0]['trade'] ?? null;
        if ($primary !== null) {
            $base['entry'] = $primary['entry'];
            $base['entry_price'] = $primary['entry'];
            $base['stop_loss'] = $primary['stop_loss'];
            $base['target_1'] = $primary['target_1'];
            $base['target1'] = $primary['target_1'];
            $base['target_2'] = $primary['target_2'];
            $base['target2'] = $primary['target_2'];
            $base['risk'] = $primary['risk'];
        }

        return $base;
    }

    /** Fetch, normalize, discard incomplete/current candles and optionally apply EGX session cutoff. */
    private function fetchClosedCandles(
        string $assetId,
        string $resolution,
        int $lookbackSeconds,
        int $intervalSeconds,
        int $now,
        bool $applySessionCutoff
    ): array {
        $json = $this->api->fetchCandles($assetId, $resolution, $now - $lookbackSeconds, $now);
        $candles = $this->api->normalizeCandles($json);

        $candles = array_values(array_filter($candles, function (array $candle) use ($now, $intervalSeconds, $applySessionCutoff): bool {
            $timestamp = (int) ($candle['timestamp'] ?? 0);
            if ($timestamp <= 0 || $timestamp + $intervalSeconds > $now) {
                return false;
            }
            if ($applySessionCutoff) {
                $localTime = Carbon::createFromTimestampUTC($timestamp)->setTimezone(self::MARKET_TZ)->format('H:i');
                if ($localTime >= self::SESSION_CUTOFF) {
                    return false;
                }
            }
            return true;
        }));

        return array_values($candles);
    }

    /** Build complete 15m bars from three 5m bars; ignore incomplete buckets. */
    private function aggregateCandles(array $candles, int $bucketSeconds, int $barsPerBucket, int $now): array
    {
        $groups = [];
        foreach ($candles as $candle) {
            $timestamp = (int) $candle['timestamp'];
            $bucket = intdiv($timestamp, $bucketSeconds) * $bucketSeconds;
            $groups[$bucket][] = $candle;
        }
        ksort($groups);

        $aggregated = [];
        foreach ($groups as $bucket => $group) {
            if (($bucket + $bucketSeconds) > $now || count($group) !== $barsPerBucket) {
                continue;
            }

            usort($group, fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

            // Require the exact three expected 5m candles; a count of three
            // alone must not hide missing candles or gaps in the source data.
            $expectedTimestamps = [
                (int) $bucket,
                (int) $bucket + 300,
                (int) $bucket + 600,
            ];
            $actualTimestamps = array_map(
                fn (array $candle) => (int) ($candle['timestamp'] ?? 0),
                $group
            );

            if ($actualTimestamps !== $expectedTimestamps) {
                continue;
            }

            $aggregated[] = [
                'timestamp' => (int) $bucket,
                'open' => (float) $group[0]['open'],
                'high' => (float) max(array_column($group, 'high')),
                'low' => (float) min(array_column($group, 'low')),
                'close' => (float) $group[count($group) - 1]['close'],
                'volume' => (float) array_sum(array_column($group, 'volume')),
            ];
        }
        return $aggregated;
    }

    private function trendSnapshot(array $candles): array
    {
        $count = count($candles);
        if ($count < self::EMA_SLOW + 5) {
            return ['bullish' => false, 'reason' => 'INSUFFICIENT_CANDLES', 'candles_count' => $count];
        }

        $closes = array_column($candles, 'close');
        $ema20 = $this->emaSeries($closes, self::EMA_FAST);
        $ema34 = $this->emaSeries($closes, self::EMA_MIDDLE);
        $ema50 = $this->emaSeries($closes, self::EMA_SLOW);
        $last = $count - 1;
        $bullish = $ema20[$last] > $ema34[$last] && $ema34[$last] > $ema50[$last];

        return [
            'bullish' => $bullish,
            'reason' => $bullish ? 'EMA_ALIGNMENT_BULLISH' : 'EMA_ALIGNMENT_NOT_BULLISH',
            'candles_count' => $count,
            'close' => (float) $closes[$last],
            'ema_20' => $ema20[$last],
            'ema_34' => $ema34[$last],
            'ema_50' => $ema50[$last],
            'last_candle_timestamp' => (int) $candles[$last]['timestamp'],
        ];
    }

    private function evaluateEntryTimeframe(array $candles, string $timeframe, bool $trendConfirmed): array
    {
        $base = ['timeframe' => $timeframe, 'status' => 'REJECTED', 'reason' => null, 'candles_count' => count($candles)];
        if (! $trendConfirmed) {
            $base['reason'] = 'HIGHER_TIMEFRAME_TREND_NOT_CONFIRMED';
            return $base;
        }
        if (count($candles) < max(self::EMA_MIDDLE + 5, self::VOLUME_PERIOD + 2, self::STOCH_K_PERIOD + self::STOCH_K_SMOOTH + self::STOCH_D_SMOOTH + 2)) {
            $base['reason'] = 'INSUFFICIENT_ENTRY_CANDLES';
            return $base;
        }

        $closes = array_column($candles, 'close');
        $ema34 = $this->emaSeries($closes, self::EMA_MIDDLE);
        $volumes = array_column($candles, 'volume');
        $volumeSma = $this->smaSeries($volumes, self::VOLUME_PERIOD);
        $stochastic = $this->stochasticSeries($candles);

        $last = count($candles) - 1;
        $previous = $last - 1;
        $candle = $candles[$last];
        $breakout = (float) $candles[$previous]['close'] <= $ema34[$previous]
            && (float) $candle['close'] > $ema34[$last];
        $bullishCandle = (float) $candle['close'] > (float) $candle['open'];
        $volumeConfirmed = $volumeSma[$last] !== null && (float) $candle['volume'] > $volumeSma[$last];
        $k = $stochastic['k'][$last] ?? null;
        $d = $stochastic['d'][$last] ?? null;
        $stochasticConfirmed = $k !== null && $d !== null
            && $k > self::STOCH_LOWER && $k < self::STOCH_UPPER && $k > $d;

        $checks = [
            'ema34_breakout' => $breakout,
            'bullish_candle' => $bullishCandle,
            'volume_above_sma20' => $volumeConfirmed,
            'stochastic_k_between_20_80' => $k !== null && $k > self::STOCH_LOWER && $k < self::STOCH_UPPER,
            'stochastic_k_above_d' => $k !== null && $d !== null && $k > $d,
        ];
        $failed = array_keys(array_filter($checks, fn (bool $passed) => ! $passed));

        $base['checks'] = $checks;
        $base['indicators'] = [
            'ema_34' => $ema34[$last],
            'volume' => (float) $candle['volume'],
            'volume_sma_20' => $volumeSma[$last],
            'stochastic_k' => $k,
            'stochastic_d' => $d,
        ];
        $base['signal_candle'] = [
            'timestamp' => (int) $candle['timestamp'],
            'open' => (float) $candle['open'],
            'high' => (float) $candle['high'],
            'low' => (float) $candle['low'],
            'close' => (float) $candle['close'],
            'volume' => (float) $candle['volume'],
        ];

        if ($failed !== []) {
            $base['reason'] = 'ENTRY_CONDITIONS_NOT_MET';
            $base['failed_conditions'] = $failed;
            return $base;
        }

        $entry = (float) $candle['close']; // Signal close/reference entry; not a claim of guaranteed live fill.
        $stopLoss = (float) $candle['low'];
        $risk = $entry - $stopLoss;
        if ($risk <= 0) {
            $base['reason'] = 'INVALID_STOP_LOSS_DISTANCE';
            return $base;
        }

        $base['status'] = 'QUALIFIED';
        $base['reason'] = 'EMA_BREAKOUT_CONFIRMED';
        $base['failed_conditions'] = [];
        $base['trade'] = [
            'timeframe' => $timeframe,
            'signal_timestamp' => (int) $candle['timestamp'],
            'entry' => $entry,
            'entry_execution_note' => 'Signal candle close reference. A live order submitted after candle close may fill at a different price.',
            'stop_loss' => $stopLoss,
            'risk' => $risk,
            'target_1' => $entry + ($risk * self::TARGET_1_R),
            'target_2' => $entry + ($risk * self::TARGET_2_R),
            'target_1_r' => self::TARGET_1_R,
            'target_2_r' => self::TARGET_2_R,
            'exit_plan' => ['target_1_quantity_percent' => 50, 'target_2_quantity_percent' => 50],
        ];

        return $base;
    }

    /** EMA initialized from the first available close; values are computed chronologically. */
    private function emaSeries(array $values, int $period): array
    {
        $result = [];
        if ($values === []) {
            return $result;
        }
        $alpha = 2 / ($period + 1);
        $ema = (float) $values[0];
        foreach ($values as $index => $value) {
            $ema = $index === 0 ? (float) $value : (((float) $value * $alpha) + ($ema * (1 - $alpha)));
            $result[] = $ema;
        }
        return $result;
    }

    private function smaSeries(array $values, int $period): array
    {
        $result = [];
        $sum = 0.0;
        foreach ($values as $index => $value) {
            $sum += (float) $value;
            if ($index >= $period) {
                $sum -= (float) $values[$index - $period];
            }
            $result[] = $index + 1 >= $period ? $sum / $period : null;
        }
        return $result;
    }

    /** Standard Stochastic: raw %K(14), SMA(%K,3), SMA(smoothed %K,3) for %D. */
    private function stochasticSeries(array $candles): array
    {
        $rawK = [];
        $count = count($candles);
        for ($i = 0; $i < $count; $i++) {
            if ($i + 1 < self::STOCH_K_PERIOD) {
                $rawK[] = null;
                continue;
            }
            $window = array_slice($candles, $i - self::STOCH_K_PERIOD + 1, self::STOCH_K_PERIOD);
            $highest = (float) max(array_column($window, 'high'));
            $lowest = (float) min(array_column($window, 'low'));
            $range = $highest - $lowest;
            $rawK[] = $range > 0 ? (((float) $candles[$i]['close'] - $lowest) / $range) * 100 : 0.0;
        }

        $smoothK = $this->nullableSmaSeries($rawK, self::STOCH_K_SMOOTH);
        $smoothD = $this->nullableSmaSeries($smoothK, self::STOCH_D_SMOOTH);
        return ['k' => $smoothK, 'd' => $smoothD];
    }

    private function nullableSmaSeries(array $values, int $period): array
    {
        $result = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if ($i + 1 < $period) {
                $result[] = null;
                continue;
            }
            $window = array_slice($values, $i - $period + 1, $period);
            if (in_array(null, $window, true)) {
                $result[] = null;
                continue;
            }
            $result[] = array_sum($window) / $period;
        }
        return $result;
    }

    private function baseResult(array $stock): array
    {
        return [
            'asset_id' => (string) ($stock['asset_id'] ?? $stock['id'] ?? ''),
            'symbol' => $stock['symbol'] ?? $stock['symbol_code'] ?? null,
            'symbol_code' => $stock['symbol_code'] ?? $stock['symbol'] ?? null,
            'name' => $stock['name'] ?? $stock['arabic_name'] ?? null,
            'status' => 'REJECTED',
            'strategy' => 'EMA_BREAKOUT_SCALPING',
        ];
    }

    private function errorResult(string $assetId, string $reason, ?string $error = null): array
    {
        return [
            'asset_id' => $assetId,
            'symbol' => null,
            'name' => null,
            'status' => 'ERROR',
            'signal' => 'NONE',
            'strategy' => 'EMA_BREAKOUT_SCALPING',
            'reason' => $reason,
            'error' => $error,
        ];
    }
}
