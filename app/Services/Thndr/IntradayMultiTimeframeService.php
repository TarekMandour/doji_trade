<?php

namespace App\Services\Thndr;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Independent intraday multi-timeframe engine.
 *
 * This service does NOT modify or reuse the decision flow of StockAnalysisService.
 * It starts from the selected/entry-eligible stocks and loads:
 * 1D -> 1H -> 30m -> 15m -> 5m -> 1m.
 *
 * Strategies:
 * 1) Price > Session VWAP + MACD line > Signal + positive histogram + volume confirmation.
 * 2) EMA(9) crossing above Session VWAP + price above VWAP + rising VWAP + volume confirmation.
 * 3) Regular bullish divergence on MACD Histogram + RSI + Stochastic, using the
 *    TradingView settings supplied by the user: pivot period 10, regular divergence,
 *    minimum divergences 3, maximum pivot points 10, maximum bars 100.
 *
 * The service returns diagnostics even when no BUY setup exists, so the report can
 * explain why a stock did not qualify.
 */
class IntradayMultiTimeframeService
{
    private const TIMEFRAMES = [
        '1D',
        '1H',
        '30m',
        '15m',
        '5m',
        '1m',
    ];

    /** Resolution values accepted by Thndr's advanced charts endpoint. */
    private const RESOLUTIONS = [
        '1D'  => '1D',
        '1H'  => '1HR',
        '30m' => null,
        '15m' => null,
        '5m'  => '5MIN',
        '1m'  => '1MIN',
    ];

    /** Approximate history required by each timeframe. */
    private const LOOKBACK_DAYS = [
        '1D' => 365,
        '1H' => 60,
        '30m' => 30,
        '15m' => 15,
        '5m' => 7,
        '1m' => 3,
    ];

    private const EMA_FAST = 9;
    private const EMA_TREND = 20;
    private const EMA_SLOW = 50;

    private const MACD_FAST = 12;
    private const MACD_SLOW = 26;
    private const MACD_SIGNAL = 9;

    private const RSI_PERIOD = 14;
    private const STOCH_PERIOD = 14;
    private const STOCH_SMOOTH = 3;

    /** TradingView Divergence-for-Many-Indicators settings supplied by the user. */
    private const DIVERGENCE_PIVOT_PERIOD = 10;
    private const DIVERGENCE_MIN_COUNT = 3;
    private const DIVERGENCE_MAX_PIVOTS = 10;
    private const DIVERGENCE_MAX_BARS = 100;

    private const VOLUME_PERIOD = 20;
    private const MIN_RVOL = 1.05;

    public function __construct(private readonly ThndrApi $api) {}

    /**
     * Analyze only the selected asset IDs.
     *
     * @param array<int,string> $assetIds
     * @return array<int,array<string,mixed>>
     */
    public function analyzeAssetIds(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter($assetIds)));

        if ($assetIds === []) {
            return [];
        }

        $stocks = $this->loadSelectedStocks($assetIds);

        foreach ($stocks as &$stock) {
            try {
                $stock = $this->analyzeStock($stock);
            } catch (\Throwable $e) {
                Log::error('Intraday multi-timeframe analysis failed', [
                    'asset_id' => $stock['asset_id'] ?? $stock['id'] ?? null,
                    'symbol' => $stock['symbol'] ?? null,
                    'message' => $e->getMessage(),
                ]);

                $stock['status'] = 'ERROR';
                $stock['signal'] = 'NONE';
                $stock['reason'] = 'ANALYSIS_ERROR';
                $stock['error'] = $e->getMessage();
            }
        }
        unset($stock);

        return $this->rankResults($stocks);
    }

    /**
     * Load only the requested stocks from Thndr marketwatch.
     */
    private function loadSelectedStocks(array $assetIds): array
    {
        $marketWatch = $this->api->fetchMarketWatch() ?? [];
        $marketAssets = [];

        foreach ($marketWatch['assets'] ?? [] as $asset) {
            $id = $asset['asset_id'] ?? $asset['id'] ?? null;

            if ($id !== null && in_array($id, $assetIds, true)) {
                $marketAssets[$id] = $asset;
            }
        }

        $stocks = [];

        foreach ($assetIds as $assetId) {
            if (! isset($marketAssets[$assetId])) {
                $stocks[] = [
                    'asset_id' => $assetId,
                    'symbol' => null,
                    'status' => 'ERROR',
                    'signal' => 'NONE',
                    'reason' => 'ASSET_NOT_FOUND_IN_MARKETWATCH',
                ];
                continue;
            }

            $details = $this->api->fetchAsset($assetId) ?? [];

            $stocks[] = array_merge(
                $details,
                $marketAssets[$assetId],
                ['asset_id' => $assetId]
            );
        }

        return $stocks;
    }

    private function analyzeStock(array $stock): array
    {
        if (($stock['is_tradable'] ?? true) !== true) {
            return $this->emptyAnalysis($stock, 'NOT_TRADABLE');
        }

        if (($stock['is_otc'] ?? false) === true) {
            return $this->emptyAnalysis($stock, 'OTC_STOCK');
        }

        if (($stock['is_right'] ?? false) === true) {
            return $this->emptyAnalysis($stock, 'SUBSCRIPTION_RIGHT');
        }

        $timeframes = [];
        $errors = [];
        $base5mCandles = null;

        foreach (self::TIMEFRAMES as $timeframe) {
            if ($timeframe === '30m' || $timeframe === '15m') {
                // Thndr does not expose native 15m/30m candles. Build them
                // strictly from 5m candles without changing strategy logic.
                if ($base5mCandles === null) {
                    // 30m needs the largest source lookback.
                    $base5mCandles = $this->loadCandles(
                        $stock,
                        '5m',
                        self::LOOKBACK_DAYS['30m']
                    );
                }

                $candles = $this->aggregateCandles($base5mCandles, $timeframe);
                $candles = $this->sliceCandlesToLookback(
                    $candles,
                    self::LOOKBACK_DAYS[$timeframe]
                );
            } elseif ($timeframe === '5m' && $base5mCandles !== null) {
                // Reuse the already fetched source while preserving the
                // configured 5m lookback.
                $candles = $this->sliceCandlesToLookback(
                    $base5mCandles,
                    self::LOOKBACK_DAYS['5m']
                );
            } else {
                $candles = $this->loadCandles($stock, $timeframe);

                if ($timeframe === '5m') {
                    $base5mCandles = $candles;
                }
            }

            if (count($candles) < $this->minimumCandles($timeframe)) {
                $errors[$timeframe] = 'INSUFFICIENT_CANDLES';
                $timeframes[$timeframe] = $this->emptyTimeframe($candles);
                continue;
            }

            $timeframes[$timeframe] = $this->analyzeTimeframe($candles, $timeframe);
        }

        // Incomplete timeframe data must not produce a partial analysis.
        // If any required timeframe has insufficient candles, reject the stock.
        if ($errors !== []) {
            $stock['status'] = 'REJECTED';
            $stock['signal'] = 'NONE';
            $stock['strategy'] = null;
            $stock['entry_timeframe'] = null;
            $stock['signal_strength'] = 0;
            $stock['signal_reason'] = null;
            $stock['entry'] = null;
            $stock['stop_loss'] = null;
            $stock['target_1'] = null;
            $stock['target_2'] = null;
            $stock['risk_reward_t1'] = null;
            $stock['risk_reward_t2'] = null;
            $stock['hierarchy'] = [];
            $stock['strategies'] = [];
            $stock['timeframes'] = $timeframes;
            $stock['data_errors'] = $errors;
            $stock['reason'] = 'INSUFFICIENT_TIMEFRAME_DATA';

            return $stock;
        }

        $hierarchy = $this->buildHierarchy($timeframes);
        $strategies = $this->evaluateStrategies($timeframes, $hierarchy);
        $signals = array_values(array_filter($strategies, fn (array $s) => $s['signal'] === 'BUY'));

        $selected = $this->selectBestSignal($signals);
        $trade = $selected !== null
            ? $this->buildTradePlan($selected, $timeframes, $hierarchy)
            : null;

        $stock['status'] = $selected !== null ? 'QUALIFIED' : 'NO_SIGNAL';
        $stock['signal'] = $selected['signal'] ?? 'NONE';
        $stock['strategy'] = $selected['strategy'] ?? null;
        $stock['entry_timeframe'] = $selected['timeframe'] ?? null;
        $stock['signal_strength'] = $selected['strength'] ?? 0;
        $stock['signal_reason'] = $selected['reason'] ?? null;
        $stock['entry'] = $trade['entry'] ?? null;
        $stock['stop_loss'] = $trade['stop_loss'] ?? null;
        $stock['target_1'] = $trade['target_1'] ?? null;
        $stock['target_2'] = $trade['target_2'] ?? null;
        $stock['risk_reward_t1'] = $trade['risk_reward_t1'] ?? null;
        $stock['risk_reward_t2'] = $trade['risk_reward_t2'] ?? null;
        $stock['hierarchy'] = $hierarchy;
        $stock['strategies'] = $strategies;
        $stock['timeframes'] = $timeframes;
        $stock['data_errors'] = $errors;
        $stock['reason'] = $selected['reason'] ?? ($errors === [] ? 'NO_STRATEGY_CONFIRMED' : 'MISSING_TIMEFRAME_DATA');

        return $stock;
    }

    private function loadCandles(
        array $stock,
        string $timeframe,
        ?int $lookbackDays = null
    ): array
    {
        $assetId = $stock['asset_id'] ?? $stock['id'] ?? null;

        if (! $assetId) {
            return [];
        }

        $resolution = self::RESOLUTIONS[$timeframe] ?? null;

        // 15m and 30m are derived from 5m candles and must never be
        // requested directly from Thndr.
        if ($resolution === null) {
            return [];
        }

        $end = time();
        $days = $lookbackDays ?? self::LOOKBACK_DAYS[$timeframe];
        $start = strtotime('-'.$days.' days', $end);

        $json = $this->api->fetchCandles(
            (string) $assetId,
            $resolution,
            (int) $start,
            (int) $end
        );

        return $this->api->normalizeCandles($json);
    }

    /**
     * Aggregate normalized 5m candles into 15m or 30m candles.
     *
     * Only complete and contiguous buckets are accepted:
     * - 15m = 3 x 5m candles
     * - 30m = 6 x 5m candles
     */
    private function aggregateCandles(array $candles, string $timeframe): array
    {
        $requiredBars = match ($timeframe) {
            '15m' => 3,
            '30m' => 6,
            default => 1,
        };

        if ($requiredBars === 1 || $candles === []) {
            return $candles;
        }

        $intervalSeconds = $requiredBars * 5 * 60;
        $sourceIntervalSeconds = 5 * 60;

        usort(
            $candles,
            fn (array $a, array $b): int =>
                ((int) ($a['timestamp'] ?? $a['time'] ?? 0))
                <=> ((int) ($b['timestamp'] ?? $b['time'] ?? 0))
        );

        $groups = [];

        foreach ($candles as $candle) {
            $timestamp = (int) ($candle['timestamp'] ?? $candle['time'] ?? 0);

            if ($timestamp <= 0) {
                continue;
            }

            $bucket = intdiv($timestamp, $intervalSeconds) * $intervalSeconds;
            $groups[$bucket][] = $candle;
        }

        $aggregated = [];

        foreach ($groups as $bucketTimestamp => $group) {
            if (count($group) !== $requiredBars) {
                continue;
            }

            usort(
                $group,
                fn (array $a, array $b): int =>
                    ((int) ($a['timestamp'] ?? $a['time'] ?? 0))
                    <=> ((int) ($b['timestamp'] ?? $b['time'] ?? 0))
            );

            $firstTimestamp = (int) (
                $group[0]['timestamp'] ?? $group[0]['time'] ?? 0
            );

            foreach ($group as $index => $candle) {
                $timestamp = (int) (
                    $candle['timestamp'] ?? $candle['time'] ?? 0
                );
                $expected = $firstTimestamp + ($index * $sourceIntervalSeconds);

                if ($timestamp !== $expected) {
                    continue 2;
                }
            }

            $open = (float) ($group[0]['open'] ?? 0);
            $close = (float) ($group[count($group) - 1]['close'] ?? 0);
            $high = max(array_map(
                fn (array $candle): float => (float) ($candle['high'] ?? 0),
                $group
            ));
            $low = min(array_map(
                fn (array $candle): float => (float) ($candle['low'] ?? 0),
                $group
            ));
            $volume = array_sum(array_map(
                fn (array $candle): float => (float) ($candle['volume'] ?? 0),
                $group
            ));

            $aggregated[] = [
                'timestamp' => (int) $bucketTimestamp,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
            ];
        }

        usort(
            $aggregated,
            fn (array $a, array $b): int =>
                $a['timestamp'] <=> $b['timestamp']
        );

        return $aggregated;
    }

    /**
     * Preserve the configured lookback window for a derived timeframe.
     */
    private function sliceCandlesToLookback(
        array $candles,
        int $lookbackDays
    ): array
    {
        if ($candles === []) {
            return [];
        }

        $cutoff = time() - ($lookbackDays * 86400);

        return array_values(array_filter(
            $candles,
            fn (array $candle): bool =>
                (int) ($candle['timestamp'] ?? $candle['time'] ?? 0) >= $cutoff
        ));
    }

    
private function analyzeTimeframe(array $candles, string $timeframe): array
    {
        $ema9 = $this->emaSeries($candles, self::EMA_FAST, 'close');
        $ema20 = $this->emaSeries($candles, self::EMA_TREND, 'close');
        $ema50 = $this->emaSeries($candles, self::EMA_SLOW, 'close');
        $macd = $this->macdSeries($candles);
        $rsi = $this->rsiSeries($candles, self::RSI_PERIOD);
        $stoch = $this->stochasticSeries($candles);
        $vwap = $this->sessionVwapSeries($candles);
        $rvol = $this->relativeVolume($candles);
        $structure = $this->simpleStructure($candles);
        $divergence = $this->detectBullishDivergence($candles, $macd['histogram'], $rsi, $stoch);
        $atr = $this->atr($candles, 14);

        $last = count($candles) - 1;
        $close = (float) $candles[$last]['close'];

        $trend = $this->determineTrend(
            $close,
            $ema20[$last],
            $ema50[$last],
            $ema20,
            $structure
        );

        return [
            'timeframe' => $timeframe,
            'candles_count' => count($candles),
            'last_timestamp' => $candles[$last]['timestamp'],
            'last_close' => $close,
            'trend' => $trend['trend'],
            'trend_score' => $trend['score'],
            'structure' => $structure,
            'ema_9' => $this->roundNullable($ema9[$last]),
            'ema_9_previous' => $this->roundNullable($ema9[max(0, $last - 1)]),
            'ema_20' => $this->roundNullable($ema20[$last]),
            'ema_50' => $this->roundNullable($ema50[$last]),
            'ema_20_slope' => $this->roundNullable($this->slope($ema20, 5)),
            'vwap' => $this->roundNullable($vwap[$last]),
            'vwap_previous' => $this->roundNullable($vwap[max(0, $last - 1)]),
            'vwap_rising' => $vwap[$last] > $vwap[max(0, $last - 1)],
            'macd' => $this->roundNullable($macd['macd'][$last]),
            'macd_signal' => $this->roundNullable($macd['signal'][$last]),
            'macd_histogram' => $this->roundNullable($macd['histogram'][$last]),
            'macd_previous_histogram' => $this->roundNullable($macd['histogram'][max(0, $last - 1)]),
            'rsi' => $this->roundNullable($rsi[$last]),
            'stochastic_k' => $this->roundNullable($stoch[$last]),
            'atr_14' => $this->roundNullable($atr),
            'divergence' => $divergence,
            'volume' => (float) $candles[$last]['volume'],
            'average_volume' => $this->roundNullable($rvol['average']),
            'relative_volume' => $this->roundNullable($rvol['rvol']),
            'volume_confirmed' => $rvol['rvol'] >= self::MIN_RVOL,
            'bullish_candle' => $candles[$last]['close'] > $candles[$last]['open'],
            'previous_close' => (float) $candles[max(0, $last - 1)]['close'],
        ];
    }

    private function buildHierarchy(array $timeframes): array
    {
        $weights = [
            '1D' => 3,
            '1H' => 2,
            '30m' => 1,
            '15m' => 1,
            '5m' => 1,
            '1m' => 1,
        ];

        $score = 0;
        $max = array_sum($weights);
        $details = [];

        foreach ($weights as $tf => $weight) {
            $trend = $timeframes[$tf]['trend'] ?? 'UNKNOWN';
            $value = match ($trend) {
                'BULLISH' => $weight,
                'BEARISH' => -$weight,
                default => 0,
            };

            $score += $value;
            $details[$tf] = [
                'trend' => $trend,
                'weight' => $weight,
                'contribution' => $value,
            ];
        }

        $ratio = $max > 0 ? $score / $max : 0;

        return [
            'score' => $score,
            'max_score' => $max,
            'bias' => $ratio >= 0.30 ? 'BULLISH' : ($ratio <= -0.30 ? 'BEARISH' : 'NEUTRAL'),
            'details' => $details,
            'entry_allowed' => $score > 0,
        ];
    }

    private function evaluateStrategies(array $tf, array $hierarchy): array
    {
        return [
            $this->strategyVwapMacd($tf, $hierarchy),
            $this->strategyEmaVwap($tf, $hierarchy),
            $this->strategyBullishDivergence($tf, $hierarchy),
        ];
    }

    private function strategyVwapMacd(array $tf, array $hierarchy): array
    {
        foreach (['1m', '5m', '15m'] as $timeframe) {
            if (! isset($tf[$timeframe])) {
                continue;
            }

            $x = $tf[$timeframe];
            $priceAboveVwap = $x['last_close'] > ($x['vwap'] ?? INF);
            $macdBullish = $x['macd'] > $x['macd_signal'];
            $histPositive = $x['macd_histogram'] > 0;
            $volume = $x['volume_confirmed'];
            $trendSupport = in_array($x['trend'], ['BULLISH', 'NEUTRAL'], true);
            $higherContext = in_array($hierarchy['bias'], ['BULLISH', 'NEUTRAL'], true);

            $conditions = [
                'price_above_vwap' => $priceAboveVwap,
                'macd_above_signal' => $macdBullish,
                'histogram_positive' => $histPositive,
                'volume_confirmed' => $volume,
                'timeframe_trend_support' => $trendSupport,
                'higher_timeframe_context' => $higherContext,
            ];

            if ($priceAboveVwap && $macdBullish && $histPositive && $volume && $trendSupport && $higherContext) {
                return $this->signalResult(
                    'VWAP_MACD',
                    $timeframe,
                    85,
                    $conditions,
                    'Price above Session VWAP + MACD above Signal + positive Histogram + volume confirmation.'
                );
            }
        }

        return $this->noSignalResult('VWAP_MACD');
    }

    private function strategyEmaVwap(array $tf, array $hierarchy): array
    {
        foreach (['1m', '5m', '15m'] as $timeframe) {
            if (! isset($tf[$timeframe])) {
                continue;
            }

            $x = $tf[$timeframe];
            $emaNow = $x['ema_9'];
            $vwapNow = $x['vwap'];

            if ($emaNow === null || $vwapNow === null) {
                continue;
            }

            $priceAbove = $x['last_close'] > $vwapNow;
            $emaAbove = $emaNow > $vwapNow;
            $vwapRising = $x['vwap_rising'];
            $volume = $x['volume_confirmed'];
            $trendSupport = in_array($x['trend'], ['BULLISH', 'NEUTRAL'], true);
            $higherContext = in_array($hierarchy['bias'], ['BULLISH', 'NEUTRAL'], true);

            // A real cross requires the previous EMA to be at/below the previous VWAP.
            // The previous VWAP is stored by the timeframe analysis.
            $previousEma = $x['ema_9_previous'] ?? null;
            $previousVwap = $x['vwap_previous'];
            $crossed = $previousEma !== null
                && $previousVwap !== null
                && $previousEma <= $previousVwap
                && $emaAbove;

            $conditions = [
                'ema_crossed_above_vwap' => $crossed,
                'price_above_vwap' => $priceAbove,
                'vwap_rising' => $vwapRising,
                'volume_confirmed' => $volume,
                'timeframe_trend_support' => $trendSupport,
                'higher_timeframe_context' => $higherContext,
            ];

            if ($crossed && $priceAbove && $vwapRising && $volume && $trendSupport && $higherContext) {
                return $this->signalResult(
                    'EMA_VWAP',
                    $timeframe,
                    88,
                    $conditions,
                    'EMA(9) crossed above Session VWAP + price above VWAP + rising VWAP + volume confirmation.'
                );
            }
        }

        return $this->noSignalResult('EMA_VWAP');
    }

    private function strategyBullishDivergence(array $tf, array $hierarchy): array
    {
        foreach (['15m', '5m', '1m'] as $timeframe) {
            // Divergence needs the raw candles, therefore this strategy is evaluated
            // from the compact divergence snapshot saved below.
            if (! isset($tf[$timeframe]['divergence'])) {
                continue;
            }

            $d = $tf[$timeframe]['divergence'];
            $x = $tf[$timeframe];

            $count = (int) ($d['bullish_count'] ?? 0);
            $volume = $x['volume_confirmed'];
            $vwap = $x['vwap'];
            $priceAboveVwap = $vwap !== null && $x['last_close'] >= $vwap;
            $macdConfirmed = $x['macd'] >= $x['macd_signal'];
            $context = in_array($hierarchy['bias'], ['BULLISH', 'NEUTRAL'], true);

            $conditions = [
                'bullish_divergence_count' => $count >= self::DIVERGENCE_MIN_COUNT,
                'price_above_or_reclaim_vwap' => $priceAboveVwap,
                'macd_confirmation' => $macdConfirmed,
                'volume_confirmed' => $volume,
                'higher_timeframe_context' => $context,
            ];

            if ($count >= self::DIVERGENCE_MIN_COUNT && $priceAboveVwap && $macdConfirmed && $volume && $context) {
                return $this->signalResult(
                    'BULLISH_DIVERGENCE',
                    $timeframe,
                    min(95, 70 + ($count * 5)),
                    $conditions,
                    'Regular bullish divergence confirmed by at least 3 indicators, with VWAP/MACD/volume confirmation.'
                );
            }
        }

        return $this->noSignalResult('BULLISH_DIVERGENCE');
    }

    private function signalResult(string $strategy, string $timeframe, int $strength, array $conditions, string $reason): array
    {
        return [
            'strategy' => $strategy,
            'signal' => 'BUY',
            'timeframe' => $timeframe,
            'strength' => $strength,
            'conditions' => $conditions,
            'reason' => $reason,
        ];
    }

    private function noSignalResult(string $strategy): array
    {
        return [
            'strategy' => $strategy,
            'signal' => 'NONE',
            'timeframe' => null,
            'strength' => 0,
            'conditions' => [],
            'reason' => null,
        ];
    }

    private function selectBestSignal(array $signals): ?array
    {
        if ($signals === []) {
            return null;
        }

        usort($signals, function (array $a, array $b) {
            $priority = ['1m' => 3, '5m' => 2, '15m' => 1];
            $scoreA = ($a['strength'] ?? 0) + ($priority[$a['timeframe']] ?? 0) * 2;
            $scoreB = ($b['strength'] ?? 0) + ($priority[$b['timeframe']] ?? 0) * 2;

            return $scoreB <=> $scoreA;
        });

        return $signals[0];
    }

    private function buildTradePlan(array $signal, array $tf, array $hierarchy): array
    {
        $entryTf = $signal['timeframe'];
        $x = $tf[$entryTf] ?? null;

        if ($x === null) {
            return [];
        }

        $entry = (float) $x['last_close'];
        $atr = (float) ($x['atr_14'] ?? 0);
        if ($atr <= 0) {
            $atr = $this->estimateAtrFromTimeframe($entryTf, $tf);
        }

        // Conservative initial intraday risk model. These are intentionally isolated
        // so we can replace them with a support/resistance engine after the first live test.
        $stop = $entry - ($atr * 1.0);
        $target1 = $entry + ($atr * 1.5);
        $target2 = $entry + ($atr * 2.5);

        if ($x['vwap'] !== null && $x['vwap'] < $entry) {
            $stop = max($stop, $x['vwap'] - ($atr * 0.25));
        }

        return [
            'entry' => round($entry, 4),
            'stop_loss' => round($stop, 4),
            'target_1' => round($target1, 4),
            'target_2' => round($target2, 4),
            'risk_reward_t1' => $entry > $stop ? round(($target1 - $entry) / ($entry - $stop), 2) : null,
            'risk_reward_t2' => $entry > $stop ? round(($target2 - $entry) / ($entry - $stop), 2) : null,
            'entry_timeframe' => $entryTf,
            'hierarchy_bias' => $hierarchy['bias'],
        ];
    }

    /**
     * Adds the divergence snapshot to a timeframe without exposing all indicator arrays.
     */
    private function withDivergence(array $analysis, array $candles): array
    {
        return $analysis;
    }

    private function simpleStructure(array $candles): array
    {
        $count = count($candles);
        $window = 3;

        if ($count < ($window * 2) + 5) {
            return [
                'higher_highs' => 0,
                'higher_lows' => 0,
                'lower_highs' => 0,
                'lower_lows' => 0,
            ];
        }

        $highs = [];
        $lows = [];

        for ($i = $window; $i < $count - $window; $i++) {
            $high = true;
            $low = true;

            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j === $i) {
                    continue;
                }

                if ($candles[$j]['high'] >= $candles[$i]['high']) {
                    $high = false;
                }

                if ($candles[$j]['low'] <= $candles[$i]['low']) {
                    $low = false;
                }
            }

            if ($high) {
                $highs[] = (float) $candles[$i]['high'];
            }

            if ($low) {
                $lows[] = (float) $candles[$i]['low'];
            }
        }

        $highs = array_slice($highs, -6);
        $lows = array_slice($lows, -6);

        $hh = $lh = $hl = $ll = 0;

        for ($i = 1; $i < count($highs); $i++) {
            $highs[$i] > $highs[$i - 1] ? $hh++ : $lh++;
        }

        for ($i = 1; $i < count($lows); $i++) {
            $lows[$i] > $lows[$i - 1] ? $hl++ : $ll++;
        }

        return [
            'higher_highs' => $hh,
            'higher_lows' => $hl,
            'lower_highs' => $lh,
            'lower_lows' => $ll,
        ];
    }

    private function determineTrend(
        float $close,
        ?float $ema20,
        ?float $ema50,
        array $ema20Series,
        array $structure
    ): array {
        $score = 0;

        if ($ema20 !== null && $close > $ema20) {
            $score += 2;
        } elseif ($ema20 !== null) {
            $score -= 2;
        }

        if ($ema20 !== null && $ema50 !== null) {
            $score += $ema20 > $ema50 ? 2 : -2;
        }

        $slope = $this->slope($ema20Series, 5);
        if ($slope > 0) {
            $score++;
        } elseif ($slope < 0) {
            $score--;
        }

        $score += min(2, $structure['higher_highs'] ?? 0);
        $score += min(2, $structure['higher_lows'] ?? 0);
        $score -= min(2, $structure['lower_highs'] ?? 0);
        $score -= min(2, $structure['lower_lows'] ?? 0);

        return [
            'score' => $score,
            'trend' => $score >= 3 ? 'BULLISH' : ($score <= -3 ? 'BEARISH' : 'NEUTRAL'),
        ];
    }

    private function emaSeries(array $candles, int $period, string $field): array
    {
        $values = array_map(fn ($c) => (float) $c[$field], $candles);
        $result = array_fill(0, count($values), null);

        if (count($values) < $period) {
            return $result;
        }

        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $seed;
        $multiplier = 2 / ($period + 1);

        for ($i = $period; $i < count($values); $i++) {
            $result[$i] = (($values[$i] - $result[$i - 1]) * $multiplier) + $result[$i - 1];
        }

        return $result;
    }

    private function macdSeries(array $candles): array
    {
        $fast = $this->emaSeries($candles, self::MACD_FAST, 'close');
        $slow = $this->emaSeries($candles, self::MACD_SLOW, 'close');
        $macd = array_fill(0, count($candles), null);

        for ($i = 0; $i < count($candles); $i++) {
            if ($fast[$i] !== null && $slow[$i] !== null) {
                $macd[$i] = $fast[$i] - $slow[$i];
            }
        }

        $signal = $this->emaSeriesFromNullable($macd, self::MACD_SIGNAL);
        $histogram = array_fill(0, count($candles), null);

        for ($i = 0; $i < count($candles); $i++) {
            if ($macd[$i] !== null && $signal[$i] !== null) {
                $histogram[$i] = $macd[$i] - $signal[$i];
            }
        }

        return [
            'macd' => $macd,
            'signal' => $signal,
            'histogram' => $histogram,
        ];
    }

    private function emaSeriesFromNullable(array $values, int $period): array
    {
        $result = array_fill(0, count($values), null);
        $valid = [];

        foreach ($values as $i => $value) {
            if ($value !== null) {
                $valid[] = ['index' => $i, 'value' => (float) $value];
            }
        }

        if (count($valid) < $period) {
            return $result;
        }

        $seedValues = array_slice($valid, 0, $period);
        $seed = array_sum(array_column($seedValues, 'value')) / $period;
        $seedIndex = $seedValues[$period - 1]['index'];
        $result[$seedIndex] = $seed;
        $multiplier = 2 / ($period + 1);
        $previous = $seed;

        for ($i = $period; $i < count($valid); $i++) {
            $value = $valid[$i]['value'];
            $current = (($value - $previous) * $multiplier) + $previous;
            $result[$valid[$i]['index']] = $current;
            $previous = $current;
        }

        return $result;
    }

    private function rsiSeries(array $candles, int $period): array
    {
        $closes = array_map(fn ($c) => (float) $c['close'], $candles);
        $result = array_fill(0, count($closes), null);

        if (count($closes) <= $period) {
            return $result;
        }

        $gain = 0.0;
        $loss = 0.0;

        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gain += max(0, $change);
            $loss += max(0, -$change);
        }

        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        $result[$period] = $avgLoss == 0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));

        for ($i = $period + 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $currentGain = max(0, $change);
            $currentLoss = max(0, -$change);
            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;
            $result[$i] = $avgLoss == 0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }

        return $result;
    }

    private function stochasticSeries(array $candles): array
    {
        $result = array_fill(0, count($candles), null);

        for ($i = self::STOCH_PERIOD - 1; $i < count($candles); $i++) {
            $window = array_slice($candles, $i - self::STOCH_PERIOD + 1, self::STOCH_PERIOD);
            $highest = max(array_column($window, 'high'));
            $lowest = min(array_column($window, 'low'));
            $raw = $highest == $lowest
                ? 50.0
                : (((float) $candles[$i]['close'] - $lowest) / ($highest - $lowest)) * 100;

            $rawValues = [];
            for ($j = max(self::STOCH_PERIOD - 1, $i - self::STOCH_SMOOTH + 1); $j <= $i; $j++) {
                $w = array_slice($candles, $j - self::STOCH_PERIOD + 1, self::STOCH_PERIOD);
                $h = max(array_column($w, 'high'));
                $l = min(array_column($w, 'low'));
                $rawValues[] = $h == $l ? 50.0 : (((float) $candles[$j]['close'] - $l) / ($h - $l)) * 100;
            }

            $result[$i] = array_sum($rawValues) / count($rawValues);
        }

        return $result;
    }

    private function sessionVwapSeries(array $candles): array
    {
        $result = array_fill(0, count($candles), null);
        $cumPV = 0.0;
        $cumVolume = 0.0;
        $session = null;

        foreach ($candles as $i => $candle) {
            $date = Carbon::createFromTimestampUTC((int) $candle['timestamp'])
                ->setTimezone(config('app.timezone', 'Africa/Cairo'))
                ->toDateString();

            if ($date !== $session) {
                $session = $date;
                $cumPV = 0.0;
                $cumVolume = 0.0;
            }

            $typical = ((float) $candle['high'] + (float) $candle['low'] + (float) $candle['close']) / 3;
            $volume = max(0.0, (float) $candle['volume']);
            $cumPV += $typical * $volume;
            $cumVolume += $volume;

            $result[$i] = $cumVolume > 0 ? $cumPV / $cumVolume : $typical;
        }

        return $result;
    }

    private function relativeVolume(array $candles): array
    {
        $last = count($candles) - 1;
        if ($last < self::VOLUME_PERIOD) {
            return ['average' => 0.0, 'rvol' => 0.0];
        }

        $previous = array_slice($candles, max(0, $last - self::VOLUME_PERIOD), self::VOLUME_PERIOD);
        $average = array_sum(array_map(fn ($c) => (float) $c['volume'], $previous)) / count($previous);
        $current = (float) $candles[$last]['volume'];

        return [
            'average' => $average,
            'rvol' => $average > 0 ? $current / $average : 0.0,
        ];
    }

    private function detectBullishDivergence(array $candles, array $macdHistogram, array $rsi, array $stoch): array
    {
        $last = count($candles) - 1;
        $period = self::DIVERGENCE_PIVOT_PERIOD;

        if ($last < ($period * 2) + 5) {
            return [
                'bullish' => false,
                'bullish_count' => 0,
                'indicators' => [],
                'pivot_period' => $period,
                'minimum_divergences' => self::DIVERGENCE_MIN_COUNT,
            ];
        }

        $pivots = [];
        $start = max($period, $last - self::DIVERGENCE_MAX_BARS);
        $end = $last - $period; // only confirmed pivots; no look-ahead

        for ($i = $start; $i <= $end; $i++) {
            $isLow = true;

            for ($j = $i - $period; $j <= $i + $period; $j++) {
                if ($j === $i) {
                    continue;
                }

                if ((float) $candles[$j]['low'] <= (float) $candles[$i]['low']) {
                    $isLow = false;
                    break;
                }
            }

            if ($isLow) {
                $pivots[] = $i;
            }
        }

        $pivots = array_slice($pivots, -self::DIVERGENCE_MAX_PIVOTS);

        if (count($pivots) < 2) {
            return [
                'bullish' => false,
                'bullish_count' => 0,
                'indicators' => [],
                'pivot_period' => $period,
                'minimum_divergences' => self::DIVERGENCE_MIN_COUNT,
                'pivot_count' => count($pivots),
            ];
        }

        $currentPivot = $pivots[count($pivots) - 1];
        $previousPivot = $pivots[count($pivots) - 2];
        $priceCurrent = (float) $candles[$currentPivot]['low'];
        $pricePrevious = (float) $candles[$previousPivot]['low'];

        $checks = [
            'MACD_HIST' => [$macdHistogram, $previousPivot, $currentPivot],
            'RSI' => [$rsi, $previousPivot, $currentPivot],
            'STOCH' => [$stoch, $previousPivot, $currentPivot],
        ];

        $indicators = [];
        $count = 0;

        foreach ($checks as $name => [$series, $previousIndex, $currentIndex]) {
            $previousValue = $series[$previousIndex] ?? null;
            $currentValue = $series[$currentIndex] ?? null;

            $bullish = $priceCurrent < $pricePrevious
                && $previousValue !== null
                && $currentValue !== null
                && (float) $currentValue > (float) $previousValue;

            $indicators[$name] = [
                'bullish' => $bullish,
                'previous_value' => $previousValue !== null ? round((float) $previousValue, 6) : null,
                'current_value' => $currentValue !== null ? round((float) $currentValue, 6) : null,
                'previous_price' => $pricePrevious,
                'current_price' => $priceCurrent,
                'previous_pivot_index' => $previousIndex,
                'current_pivot_index' => $currentIndex,
            ];

            if ($bullish) {
                $count++;
            }
        }

        $latestPivotAge = $last - $currentPivot;

        return [
            'bullish' => $count >= self::DIVERGENCE_MIN_COUNT && $latestPivotAge <= self::DIVERGENCE_MAX_BARS,
            'bullish_count' => $count,
            'indicators' => $indicators,
            'price_lower_low' => $priceCurrent < $pricePrevious,
            'pivot_period' => $period,
            'minimum_divergences' => self::DIVERGENCE_MIN_COUNT,
            'maximum_pivot_points' => self::DIVERGENCE_MAX_PIVOTS,
            'maximum_bars' => self::DIVERGENCE_MAX_BARS,
            'latest_pivot_age' => $latestPivotAge,
        ];
    }

    private function atr(array $candles, int $period): float
    {
        $count = count($candles);
        if ($count <= $period) {
            return 0.0;
        }

        $trs = [];
        for ($i = 1; $i < $count; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $previousClose = (float) $candles[$i - 1]['close'];
            $trs[] = max($high - $low, abs($high - $previousClose), abs($low - $previousClose));
        }

        if (count($trs) < $period) {
            return 0.0;
        }

        $atr = array_sum(array_slice($trs, 0, $period)) / $period;

        for ($i = $period; $i < count($trs); $i++) {
            $atr = (($atr * ($period - 1)) + $trs[$i]) / $period;
        }

        return $atr;
    }

    private function estimateAtrFromTimeframe(string $timeframe, array $tf): float
    {
        $x = $tf[$timeframe] ?? [];
        $close = (float) ($x['last_close'] ?? 0);

        // Percentage-based fallback keeps the first version independent from the old
        // daily ATR engine. We will replace this with true intraday ATR once the first
        // live report establishes the desired risk behavior.
        return max($close * 0.005, 0.0001);
    }

    private function approximatePreviousEma(array $tf, string $timeframe): ?float
    {
        // The current compact timeframe result intentionally keeps only the latest EMA.
        // For a reliable crossover we reconstruct the previous value from the saved slope.
        // This is conservative: if it cannot be reconstructed, no crossover is reported.
        $x = $tf[$timeframe] ?? [];
        if (! isset($x['ema_9'], $x['vwap'])) {
            return null;
        }

        return $x['ema_9_previous'] ?? null;
    }

    private function slope(array $series, int $bars): ?float
    {
        $last = count($series) - 1;
        $from = $last - $bars;

        if ($from < 0 || $series[$last] === null || $series[$from] === null) {
            return null;
        }

        return $series[$last] - $series[$from];
    }

    private function minimumCandles(string $timeframe): int
    { 
        return match ($timeframe) {
            '1D' => 60,
            '1H' => 80,
            '30m' => 80,
            '15m' => 80,
            '5m' => 100,
            '1m' => 150,
            default => 50,
        };
    }

    private function emptyTimeframe(array $candles): array
    {
        return [
            'timeframe' => null,
            'candles_count' => count($candles),
            'last_timestamp' => $candles !== [] ? end($candles)['timestamp'] : null,
            'last_close' => $candles !== [] ? (float) end($candles)['close'] : null,
            'previous_close' => $candles !== [] ? (float) $candles[max(0, count($candles) - 2)]['close'] : null,
            'trend' => 'UNKNOWN',
            'trend_score' => 0,
            'structure' => [
                'higher_highs' => 0,
                'higher_lows' => 0,
                'lower_highs' => 0,
                'lower_lows' => 0,
            ],
            'ema_9' => null,
            'ema_9_previous' => null,
            'ema_20' => null,
            'ema_50' => null,
            'ema_20_slope' => null,
            'vwap' => null,
            'vwap_previous' => null,
            'vwap_rising' => false,
            'macd' => null,
            'macd_signal' => null,
            'macd_histogram' => null,
            'macd_previous_histogram' => null,
            'rsi' => null,
            'stochastic_k' => null,
            'atr_14' => null,
            'divergence' => [
                'bullish' => false,
                'bullish_count' => 0,
                'indicators' => [],
            ],
            'volume' => $candles !== [] ? (float) end($candles)['volume'] : 0.0,
            'average_volume' => null,
            'relative_volume' => null,
            'volume_confirmed' => false,
            'bullish_candle' => false,
        ];
    }

    private function emptyAnalysis(array $stock, string $reason): array
    {
        $stock['status'] = 'REJECTED';
        $stock['signal'] = 'NONE';
        $stock['reason'] = $reason;
        $stock['strategies'] = [];
        $stock['timeframes'] = [];
        $stock['hierarchy'] = [];

        return $stock;
    }

    private function rankResults(array $stocks): array
    {
        usort($stocks, function (array $a, array $b) {
            $signalA = ($a['signal'] ?? 'NONE') === 'BUY' ? 1 : 0;
            $signalB = ($b['signal'] ?? 'NONE') === 'BUY' ? 1 : 0;

            if ($signalA !== $signalB) {
                return $signalB <=> $signalA;
            }

            return ($b['signal_strength'] ?? 0) <=> ($a['signal_strength'] ?? 0);
        });

        return $stocks;
    }

    private function roundNullable(mixed $value, int $precision = 6): ?float
    {
        return $value === null ? null : round((float) $value, $precision);
    }
}
