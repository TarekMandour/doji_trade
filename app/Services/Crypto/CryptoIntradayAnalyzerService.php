<?php

namespace App\Services\Crypto;

/**
 * Binance Spot port of the VWAP_MACD strategy in IntradayMultiTimeframeService.
 * Binance klines are normalized here into the same OHLCV shape used by the EGX engine.
 */
class CryptoIntradayAnalyzerService
{
    private const MIN_RVOL = 1.05;
    private const VOLUME_PERIOD = 20;
    private const MACD_FAST = 12;
    private const MACD_SLOW = 26;
    private const MACD_SIGNAL = 9;
    private const EMA_TREND = 20;
    private const EMA_SLOW = 50;
    private const ROUND_TRIP_COST_PCT = 0.35;
    private const MIN_NET_PROFIT_PCT = 0.8;
    private const MIN_NET_RR = 1.2;

    private const TIMEFRAMES = [
        '1d' => ['minimum' => 60, 'weight' => 3],
        '1h' => ['minimum' => 80, 'weight' => 2],
        '30m' => ['minimum' => 80, 'weight' => 1],
        '15m' => ['minimum' => 80, 'weight' => 1],
        '5m' => ['minimum' => 100, 'weight' => 1],
        '1m' => ['minimum' => 150, 'weight' => 1],
    ];

    public function analyze(array $candlesByInterval): array
    {
        $nowMs = (int) floor(microtime(true) * 1000);
        $timeframes = [];
        $dataErrors = [];

        foreach (self::TIMEFRAMES as $interval => $config) {
            $raw = array_values($candlesByInterval[$interval] ?? []);
            $closed = array_values(array_filter($raw, static function ($candle) use ($nowMs) {
                return isset($candle[0], $candle[6]) && (int) $candle[6] < $nowMs;
            }));

            if (count($closed) < $config['minimum']) {
                $dataErrors[$interval] = [
                    'required' => $config['minimum'],
                    'received_closed' => count($closed),
                ];
                continue;
            }

            $normalized = array_map(static fn ($c) => [
                'timestamp' => (int) floor((int) $c[0] / 1000),
                'open' => (float) $c[1],
                'high' => (float) $c[2],
                'low' => (float) $c[3],
                'close' => (float) $c[4],
                'volume' => (float) $c[5],
            ], $closed);

            $timeframes[$interval] = $this->analyzeTimeframe($normalized, $interval);
        }

        if ($dataErrors !== []) {
            $missing = implode(', ', array_keys($dataErrors));
            return [
                'state' => 'rejected',
                'signal_type' => null,
                'reason' => "Insufficient closed candle history: {$missing}.",
                'data_errors' => $dataErrors,
                'timeframes' => $timeframes,
            ];
        }

        $hierarchy = $this->buildHierarchy($timeframes);
        $higherContext = $this->higherContext($timeframes, $hierarchy);
        $nearMiss = null;
        $selected = null;

        // Mirrors the original service order: 15m first, then 5m.
        foreach (['15m', '5m'] as $interval) {
            $x = $timeframes[$interval];
            $conditions = [
                'price_above_vwap' => $x['last_close'] > $x['vwap'],
                'macd_above_signal' => $x['macd'] > $x['macd_signal'],
                'histogram_positive' => $x['macd_histogram'] > 0,
                'volume_confirmed' => $x['volume_confirmed'],
                'timeframe_trend_support' => in_array($x['trend'], ['BULLISH', 'NEUTRAL'], true),
                'higher_timeframe_context' => $higherContext,
            ];
            $passed = count(array_filter($conditions)) ;
            if ($nearMiss === null || $passed > $nearMiss['passed']) {
                $nearMiss = ['timeframe' => $interval, 'passed' => $passed, 'conditions' => $conditions];
            }

            if (!in_array(false, $conditions, true)) {
                $selected = ['timeframe' => $interval, 'conditions' => $conditions];
                break;
            }
        }

        $latest5m = $timeframes['5m']['last_close'];
        $base = [
            'strategy' => 'VWAP_MACD',
            'timeframes' => $timeframes,
            'hierarchy' => $hierarchy,
            'diagnostics' => [
                'tested_entry_timeframes' => ['15m', '5m'],
                'near_miss' => $nearMiss,
                'higher_timeframe_context' => $higherContext,
            ],
        ];

        if ($selected === null) {
            $failed = [];
            foreach ($nearMiss['conditions'] as $condition => $passed) {
                if (!$passed) $failed[] = $condition;
            }
            return $base + [
                'state' => 'rejected',
                'signal_type' => null,
                'reason' => 'VWAP_MACD conditions failed on '.$nearMiss['timeframe'].': '.implode(', ', $failed).'.',
                'market' => ['last_price' => $latest5m, 'entry_timeframe' => $nearMiss['timeframe']],
            ];
        }

        $entry = $latest5m;
        $recent = array_slice($timeframes['5m']['candles'], -20);
        $swingLow = min(array_column($recent, 'low'));
        $atr = max($timeframes[$selected['timeframe']]['atr_14'], $timeframes['1d']['atr_14'] * 0.25, $entry * 0.003);
        $stop = min($swingLow, $entry - ($atr * 1.2));
        if ($stop <= 0 || $stop >= $entry) {
            $stop = $entry - ($entry * 0.005);
        }
        $risk = $entry - $stop;

        return $base + [
            'state' => 'accepted',
            'signal_type' => 'VWAP_MACD',
            'entry_price' => $entry,
            'stop_loss' => $stop,
            'target_1' => $entry + ($risk * 2.0),
            'target_2' => $entry + ($risk * 3.0),
            'opportunity_score' => 85,
            'risk_score' => min(100, max(1, (int) round(($risk / $entry) * 1000))),
            'confidence_score' => 85,
            'reason' => 'Price above Session VWAP + MACD above Signal + positive Histogram + volume confirmation on '.$selected['timeframe'].'.',
            'market' => [
                'last_price' => $entry,
                'entry_timeframe' => $selected['timeframe'],
                'entry_conditions' => $selected['conditions'],
                'session_vwap_utc' => $timeframes[$selected['timeframe']]['vwap'],
            ],
        ];
    }

    private function analyzeTimeframe(array $candles, string $interval): array
    {
        $last = count($candles) - 1;
        $macd = $this->macdSeries($candles);
        $vwap = $this->sessionVwapSeries($candles);
        $rvol = $this->relativeVolume($candles);
        $ema20 = $this->emaSeries($candles, self::EMA_TREND, 'close');
        $ema50 = $this->emaSeries($candles, self::EMA_SLOW, 'close');
        $slope20 = $ema20[$last] - $ema20[max(0, $last - 5)];
        $structure = $this->simpleStructure($candles);

        $trendScore = 0;
        $trendScore += $candles[$last]['close'] > $ema20[$last] ? 2 : -2;
        $trendScore += $ema20[$last] > $ema50[$last] ? 2 : -2;
        $trendScore += $slope20 > 0 ? 1 : ($slope20 < 0 ? -1 : 0);
        $trendScore += min(2, $structure['higher_highs']);
        $trendScore += min(2, $structure['higher_lows']);
        $trendScore -= min(2, $structure['lower_highs']);
        $trendScore -= min(2, $structure['lower_lows']);
        $trend = $trendScore >= 3 ? 'BULLISH' : ($trendScore <= -3 ? 'BEARISH' : 'NEUTRAL');

        return [
            'timeframe' => $interval,
            'candles_count' => count($candles),
            'last_timestamp' => $candles[$last]['timestamp'],
            'last_close' => $candles[$last]['close'],
            'trend' => $trend,
            'trend_score' => $trendScore,
            'structure' => $structure,
            'ema_20' => $ema20[$last],
            'ema_50' => $ema50[$last],
            'vwap' => $vwap[$last],
            'macd' => $macd['macd'][$last],
            'macd_signal' => $macd['signal'][$last],
            'macd_histogram' => $macd['histogram'][$last],
            'volume' => $candles[$last]['volume'],
            'average_volume' => $rvol['average'],
            'relative_volume' => $rvol['rvol'],
            'volume_confirmed' => $rvol['rvol'] >= self::MIN_RVOL,
            'atr_14' => $this->atr($candles, 14),
            'candles' => $candles,
        ];
    }

    private function simpleStructure(array $candles): array
    {
        $count = count($candles);
        $window = 3;
        if ($count < ($window * 2) + 5) {
            return ['higher_highs' => 0, 'higher_lows' => 0, 'lower_highs' => 0, 'lower_lows' => 0];
        }

        $highs = [];
        $lows = [];
        for ($i = $window; $i < $count - $window; $i++) {
            $isHigh = true;
            $isLow = true;
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j === $i) continue;
                if ($candles[$j]['high'] >= $candles[$i]['high']) $isHigh = false;
                if ($candles[$j]['low'] <= $candles[$i]['low']) $isLow = false;
            }
            if ($isHigh) $highs[] = (float) $candles[$i]['high'];
            if ($isLow) $lows[] = (float) $candles[$i]['low'];
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
        return ['higher_highs' => $hh, 'higher_lows' => $hl, 'lower_highs' => $lh, 'lower_lows' => $ll];
    }

    private function buildHierarchy(array $tf): array
    {
        $weights = array_column(self::TIMEFRAMES, 'weight');
        $max = array_sum($weights);
        $score = 0;
        $details = [];
        foreach (self::TIMEFRAMES as $name => $config) {
            $trend = $tf[$name]['trend'];
            $contribution = $trend === 'BULLISH' ? $config['weight'] : ($trend === 'BEARISH' ? -$config['weight'] : 0);
            $score += $contribution;
            $details[$name] = ['trend' => $trend, 'weight' => $config['weight'], 'contribution' => $contribution];
        }
        $ratio = $score / max(1, $max);
        return [
            'score' => $score,
            'max_score' => $max,
            'bias' => $ratio >= 0.30 ? 'BULLISH' : ($ratio <= -0.30 ? 'BEARISH' : 'NEUTRAL'),
            'details' => $details,
            'entry_allowed' => $score > 0,
        ];
    }

    private function higherContext(array $tf, array $hierarchy): bool
    {
        if (!$hierarchy['entry_allowed'] || !in_array($hierarchy['bias'], ['BULLISH', 'NEUTRAL'], true)) return false;
        foreach (['1d', '1h', '30m'] as $name) {
            if ($tf[$name]['trend'] === 'BEARISH') return false;
        }
        return true;
    }

    private function sessionVwapSeries(array $candles): array
    {
        $result = [];
        $session = null;
        $cumPv = 0.0;
        $cumVolume = 0.0;
        foreach ($candles as $i => $c) {
            // Crypto trades 24/7: define a stable UTC calendar-day session.
            $day = gmdate('Y-m-d', $c['timestamp']);
            if ($day !== $session) {
                $session = $day;
                $cumPv = 0.0;
                $cumVolume = 0.0;
            }
            $typical = ($c['high'] + $c['low'] + $c['close']) / 3;
            $volume = max(0.0, $c['volume']);
            $cumPv += $typical * $volume;
            $cumVolume += $volume;
            $result[$i] = $cumVolume > 0 ? $cumPv / $cumVolume : $typical;
        }
        return $result;
    }

    private function relativeVolume(array $candles): array
    {
        $last = count($candles) - 1;
        if ($last < self::VOLUME_PERIOD) return ['average' => 0.0, 'rvol' => 0.0];
        $previous = array_slice($candles, $last - self::VOLUME_PERIOD, self::VOLUME_PERIOD);
        $average = array_sum(array_column($previous, 'volume')) / self::VOLUME_PERIOD;
        $current = $candles[$last]['volume'];
        return ['average' => $average, 'rvol' => $average > 0 ? $current / $average : 0.0];
    }

    private function macdSeries(array $candles): array
    {
        $fast = $this->emaSeries($candles, self::MACD_FAST, 'close');
        $slow = $this->emaSeries($candles, self::MACD_SLOW, 'close');
        $macd = array_fill(0, count($candles), null);
        foreach ($candles as $i => $_) {
            if ($fast[$i] !== null && $slow[$i] !== null) $macd[$i] = $fast[$i] - $slow[$i];
        }
        $signal = $this->emaNullable($macd, self::MACD_SIGNAL);
        $histogram = array_fill(0, count($candles), null);
        foreach ($candles as $i => $_) {
            if ($macd[$i] !== null && $signal[$i] !== null) $histogram[$i] = $macd[$i] - $signal[$i];
        }
        return ['macd' => $macd, 'signal' => $signal, 'histogram' => $histogram];
    }

    private function emaSeries(array $candles, int $period, string $field): array
    {
        $values = array_map(static fn ($c) => (float) $c[$field], $candles);
        $result = array_fill(0, count($values), null);
        if (count($values) < $period) return $result;
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $seed;
        $multiplier = 2 / ($period + 1);
        for ($i = $period; $i < count($values); $i++) {
            $result[$i] = (($values[$i] - $result[$i - 1]) * $multiplier) + $result[$i - 1];
        }
        return $result;
    }

    private function emaNullable(array $values, int $period): array
    {
        $result = array_fill(0, count($values), null);
        $valid = [];
        foreach ($values as $i => $value) if ($value !== null) $valid[] = ['index' => $i, 'value' => (float) $value];
        if (count($valid) < $period) return $result;
        $seedValues = array_slice($valid, 0, $period);
        $previous = array_sum(array_column($seedValues, 'value')) / $period;
        $seedIndex = $seedValues[$period - 1]['index'];
        $result[$seedIndex] = $previous;
        $multiplier = 2 / ($period + 1);
        for ($i = $period; $i < count($valid); $i++) {
            $previous = (($valid[$i]['value'] - $previous) * $multiplier) + $previous;
            $result[$valid[$i]['index']] = $previous;
        }
        return $result;
    }

    private function atr(array $candles, int $period): float
    {
        if (count($candles) <= $period) return 0.0;
        $ranges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $ranges[] = max(
                $candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i - 1]['close']),
                abs($candles[$i]['low'] - $candles[$i - 1]['close'])
            );
        }
        $slice = array_slice($ranges, -$period);
        return array_sum($slice) / count($slice);
    }
}
