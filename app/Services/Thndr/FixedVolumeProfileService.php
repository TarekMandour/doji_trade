<?php

namespace App\Services\Thndr;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Independent estimated Fixed Volume Profile strategy.
 *
 * The profile is estimated from 1-minute OHLCV candles because Thndr does not
 * provide trade-level volume-at-price data. Do not interpret it as an exchange
 * native volume profile.
 */
class FixedVolumeProfileService
{
    private const MARKET_TZ = 'Africa/Cairo';
    private const SESSION_OPEN = '10:00';
    private const PROFILE_MINUTES = 30;
    private const VALUE_AREA_PERCENT = 0.70;
    private const PRICE_BIN_SIZE = 0.01;
    private const MIN_PROFILE_CANDLES = 30;
    private const MIN_RELATIVE_VOLUME = 1.20;
    private const MAX_RETEST_CANDLES = 3;
    private const TARGET1_R = 1.5;
    private const TARGET2_R = 2.0;

    public function __construct(private readonly ThndrApi $api) {}

    /**
     * @param array<int,string> $assetIds
     * @return array<int,array<string,mixed>>
     */
    public function analyzeAssetIds(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter($assetIds)));

        if ($assetIds === []) {
            return [];
        }

        try {
            $marketWatch = $this->api->fetchMarketWatch();
        } catch (\Throwable $e) {
            Log::error('Fixed Volume Profile marketwatch failed', ['message' => $e->getMessage()]);
            $marketWatch = null;
        }

        if (! is_array($marketWatch)) {
            return array_map(fn ($assetId) => $this->emptyResult(
                ['asset_id' => $assetId],
                'ERROR',
                'MARKETWATCH_REQUEST_FAILED_CHECK_THNDR_TOKEN_OR_API'
            ), $assetIds);
        }

        $assets = [];
        foreach ($marketWatch['assets'] ?? [] as $asset) {
            $id = (string) ($asset['asset_id'] ?? $asset['id'] ?? '');
            if ($id !== '' && in_array($id, $assetIds, true)) {
                $assets[$id] = $asset;
            }
        }

        $results = [];
        foreach ($assetIds as $assetId) {
            $asset = $assets[$assetId] ?? null;

            if (! is_array($asset)) {
                $results[] = $this->emptyResult(
                    ['asset_id' => $assetId],
                    'ERROR',
                    'ASSET_NOT_FOUND_IN_MARKETWATCH'
                );
                continue;
            }

            $stock = array_merge($asset, ['asset_id' => $assetId]);
            if (($stock['is_tradable'] ?? true) !== true) {
                $results[] = $this->emptyResult($stock, 'REJECTED', 'NOT_TRADABLE');
                continue;
            }
            if (($stock['is_otc'] ?? false) === true) {
                $results[] = $this->emptyResult($stock, 'REJECTED', 'OTC_STOCK');
                continue;
            }
            if (($stock['is_right'] ?? false) === true) {
                $results[] = $this->emptyResult($stock, 'REJECTED', 'SUBSCRIPTION_RIGHT');
                continue;
            }

            try {
                $results[] = $this->analyzeStock($stock);
            } catch (\Throwable $e) {
                Log::error('Fixed Volume Profile analysis failed', [
                    'asset_id' => $assetId,
                    'symbol' => $stock['symbol'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                $results[] = $this->emptyResult($stock, 'ERROR', 'ANALYSIS_ERROR', $e->getMessage());
            }
        }

        return $results;
    }

    private function analyzeStock(array $stock): array
    {
        $end = time();
        $start = Carbon::now(self::MARKET_TZ)->subDays(7)->startOfDay()->timestamp;

        $oneMinuteRaw = $this->api->fetchCandles((string) $stock['asset_id'], '1MIN', $start, $end);
        $fiveMinuteRaw = $this->api->fetchCandles((string) $stock['asset_id'], '5MIN', $start, $end);

        $oneMinute = $this->api->normalizeCandles($oneMinuteRaw);
        $fiveMinute = $this->api->normalizeCandles($fiveMinuteRaw);

        if ($oneMinute === [] || $fiveMinute === []) {
            return $this->emptyResult($stock, 'REJECTED', 'NO_CANDLES_RETURNED_CHECK_THNDR_TOKEN_OR_API');
        }

        // Analyse the latest session for which the API returned 1-minute candles.
        $sessionDate = $this->latestSessionDate($oneMinute);
        if ($sessionDate === null) {
            return $this->emptyResult($stock, 'REJECTED', 'NO_VALID_SESSION_CANDLES');
        }

        $open = Carbon::parse($sessionDate.' '.self::SESSION_OPEN, self::MARKET_TZ);
        $profileEnd = $open->copy()->addMinutes(self::PROFILE_MINUTES);
        $now = Carbon::now(self::MARKET_TZ);

        $sessionOneMinute = array_values(array_filter($oneMinute, function (array $candle) use ($sessionDate, $open, $profileEnd): bool {
            $time = Carbon::createFromTimestamp((int) $candle['timestamp'], self::MARKET_TZ);
            return $time->toDateString() === $sessionDate
                && $time->greaterThanOrEqualTo($open)
                && $time->lessThan($profileEnd);
        }));

        if ($now->lessThan($profileEnd) && $now->toDateString() === $sessionDate) {
            return $this->emptyResult($stock, 'NO_SIGNAL', 'OPENING_RANGE_NOT_COMPLETE', null, [
                'session_date' => $sessionDate,
                'profile_candles_count' => count($sessionOneMinute),
            ]);
        }

        $uniqueProfileTimestamps = count(array_unique(array_map(fn (array $candle) => (int) $candle['timestamp'], $sessionOneMinute)));
        if (count($sessionOneMinute) < self::MIN_PROFILE_CANDLES || $uniqueProfileTimestamps < self::MIN_PROFILE_CANDLES) {
            return $this->emptyResult($stock, 'REJECTED', 'INSUFFICIENT_OPENING_CANDLES', null, [
                'session_date' => $sessionDate,
                'profile_candles_count' => count($sessionOneMinute),
            ]);
        }

        $profile = $this->buildProfile($sessionOneMinute);
        if ($profile === null) {
            return $this->emptyResult($stock, 'REJECTED', 'INVALID_VOLUME_PROFILE_DATA', null, [
                'session_date' => $sessionDate,
                'profile_candles_count' => count($sessionOneMinute),
            ]);
        }

        $sessionFiveMinute = array_values(array_filter($fiveMinute, function (array $candle) use ($sessionDate, $open, $now): bool {
            $time = Carbon::createFromTimestamp((int) $candle['timestamp'], self::MARKET_TZ);
            return $time->toDateString() === $sessionDate
                && $time->greaterThanOrEqualTo($open)
                && $time->copy()->addMinutes(5)->lessThanOrEqualTo($now);
        }));

        usort($sessionFiveMinute, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        $base = [
            'session_date' => $sessionDate,
            'profile_candles_count' => count($sessionOneMinute),
            'volume_profile' => [
                'poc' => $profile['poc'],
                'vah' => $profile['vah'],
                'val' => $profile['val'],
                'method' => 'estimated_ohlcv',
                'value_area_percent' => self::VALUE_AREA_PERCENT * 100,
                'price_bin_size' => self::PRICE_BIN_SIZE,
                'limitations' => ['ESTIMATED_FROM_1MIN_OHLCV_NOT_TRUE_VOLUME_AT_PRICE'],
            ],
            'poc' => $profile['poc'],
            'vah' => $profile['vah'],
            'val' => $profile['val'],
        ];

        $signal = $this->findSetup($sessionFiveMinute, $profile, $profileEnd->timestamp);
        if ($signal === null) {
            $reason = count($sessionFiveMinute) < 4
                ? 'INSUFFICIENT_POST_PROFILE_5MIN_CANDLES'
                : 'NO_CONFIRMED_BREAKOUT_RETEST';

            return $this->emptyResult($stock, 'NO_SIGNAL', $reason, null, array_merge($base, [
                'timeframes' => ['1m' => ['candles_count' => count($sessionOneMinute)], '5m' => ['candles_count' => count($sessionFiveMinute)]],
            ]));
        }

        return array_merge($this->emptyResult($stock, 'QUALIFIED', $signal['reason']), $base, [
            'status' => 'QUALIFIED',
            'signal' => 'BUY',
            'strategy' => 'FIXED_VOLUME_PROFILE_BREAKOUT_RETEST',
            'entry_timeframe' => '5MIN',
            'signal_strength' => $signal['signal_strength'],
            'signal_reason' => $signal['reason'],
            'entry' => $signal['entry'],
            'stop_loss' => $signal['stop_loss'],
            'target_1' => $signal['target_1'],
            'target_2' => $signal['target_2'],
            'relative_volume' => $signal['relative_volume'],
            'vwap' => $signal['vwap'],
            'breakout_at' => $signal['breakout_at'],
            'entry_at' => $signal['entry_at'],
            'timeframes' => [
                '1m' => ['candles_count' => count($sessionOneMinute)],
                '5m' => ['candles_count' => count($sessionFiveMinute)],
            ],
            'reason' => 'FIXED_VOLUME_PROFILE_BREAKOUT_RETEST_CONFIRMED',
        ]);
    }

    private function latestSessionDate(array $candles): ?string
    {
        $dates = [];
        foreach ($candles as $candle) {
            $timestamp = (int) ($candle['timestamp'] ?? 0);
            if ($timestamp <= 0) {
                continue;
            }
            $local = Carbon::createFromTimestamp($timestamp, self::MARKET_TZ);
            if ($local->format('H:i') >= self::SESSION_OPEN) {
                $dates[] = $local->toDateString();
            }
        }

        if ($dates === []) {
            return null;
        }

        rsort($dates);
        return $dates[0];
    }

    /**
     * Estimate volume-at-price by distributing each minute candle's volume
     * evenly over every price bin intersected by its low/high range.
     */
    private function buildProfile(array $candles): ?array
    {
        $volumeByBin = [];

        foreach ($candles as $candle) {
            $low = (float) ($candle['low'] ?? 0);
            $high = (float) ($candle['high'] ?? 0);
            $volume = (float) ($candle['volume'] ?? 0);

            if ($low <= 0 || $high < $low || $volume <= 0) {
                continue;
            }

            $first = (int) floor($low / self::PRICE_BIN_SIZE);
            $last = (int) floor($high / self::PRICE_BIN_SIZE);
            $count = max(1, $last - $first + 1);
            $perBinVolume = $volume / $count;

            for ($bin = $first; $bin <= $last; $bin++) {
                $volumeByBin[$bin] = ($volumeByBin[$bin] ?? 0) + $perBinVolume;
            }
        }

        if ($volumeByBin === []) {
            return null;
        }

        ksort($volumeByBin, SORT_NUMERIC);
        $bins = array_keys($volumeByBin);
        $pocBin = (int) array_search(max($volumeByBin), $volumeByBin, true);
        $targetVolume = array_sum($volumeByBin) * self::VALUE_AREA_PERCENT;
        $accumulated = (float) $volumeByBin[$pocBin];
        $lowIndex = array_search($pocBin, $bins, true);
        $highIndex = $lowIndex;

        while ($accumulated < $targetVolume && ($lowIndex > 0 || $highIndex < count($bins) - 1)) {
            $belowIndex = $lowIndex > 0 ? $lowIndex - 1 : null;
            $aboveIndex = $highIndex < count($bins) - 1 ? $highIndex + 1 : null;
            $belowVolume = $belowIndex !== null ? (float) $volumeByBin[$bins[$belowIndex]] : -1;
            $aboveVolume = $aboveIndex !== null ? (float) $volumeByBin[$bins[$aboveIndex]] : -1;

            if ($aboveVolume >= $belowVolume && $aboveIndex !== null) {
                $highIndex = $aboveIndex;
                $accumulated += $aboveVolume;
            } elseif ($belowIndex !== null) {
                $lowIndex = $belowIndex;
                $accumulated += $belowVolume;
            } else {
                break;
            }
        }

        return [
            'poc' => round(($pocBin + 0.5) * self::PRICE_BIN_SIZE, 4),
            'vah' => round(($bins[$highIndex] + 1) * self::PRICE_BIN_SIZE, 4),
            'val' => round($bins[$lowIndex] * self::PRICE_BIN_SIZE, 4),
        ];
    }

    private function findSetup(array $candles, array $profile, int $profileEndTimestamp): ?array
    {
        if (count($candles) < 4) {
            return null;
        }

        $vwapByIndex = [];
        $sumPriceVolume = 0.0;
        $sumVolume = 0.0;
        foreach ($candles as $index => $candle) {
            $volume = max(0.0, (float) $candle['volume']);
            $typicalPrice = ((float) $candle['high'] + (float) $candle['low'] + (float) $candle['close']) / 3;
            $sumPriceVolume += $typicalPrice * $volume;
            $sumVolume += $volume;
            $vwapByIndex[$index] = $sumVolume > 0 ? $sumPriceVolume / $sumVolume : $typicalPrice;
        }

        $setups = [];
        for ($breakoutIndex = 1; $breakoutIndex < count($candles) - 1; $breakoutIndex++) {
            $breakout = $candles[$breakoutIndex];
            // The profile is frozen at the end of the opening range; never signal on candles used to build it.
            if ((int) $breakout['timestamp'] < $profileEndTimestamp) {
                continue;
            }
            $buffer = max(self::PRICE_BIN_SIZE, $profile['vah'] * 0.001);
            $rvol = $this->relativeVolume($candles, $breakoutIndex);

            if ((float) $breakout['close'] <= $profile['vah'] + $buffer
                || (float) $breakout['close'] <= $vwapByIndex[$breakoutIndex]
                || $rvol < self::MIN_RELATIVE_VOLUME) {
                continue;
            }

            $lastRetestIndex = min(count($candles) - 1, $breakoutIndex + self::MAX_RETEST_CANDLES);
            for ($retestIndex = $breakoutIndex + 1; $retestIndex <= $lastRetestIndex; $retestIndex++) {
                $retest = $candles[$retestIndex];
                $touchTolerance = max(self::PRICE_BIN_SIZE, $profile['vah'] * 0.002);

                $touchesLevel = (float) $retest['low'] <= $profile['vah'] + $touchTolerance
                    && (float) $retest['low'] >= $profile['vah'] - $touchTolerance;
                $holdsLevel = (float) $retest['close'] >= $profile['vah'];
                $bullish = (float) $retest['close'] > (float) $retest['open'];
                $aboveVwap = (float) $retest['close'] > $vwapByIndex[$retestIndex];

                if (! ($touchesLevel && $holdsLevel && $bullish && $aboveVwap)) {
                    continue;
                }

                $entry = (float) $retest['close'];
                $riskFloor = min((float) $retest['low'], $profile['vah']);

                // Use a volatility-aware stop buffer rather than a fixed one-piastre buffer.
                $recentRanges = [];
                for ($rangeIndex = max(0, $retestIndex - 5); $rangeIndex < $retestIndex; $rangeIndex++) {
                    $range = (float) $candles[$rangeIndex]['high'] - (float) $candles[$rangeIndex]['low'];
                    if ($range > 0) {
                        $recentRanges[] = $range;
                    }
                }
                sort($recentRanges);
                $medianRange = $recentRanges !== []
                    ? $recentRanges[(int) floor((count($recentRanges) - 1) / 2)]
                    : self::PRICE_BIN_SIZE;
                $stopBuffer = max(self::PRICE_BIN_SIZE, $medianRange * 0.20);
                $stopLoss = round($riskFloor - $stopBuffer, 4);
                $risk = $entry - $stopLoss;

                if ($risk <= 0) {
                    continue;
                }

                $targets = $this->calculateTargets($candles, $retestIndex, $entry, $risk);
                if ($targets === null) {
                    // No realistic resistance target with an acceptable reward/risk ratio.
                    continue;
                }

                $setups[] = [
                    'entry' => round($entry, 4),
                    'stop_loss' => $stopLoss,
                    'target_1' => $targets['target_1'],
                    'target_2' => $targets['target_2'],
                    'target_1_method' => $targets['target_1_method'],
                    'target_2_method' => $targets['target_2_method'],
                    'risk_reward_1' => $targets['risk_reward_1'],
                    'risk_reward_2' => $targets['risk_reward_2'],
                    'relative_volume' => round($rvol, 2),
                    'vwap' => round($vwapByIndex[$retestIndex], 4),
                    'breakout_at' => Carbon::createFromTimestamp((int) $breakout['timestamp'], self::MARKET_TZ)->format('Y-m-d H:i'),
                    'entry_at' => Carbon::createFromTimestamp((int) $retest['timestamp'], self::MARKET_TZ)->format('Y-m-d H:i'),
                    'signal_strength' => (int) min(100, round(60 + max(0, $rvol - self::MIN_RELATIVE_VOLUME) * 20)),
                    'reason' => 'CLOSE_ABOVE_VAH_VWAP_VOLUME_AND_RETEST_CONFIRMED',
                    '_timestamp' => (int) $retest['timestamp'],
                ];
                break;
            }
        }

        if ($setups === []) {
            return null;
        }

        usort($setups, fn ($a, $b) => $b['_timestamp'] <=> $a['_timestamp']);
        $setup = $setups[0];
        unset($setup['_timestamp']);

        return $setup;
    }

    /**
     * Pick realistic targets using resistance levels from completed candles.
     * R-multiples are minimum reward/risk filters and fallback targets only
     * when no resistance exists before the corresponding R level.
     */
    private function calculateTargets(array $candles, int $entryIndex, float $entry, float $risk): ?array
    {
        $minimumTarget1 = $entry + ($risk * self::TARGET1_R);
        $minimumTarget2 = $entry + ($risk * self::TARGET2_R);
        $resistances = [];

        // Pivot highs are only taken from candles available before the entry.
        for ($i = 2; $i < $entryIndex - 1; $i++) {
            $high = (float) ($candles[$i]['high'] ?? 0);
            if ($high <= $entry) {
                continue;
            }

            $isPivotHigh = $high >= (float) ($candles[$i - 1]['high'] ?? 0)
                && $high >= (float) ($candles[$i - 2]['high'] ?? 0)
                && $high > (float) ($candles[$i + 1]['high'] ?? 0);

            if ($isPivotHigh) {
                $resistances[] = $high;
            }
        }

        // The highest prior candle high is also a relevant local resistance.
        for ($i = 0; $i < $entryIndex; $i++) {
            $high = (float) ($candles[$i]['high'] ?? 0);
            if ($high > $entry) {
                $resistances[] = $high;
            }
        }

        $resistances = array_values(array_unique(array_map(
            fn ($price) => round((float) $price, 4),
            $resistances
        )));
        sort($resistances, SORT_NUMERIC);

        // A resistance too close to entry is not a valid first target.
        $target1 = null;
        foreach ($resistances as $resistance) {
            if ($resistance >= $minimumTarget1) {
                $target1 = $resistance;
                break;
            }
        }

        if ($target1 === null) {
            // Do not force a theoretical target through a known resistance.
            $blockingResistance = $resistances[0] ?? null;
            if ($blockingResistance !== null && $blockingResistance < $minimumTarget1) {
                return null;
            }

            $target1 = round($minimumTarget1, 4);
            $target1Method = 'R_MULTIPLE_NO_RESISTANCE_BEFORE_TARGET';
        } else {
            $target1Method = 'RESISTANCE';
        }

        $target2 = null;
        foreach ($resistances as $resistance) {
            if ($resistance > $target1 && $resistance >= $minimumTarget2) {
                $target2 = $resistance;
                break;
            }
        }

        if ($target2 !== null) {
            $target2Method = 'NEXT_RESISTANCE';
        } else {
            $blockingTarget2 = null;
            foreach ($resistances as $resistance) {
                if ($resistance > $target1 && $resistance < $minimumTarget2) {
                    $blockingTarget2 = $resistance;
                    break;
                }
            }

            if ($blockingTarget2 !== null) {
                return null;
            }

            $target2 = round(max($minimumTarget2, $target1 + self::PRICE_BIN_SIZE), 4);
            $target2Method = 'R_MULTIPLE_NO_RESISTANCE_BEFORE_TARGET';
        }

        $rr1 = round(($target1 - $entry) / $risk, 2);
        $rr2 = round(($target2 - $entry) / $risk, 2);

        if ($target1 <= $entry || $target2 <= $target1 || $rr1 < self::TARGET1_R || $rr2 < self::TARGET2_R) {
            return null;
        }

        return [
            'target_1' => round($target1, 4),
            'target_2' => round($target2, 4),
            'target_1_method' => $target1Method,
            'target_2_method' => $target2Method,
            'risk_reward_1' => $rr1,
            'risk_reward_2' => $rr2,
        ];
    }

    private function relativeVolume(array $candles, int $index): float
    {
        $start = max(0, $index - 20);
        $volumes = [];
        for ($i = $start; $i < $index; $i++) {
            $volume = (float) ($candles[$i]['volume'] ?? 0);
            if ($volume > 0) {
                $volumes[] = $volume;
            }
        }

        if (count($volumes) < 3) {
            return 0.0;
        }

        return (float) $candles[$index]['volume'] / (array_sum($volumes) / count($volumes));
    }

    private function emptyResult(array $stock, string $status, string $reason, ?string $error = null, array $extra = []): array
    {
        return array_merge([
            'asset_id' => $stock['asset_id'] ?? $stock['id'] ?? null,
            'symbol' => $stock['symbol'] ?? $stock['ticker'] ?? $stock['symbol_code'] ?? null,
            'name' => $stock['name'] ?? $stock['asset_name'] ?? null,
            'status' => $status,
            'signal' => 'NONE',
            'strategy' => null,
            'entry_timeframe' => '5MIN',
            'signal_strength' => 0,
            'signal_reason' => null,
            'entry' => null,
            'stop_loss' => null,
            'target_1' => null,
            'target_2' => null,
            'reason' => $reason,
            'data_errors' => $error !== null ? [$error] : [],
            'timeframes' => [],
        ], $extra);
    }
}
