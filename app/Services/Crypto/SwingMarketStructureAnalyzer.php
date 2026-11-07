<?php

namespace App\Services\Crypto;

/**
 * Swing eligibility evaluator. Input candles use Binance kline array format.
 * Only closed candles are considered. No entry signal or opportunity score is produced.
 */
class SwingMarketStructureAnalyzer
{
    private const TIMEFRAMES = [
        '4h' => ['left' => 5, 'right' => 5],
        '1h' => ['left' => 4, 'right' => 4],
        '15m' => ['left' => 3, 'right' => 3],
        '5m' => ['left' => 2, 'right' => 2],
    ];

    public function analyze(array $candlesByTimeframe): array
    {
        $results = [];
        foreach (self::TIMEFRAMES as $timeframe => $config) {
            $candles = $candlesByTimeframe[$timeframe] ?? [];
            $results[$timeframe] = $this->analyzeTimeframe($candles, $config['left'], $config['right']);
        }

        $passedOthers = (int) ($results['1h']['passed'] ?? false)
            + (int) ($results['15m']['passed'] ?? false)
            + (int) ($results['5m']['passed'] ?? false);

        // 4H is mandatory; at least one lower timeframe must also pass.
        $eligible = ($results['4h']['passed'] ?? false) && $passedOthers >= 1;

        return [
            'eligible' => $eligible,
            'passed_timeframes' => array_keys(array_filter($results, fn ($item) => $item['passed'] ?? false)),
            'timeframes' => $results,
            'reason' => $eligible
                ? '4H passed and at least one of 1H, 15M, 5M passed.'
                : 'Swing structure requirement not met (4H is mandatory and at least one lower timeframe must pass).',
        ];
    }

    private function analyzeTimeframe(array $rawCandles, int $left, int $right): array
    {
        $candles = array_values(array_filter($rawCandles, fn ($c) => is_array($c) && count($c) >= 7));
        if (count($candles) < max(60, $left + $right + 20)) {
            return ['passed' => false, 'reason' => 'Insufficient closed candles.', 'ema20' => null, 'ema50' => null];
        }

        // Candle close time must be before now. The caller also requests closed bars only.
        $nowMs = (int) floor(microtime(true) * 1000);
        $candles = array_values(array_filter($candles, fn ($c) => (int) $c[6] < $nowMs));
        if (count($candles) < 60) {
            return ['passed' => false, 'reason' => 'Insufficient closed candles after removing the open candle.', 'ema20' => null, 'ema50' => null];
        }

        $highs = array_map(fn ($c) => (float) $c[2], $candles);
        $lows = array_map(fn ($c) => (float) $c[3], $candles);
        $closes = array_map(fn ($c) => (float) $c[4], $candles);

        $ema20 = $this->ema($closes, 20);
        $ema50 = $this->ema($closes, 50);
        $atr = $this->atr($candles, 14);
        if ($ema20 === null || $ema50 === null || $atr === null || $atr <= 0) {
            return ['passed' => false, 'reason' => 'Unable to calculate EMA/ATR.', 'ema20' => $ema20, 'ema50' => $ema50];
        }

        $last = count($candles) - 1;
        // EMA20/EMA50 are calculated for diagnostic visibility only.
        // They are intentionally not used as Swing eligibility conditions.

        $rawHighPivots = $this->confirmedPivots($highs, $left, $right, true);
        $rawLowPivots = $this->confirmedPivots($lows, $left, $right, false);
        $swingPivots = $this->applyAtrSwingFilter($rawHighPivots, $rawLowPivots, $atr);
        $highPivots = array_values(array_filter($swingPivots, fn ($p) => $p['type'] === 'high'));
        $lowPivots = array_values(array_filter($swingPivots, fn ($p) => $p['type'] === 'low'));

        if (count($highPivots) < 2 || count($lowPivots) < 2) {
            return ['passed' => false, 'reason' => 'Not enough confirmed swing highs/lows.', 'ema20' => $ema20, 'ema50' => $ema50];
        }

        // The 50-candle freshness rule applies to the latest confirmed high
        // and latest confirmed low only. Their previous comparison pivots may be older.
        $lastHighs = array_slice($highPivots, -2);
        $lastLows = array_slice($lowPivots, -2);
        $latestHighAge = $last - $lastHighs[1]['index'];
        $latestLowAge = $last - $lastLows[1]['index'];

        if ($latestHighAge > 50 || $latestLowAge > 50) {
            return [
                'passed' => false,
                'reason' => 'Latest confirmed swing high or low is older than 50 candles.',
                'ema20' => $ema20,
                'ema50' => $ema50,
                'latest_swing_high_age' => $latestHighAge,
                'latest_swing_low_age' => $latestLowAge,
            ];
        }
        $hh = $lastHighs[1]['price'] > $lastHighs[0]['price'];
        $hl = $lastLows[1]['price'] > $lastLows[0]['price'];
        $passed = $hh && $hl;

        return [
            'passed' => $passed,
            'reason' => $passed ? 'HH + HL confirmed; EMA conditions are not part of eligibility.' : 'Market structure is not HH + HL.',
            'hh' => $hh,
            'hl' => $hl,
            'ema20' => $ema20,
            'ema50' => $ema50,
            'atr14' => $atr,
            'last_swing_highs' => $lastHighs,
            'last_swing_lows' => $lastLows,
        ];
    }

    private function confirmedPivots(array $values, int $left, int $right, bool $high): array
    {
        $pivots = [];
        $last = count($values) - 1;
        for ($i = $left; $i <= $last - $right; $i++) {
            $window = array_slice($values, $i - $left, $left + $right + 1);
            $value = $values[$i];
            $isPivot = $high ? $value === max($window) : $value === min($window);
            if ($isPivot) $pivots[] = ['index' => $i, 'price' => $value];
        }
        return $pivots;
    }

    /**
     * Build an alternating swing sequence. A new opposite pivot is retained only
     * when its move from the last retained pivot is at least one current ATR(14).
     * If consecutive pivots have the same type, retain only the more extreme one.
     */
    private function applyAtrSwingFilter(array $highPivots, array $lowPivots, float $atr): array
    {
        $candidates = [];
        foreach ($highPivots as $pivot) $candidates[] = $pivot + ['type' => 'high'];
        foreach ($lowPivots as $pivot) $candidates[] = $pivot + ['type' => 'low'];
        usort($candidates, fn ($a, $b) => $a['index'] <=> $b['index']);

        $swings = [];
        foreach ($candidates as $candidate) {
            if ($swings === []) {
                $swings[] = $candidate;
                continue;
            }

            $lastIndex = count($swings) - 1;
            $previous = $swings[$lastIndex];
            if ($candidate['type'] === $previous['type']) {
                $moreExtreme = $candidate['type'] === 'high'
                    ? $candidate['price'] > $previous['price']
                    : $candidate['price'] < $previous['price'];
                if ($moreExtreme) $swings[$lastIndex] = $candidate;
                continue;
            }

            if (abs($candidate['price'] - $previous['price']) >= $atr) {
                $swings[] = $candidate;
            }
        }

        return $swings;
    }

    private function ema(array $values, int $period): ?float
    {
        if (count($values) < $period) return null;
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $multiplier = 2 / ($period + 1);
        for ($i = $period; $i < count($values); $i++) {
            $seed = (($values[$i] - $seed) * $multiplier) + $seed;
        }
        return $seed;
    }

    private function atr(array $candles, int $period): ?float
    {
        if (count($candles) <= $period) return null;
        $trueRanges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $high = (float) $candles[$i][2];
            $low = (float) $candles[$i][3];
            $previousClose = (float) $candles[$i - 1][4];
            $trueRanges[] = max($high - $low, abs($high - $previousClose), abs($low - $previousClose));
        }
        if (count($trueRanges) < $period) return null;
        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;
        for ($i = $period; $i < count($trueRanges); $i++) {
            $atr = (($atr * ($period - 1)) + $trueRanges[$i]) / $period;
        }
        return $atr;
    }
}
