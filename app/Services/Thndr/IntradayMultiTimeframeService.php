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

    /** Trade-quality gates. Set ROUND_TRIP_COST_PCT to your real broker + EGX fees. */
    private const ROUND_TRIP_COST_PCT = 0.35;
    private const MIN_NET_PROFIT_PCT = 0.8;
    /** Minimum NET reward/risk (after costs). Gross targets are TARGET1_R / TARGET2_R times the risk. */
    private const MIN_NET_RR = 1.2;
    private const TARGET1_R = 2.0;
    private const TARGET2_R = 3.0;

    /** Overextension filter (do not buy the exact top of a stretched move). */
    private const MAX_ENTRY_RSI = 75;            // entry timeframe RSI at/above this = overbought
    private const MAX_VWAP_EXTENSION_ATR = 3.0;  // price further than N x ATR above VWAP = stretched
    private const NEAR_HIGH_PCT = 0.2;           // "at the session high" = within this % of it
    private const NEAR_HIGH_RSI = 70;            // chasing rule: RSI at/above this ...
    private const NEAR_HIGH_STOCH = 90;          // ... and stochastic at/above this
    private const MIN_STOP_DISTANCE_PCT = 0.3;

    /** Reject signals when the latest 5m candle is older than this (market closed / stale data). */
    private const MAX_STALE_SECONDS = 1200;

    /**
     * false = the engine runs any time. Outside market hours it analyses the last finished
     * session and flags the result (data_mode = LAST_SESSION, stale_data = true).
     * true  = outside market hours every stock is rejected with STALE_INTRADAY_DATA.
     */
    private const ENFORCE_FRESHNESS = false;

    /**
     * EGX closing-auction bars distort volume, RVOL and ATR. Intraday candles at or after this
     * local time (app timezone) are dropped. Verify against your raw 5m timestamps.
     */
    private const SESSION_CUTOFF = '14:20';

    /** EGX local time. Fixed on purpose: app.timezone may be UTC, which breaks the session cutoff and VWAP session reset. */
    private const MARKET_TZ = 'Africa/Cairo';

    /** Entry timeframes (1m is analysed but is too noisy to be used for entries). */
    private const ENTRY_TIMEFRAMES = ['15m', '5m'];

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
            // Stocks already marked ERROR (not in marketwatch / marketwatch failed) must keep their reason.
            if (($stock['status'] ?? null) === 'ERROR') {
                continue;
            }

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
        $marketWatchRaw = $this->api->fetchMarketWatch();

        if ($marketWatchRaw === null) {
            Log::error('Intraday: Thndr marketwatch request failed - token expired or API blocked');

            return array_map(fn ($id) => [
                'asset_id' => $id,
                'symbol' => null,
                'status' => 'ERROR',
                'signal' => 'NONE',
                'reason' => 'MARKETWATCH_REQUEST_FAILED_CHECK_THNDR_TOKEN',
            ], $assetIds);
        }

        $marketWatch = $marketWatchRaw;
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
        $totalCandles = array_sum(array_map(fn ($t) => (int) ($t['candles_count'] ?? 0), $timeframes));

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
            $stock['reason'] = $totalCandles === 0
                ? 'NO_CANDLES_RETURNED_CHECK_THNDR_TOKEN_OR_API'
                : 'INSUFFICIENT_TIMEFRAME_DATA';

            return $stock;
        }

        $hierarchy = $this->buildHierarchy($timeframes);

        $lastTs = (int) ($timeframes['5m']['last_timestamp'] ?? 0);
        $isStale = $lastTs <= 0 || (time() - $lastTs) > self::MAX_STALE_SECONDS;
        $blockStale = $isStale && self::ENFORCE_FRESHNESS;

        $strategies = $this->evaluateStrategies($timeframes, $hierarchy);
        $signals = $blockStale
            ? []
            : $this->sortSignals(array_values(array_filter(
                $strategies,
                fn (array $s) => $s['signal'] === 'BUY'
            )));

        // A signal is accepted only if its trade plan passes the profit / R:R gates.
        $selected = null;
        $trade = null;
        $rejects = [];

        foreach ($signals as $sig) {
            $plan = $this->buildTradePlan($sig, $timeframes, $hierarchy);

            if ($plan['valid']) {
                $selected = $sig;
                $trade = $plan;
                break;
            }

            $rejects[] = $sig['strategy'] . ':' . $plan['reject_reason'];
        }

        $stock['stale_data'] = $isStale;
        $stock['data_mode'] = $isStale ? 'LAST_SESSION' : 'LIVE';
        $stock['last_candle_at'] = $lastTs > 0 ? gmdate('Y-m-d H:i', $lastTs).' UTC' : null;
        // WATCHLIST = a strategy fired but the trade plan failed the profit / R:R / resistance gates.
        $stock['status'] = $selected !== null ? 'QUALIFIED' : ($rejects !== [] && ! $blockStale ? 'WATCHLIST' : 'NO_SIGNAL');
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
        $stock['net_profit_pct_t1'] = $trade['net_profit_pct_t1'] ?? null;
        $stock['hierarchy'] = $hierarchy;
        $stock['strategies'] = $strategies;
        $stock['timeframes'] = $timeframes;
        $stock['data_errors'] = $errors;
        $stock['reason'] = $selected['reason']
            ?? ($blockStale
                ? 'STALE_INTRADAY_DATA'
                : ($rejects !== [] ? implode(' | ', $rejects) : 'NO_STRATEGY_CONFIRMED'));

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

        $candles = $this->api->normalizeCandles($json);

        // Drop closing-auction bars from intraday series.
        if (in_array($timeframe, ['5m', '1m'], true)) {
            $tz = self::MARKET_TZ;
            $candles = array_values(array_filter(
                $candles,
                fn (array $c): bool => Carbon::createFromTimestampUTC((int) $c['timestamp'])
                    ->setTimezone($tz)->format('H:i') < self::SESSION_CUTOFF
            ));
        }

        return $candles;
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
        $bars = match ($timeframe) {
            '15m' => 3,
            '30m' => 6,
            default => 1,
        };

        if ($bars === 1 || $candles === []) {
            return $candles;
        }

        $interval = $bars * 300;
        $now = time();
        $groups = [];

        foreach ($candles as $c) {
            $ts = (int) ($c['timestamp'] ?? $c['time'] ?? 0);

            if ($ts > 0) {
                $groups[intdiv($ts, $interval) * $interval][] = $c;
            }
        }

        ksort($groups);

        $out = [];

        foreach ($groups as $bucket => $group) {
            // Skip only the bucket that is still forming. A bucket with fewer than
            // $bars candles is valid: a missing 5m bar means no trades happened.
            if ($bucket + $interval > $now) {
                continue;
            }

            usort($group, fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

            $out[] = [
                'timestamp' => (int) $bucket,
                'open' => (float) $group[0]['open'],
                'high' => (float) max(array_column($group, 'high')),
                'low' => (float) min(array_column($group, 'low')),
                'close' => (float) end($group)['close'],
                'volume' => (float) array_sum(array_column($group, 'volume')),
            ];
        }

        return $out;
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
            'last_open' => (float) $candles[$last]['open'],
            'last_low' => (float) $candles[$last]['low'],
            'recent_low' => (float) min(array_column(array_slice($candles, -3), 'low')),
            'pullback_avg_volume' => $this->pullbackAverageVolume($candles),
            'session_high' => $this->sessionHigh($candles),
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
            $this->strategyVwapPullback($tf, $hierarchy),
        ];
    }

    /**
     * Shared higher-timeframe gate: positive hierarchy score, bias not bearish,
     * and none of 1D / 1H / 30m bearish.
     */
    private function higherContext(array $tf, array $hierarchy): bool
    {
        if (! ($hierarchy['entry_allowed'] ?? false)) {
            return false;
        }

        if (! in_array($hierarchy['bias'] ?? 'NEUTRAL', ['BULLISH', 'NEUTRAL'], true)) {
            return false;
        }

        foreach (['1D', '1H', '30m'] as $t) {
            if (($tf[$t]['trend'] ?? 'UNKNOWN') === 'BEARISH') {
                return false;
            }
        }

        return true;
    }

    private function strategyVwapMacd(array $tf, array $hierarchy): array
    {
        $nearMiss = null;

        foreach (self::ENTRY_TIMEFRAMES as $timeframe) {
            if (! isset($tf[$timeframe])) {
                continue;
            }

            $x = $tf[$timeframe];
            $priceAboveVwap = $x['last_close'] > ($x['vwap'] ?? INF);
            $macdBullish = $x['macd'] > $x['macd_signal'];
            $histPositive = $x['macd_histogram'] > 0;
            $volume = $x['volume_confirmed'];
            $trendSupport = in_array($x['trend'], ['BULLISH', 'NEUTRAL'], true);
            $higherContext = $this->higherContext($tf, $hierarchy);

            $conditions = [
                'price_above_vwap' => $priceAboveVwap,
                'macd_above_signal' => $macdBullish,
                'histogram_positive' => $histPositive,
                'volume_confirmed' => $volume,
                'timeframe_trend_support' => $trendSupport,
                'higher_timeframe_context' => $higherContext,
            ];

            $nearMiss = $this->betterNearMiss($nearMiss, $timeframe, $conditions);

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

        return $this->noSignalResult('VWAP_MACD', $nearMiss);
    }

    private function strategyEmaVwap(array $tf, array $hierarchy): array
    {
        $nearMiss = null;

        foreach (self::ENTRY_TIMEFRAMES as $timeframe) {
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
            $higherContext = $this->higherContext($tf, $hierarchy);

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

            $nearMiss = $this->betterNearMiss($nearMiss, $timeframe, $conditions);

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

        return $this->noSignalResult('EMA_VWAP', $nearMiss);
    }

    private function strategyBullishDivergence(array $tf, array $hierarchy): array
    {
        $nearMiss = null;

        foreach (self::ENTRY_TIMEFRAMES as $timeframe) {
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
            $context = $this->higherContext($tf, $hierarchy);

            $conditions = [
                'bullish_divergence_count' => $count >= self::DIVERGENCE_MIN_COUNT,
                'price_above_or_reclaim_vwap' => $priceAboveVwap,
                'macd_confirmation' => $macdConfirmed,
                'volume_confirmed' => $volume,
                'higher_timeframe_context' => $context,
            ];

            $nearMiss = $this->betterNearMiss($nearMiss, $timeframe, $conditions);

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

        return $this->noSignalResult('BULLISH_DIVERGENCE', $nearMiss);
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

    private function noSignalResult(string $strategy, ?array $near = null): array
    {
        $conditions = $near['conditions'] ?? [];

        return [
            'strategy' => $strategy,
            'signal' => 'NONE',
            'timeframe' => null,
            'strength' => 0,
            'conditions' => $conditions,
            'failed_conditions' => array_keys(array_filter($conditions, fn ($v) => ! $v)),
            'near_miss_timeframe' => $near['timeframe'] ?? null,
            'reason' => null,
        ];
    }

    /**
     * Returns a reject reason when the entry is stretched, otherwise null.
     * 1) entry timeframe RSI is overbought
     * 2) price is too far above VWAP (measured in ATRs)
     * 3) price is at the session high while RSI and stochastic are both elevated (chasing the top)
     */
    private function overextensionReason(array $x, float $entry, float $sessionHigh, float $atr): ?string
    {
        $rsi = $x['rsi'] ?? null;
        $stoch = $x['stochastic_k'] ?? null;
        $vwap = $x['vwap'] ?? null;

        if ($rsi !== null && $rsi >= self::MAX_ENTRY_RSI) {
            return 'OVEREXTENDED_RSI';
        }

        if ($vwap !== null && $atr > 0 && (($entry - $vwap) / $atr) > self::MAX_VWAP_EXTENSION_ATR) {
            return 'OVEREXTENDED_FROM_VWAP';
        }

        $atHigh = $sessionHigh > 0 && $entry >= $sessionHigh * (1 - self::NEAR_HIGH_PCT / 100);

        if ($atHigh
            && $rsi !== null && $rsi >= self::NEAR_HIGH_RSI
            && $stoch !== null && $stoch >= self::NEAR_HIGH_STOCH
        ) {
            return 'CHASING_SESSION_HIGH';
        }

        return null;
    }

    /** Keeps the timeframe whose conditions came closest to firing, for diagnostics. */
    private function betterNearMiss(?array $best, string $timeframe, array $conditions): array
    {
        $score = count(array_filter($conditions));

        if ($best === null || $score > $best['score']) {
            return ['score' => $score, 'timeframe' => $timeframe, 'conditions' => $conditions];
        }

        return $best;
    }

    /**
     * VWAP / EMA20 pullback inside an uptrend: price dips to VWAP or EMA20 on weak
     * volume, then closes back above both with a bullish candle.
     */
    private function strategyVwapPullback(array $tf, array $hierarchy): array
    {
        $nearMiss = null;

        foreach (self::ENTRY_TIMEFRAMES as $timeframe) {
            $x = $tf[$timeframe] ?? null;

            if ($x === null || $x['vwap'] === null || $x['ema_20'] === null || $x['ema_50'] === null) {
                continue;
            }

            $vwap = (float) $x['vwap'];
            $ema20 = (float) $x['ema_20'];
            $close = (float) $x['last_close'];
            $low = (float) $x['recent_low'];

            $touched = $low <= $vwap * 1.003 || $low <= $ema20 * 1.003;
            $closedAbove = $close > $vwap && $close > $ema20;
            $bullish = (bool) $x['bullish_candle'];
            $uptrend = $ema20 > (float) $x['ema_50'];
            $weakPullbackVolume = $x['average_volume'] !== null
                && (float) $x['average_volume'] > 0
                && (float) $x['pullback_avg_volume'] <= (float) $x['average_volume'];
            $m30 = $tf['30m'] ?? null;
            $m30Up = $m30 !== null
                && $m30['ema_20'] !== null
                && $m30['ema_50'] !== null
                && $m30['ema_20'] > $m30['ema_50'];
            $context = $this->higherContext($tf, $hierarchy);

            $conditions = [
                'pullback_touched_vwap_or_ema20' => $touched,
                'closed_above_vwap_and_ema20' => $closedAbove,
                'bullish_candle' => $bullish,
                'timeframe_uptrend' => $uptrend,
                'pullback_volume_weak' => $weakPullbackVolume,
                '30m_uptrend' => $m30Up,
                'higher_timeframe_context' => $context,
            ];

            $nearMiss = $this->betterNearMiss($nearMiss, $timeframe, $conditions);

            if ($touched && $closedAbove && $bullish && $uptrend && $weakPullbackVolume && $m30Up && $context) {
                return $this->signalResult(
                    'VWAP_PULLBACK',
                    $timeframe,
                    82,
                    $conditions,
                    'Pullback to VWAP/EMA20 on weak volume in an uptrend, closed back above both with a bullish candle.'
                );
            }
        }

        return $this->noSignalResult('VWAP_PULLBACK', $nearMiss);
    }

    private function sortSignals(array $signals): array
    {
        $priority = ['15m' => 3, '5m' => 2, '1m' => 0];

        usort($signals, function (array $a, array $b) use ($priority) {
            $scoreA = ($a['strength'] ?? 0) + ($priority[$a['timeframe']] ?? 0) * 2;
            $scoreB = ($b['strength'] ?? 0) + ($priority[$b['timeframe']] ?? 0) * 2;

            return $scoreB <=> $scoreA;
        });

        return $signals;
    }

    private function pullbackAverageVolume(array $candles): float
    {
        $last = count($candles) - 1;
        $slice = array_slice($candles, max(0, $last - 3), min(3, $last));

        if ($slice === []) {
            return 0.0;
        }

        return array_sum(array_map(fn ($c) => (float) $c['volume'], $slice)) / count($slice);
    }

    private function sessionHigh(array $candles): float
    {
        $tz = self::MARKET_TZ;
        $toDate = fn (array $c): string => Carbon::createFromTimestampUTC((int) $c['timestamp'])
            ->setTimezone($tz)
            ->toDateString();

        $lastDate = $toDate($candles[count($candles) - 1]);
        $high = 0.0;

        for ($i = count($candles) - 1; $i >= 0; $i--) {
            if ($toDate($candles[$i]) !== $lastDate) {
                break;
            }

            $high = max($high, (float) $candles[$i]['high']);
        }

        return $high;
    }

    private function buildTradePlan(array $signal, array $tf, array $hierarchy): array
    {
        $x = $tf[$signal['timeframe']] ?? null;

        if ($x === null) {
            return ['valid' => false, 'reject_reason' => 'NO_TIMEFRAME'];
        }

        // Use the freshest price (5m), not a possibly stale 15m bar.
        $entry = (float) ($tf['5m']['last_close'] ?? $x['last_close']);

        if ($entry <= 0) {
            return ['valid' => false, 'reject_reason' => 'INVALID_PRICE'];
        }

        // ATR floor: never below 25% of daily ATR or 0.3% of price.
        $atr = max(
            (float) ($x['atr_14'] ?? 0),
            (float) ($tf['1D']['atr_14'] ?? 0) * 0.25,
            $entry * 0.003
        );

        $stop = $entry - ($atr * 1.2);

        if (($x['vwap'] ?? null) !== null && $x['vwap'] < $entry) {
            $stop = max($stop, $x['vwap'] - ($atr * 0.25));
        }

        // Stop must be at least MIN_STOP_DISTANCE_PCT away to avoid noise stop-outs.
        $stop = min($stop, $entry * (1 - self::MIN_STOP_DISTANCE_PCT / 100));

        $risk = $entry - $stop;
        $target1 = $entry + $risk * self::TARGET1_R;
        $target2 = $entry + $risk * self::TARGET2_R;
        $cost = $entry * self::ROUND_TRIP_COST_PCT / 100;

        // Resistance rule: if the session high sits between entry and target 1, target 1 is
        // capped at the session high (the first realistic exit) and the old target 1 becomes
        // target 2. The capped plan must still pass the profit / R:R gates below.
        $sessionHigh = (float) ($x['session_high'] ?? 0);
        $target1Capped = false;

        if ($sessionHigh > $entry * 1.001 && $sessionHigh < $target1) {
            $target2 = $target1;
            $target1 = $sessionHigh;
            $target1Capped = true;
        }

        $netProfit = (($target1 - $entry) - $cost) / $entry * 100;
        $netRrT1 = (($target1 - $entry) - $cost) / ($risk + $cost);
        $netRrT2 = (($target2 - $entry) - $cost) / ($risk + $cost);

        $reject = $this->overextensionReason($x, $entry, $sessionHigh, $atr);

        if ($reject === null) {
            if ($netProfit < self::MIN_NET_PROFIT_PCT) {
                $reject = $target1Capped ? 'RESISTANCE_TOO_CLOSE' : 'PROFIT_TOO_SMALL_AFTER_COSTS';
            } elseif ($netRrT1 < self::MIN_NET_RR) {
                $reject = $target1Capped ? 'RESISTANCE_LIMITS_RISK_REWARD' : 'LOW_NET_RISK_REWARD';
            }
        }

        return [
            'valid' => $reject === null,
            'reject_reason' => $reject,
            'entry' => round($entry, 4),
            'stop_loss' => round($stop, 4),
            'target_1' => round($target1, 4),
            'target_2' => round($target2, 4),
            'risk_reward_t1' => round($netRrT1, 2),
            'risk_reward_t2' => round($netRrT2, 2),
            'net_profit_pct_t1' => round($netProfit, 2),
            'target_1_capped_at_session_high' => $target1Capped,
            'entry_timeframe' => $signal['timeframe'],
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
                ->setTimezone(self::MARKET_TZ)
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