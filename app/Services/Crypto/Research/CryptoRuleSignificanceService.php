<?php

namespace App\\Services\\Crypto\\Research;

use InvalidArgumentException;

/**
 * Statistical utilities for testing whether a historical entry rule adds
 * predictive value beyond a benchmark. This class does not generate signals
 * or claim profitability; callers must supply out-of-sample observations.
 */
class CryptoRuleSignificanceService
{
    /**
     * Compare signal-period returns with matched benchmark returns.
     *
     * Each observation must contain:
     * - signal: bool (whether the rule fired at that timestamp)
     * - forward_return: float (future return over a predeclared horizon)
     * - benchmark_return: float (matched market/benchmark return over same horizon)
     *
     * Returns are decimal fractions, e.g. 0.01 means +1%.
     * Bootstrap resamples contiguous blocks of paired excess returns to retain
     * some serial dependence. The p-value is a two-sided null-centered bootstrap
     * approximation; it is not a substitute for walk-forward validation.
     */
    public function evaluate(
        array $observations,
        int $bootstrapIterations = 2000,
        int $blockLength = 5
    ): array {
        if ($bootstrapIterations < 1 || $blockLength < 1) {
            throw new InvalidArgumentException('Bootstrap iterations and block length must be positive.');
        }

        $signals = [];
        $allExcess = [];

        foreach ($observations as $index => $row) {
            if (!array_key_exists('signal', $row)
                || !array_key_exists('forward_return', $row)
                || !array_key_exists('benchmark_return', $row)
                || !is_numeric($row['forward_return'])
                || !is_numeric($row['benchmark_return'])) {
                throw new InvalidArgumentException("Invalid observation at index {$index}.");
            }

            $excess = (float) $row['forward_return'] - (float) $row['benchmark_return'];
            $allExcess[] = $excess;

            if ((bool) $row['signal']) {
                $signals[] = $excess;
            }
        }

        if (count($signals) < 2) {
            return [
                'status' => 'insufficient_data',
                'reason' => 'At least two signal observations are required.',
                'observation_count' => count($observations),
                'signal_count' => count($signals),
            ];
        }

        $observed = array_sum($signals) / count($signals);
        $centered = array_map(static fn ($value) => $value - $observed, $signals);
        $n = count($centered);
        $blockLength = min($blockLength, $n);
        $nullMeans = [];

        for ($iteration = 0; $iteration < $bootstrapIterations; $iteration++) {
            $sample = [];
            while (count($sample) < $n) {
                $start = random_int(0, $n - 1);
                for ($offset = 0; $offset < $blockLength && count($sample) < $n; $offset++) {
                    $sample[] = $centered[($start + $offset) % $n];
                }
            }
            $nullMeans[] = array_sum($sample) / $n;
        }

        $extreme = 0;
        foreach ($nullMeans as $mean) {
            if (abs($mean) >= abs($observed)) {
                $extreme++;
            }
        }

        $benchmarkMean = count($allExcess) > 0 ? array_sum($allExcess) / count($allExcess) : 0.0;

        return [
            'status' => 'evaluated',
            'method' => 'paired_excess_return_block_bootstrap',
            'observation_count' => count($observations),
            'signal_count' => $n,
            'signal_rate' => count($observations) > 0 ? $n / count($observations) : 0.0,
            'mean_excess_return' => $observed,
            'median_excess_return' => $this->median($signals),
            'positive_excess_rate' => count(array_filter($signals, static fn ($value) => $value > 0)) / $n,
            'bootstrap_iterations' => $bootstrapIterations,
            'block_length' => $blockLength,
            'p_value_two_sided' => ($extreme + 1) / ($bootstrapIterations + 1),
            'interpretation' => 'Statistical evidence only; not a trading recommendation or proof of net profitability.',
        ];
    }

    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2
            ? (float) $values[$middle]
            : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}
