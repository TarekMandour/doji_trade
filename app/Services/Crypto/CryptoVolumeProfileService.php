<?php

namespace App\Services\Crypto;

use RuntimeException;

class CryptoVolumeProfileService
{
    /**
     * Build a volume profile from Binance aggregate trades, never from candle estimates.
     * Trades must cover the requested window; incomplete coverage is reported to the caller.
     */
    public function build(array $trades, string $symbol, int $fromMs, int $toMs, bool $windowComplete): array
    {
        $byPrice = [];
        $firstTime = null;
        $lastTime = null;
        $tradeCount = 0;

        foreach ($trades as $trade) {
            if (!isset($trade['p'], $trade['q'], $trade['T'])) continue;
            $time = (int) $trade['T'];
            if ($time < $fromMs || $time > $toMs) continue;

            $price = (string) $trade['p'];
            $baseVolume = (float) $trade['q'];
            $quoteVolume = (float) $trade['p'] * $baseVolume;
            $byPrice[$price] = ($byPrice[$price] ?? 0.0) + $quoteVolume;
            $firstTime = $firstTime === null ? $time : min($firstTime, $time);
            $lastTime = $lastTime === null ? $time : max($lastTime, $time);
            $tradeCount++;
        }

        if (!$byPrice) {
            throw new RuntimeException('No aggregate trades were available for the requested 24-hour window.');
        }

        ksort($byPrice, SORT_NUMERIC);
        $totalVolume = array_sum($byPrice);
        $pocPrice = (string) array_search(max($byPrice), $byPrice, true);
        $prices = array_keys($byPrice);
        $pocIndex = array_search($pocPrice, $prices, true);

        // Expand from POC toward the adjacent price level with the larger volume until 70% is covered.
        $included = [$pocIndex => true];
        $covered = $byPrice[$pocPrice];
        $left = $pocIndex - 1;
        $right = $pocIndex + 1;
        $target = $totalVolume * 0.70;

        while ($covered < $target && ($left >= 0 || $right < count($prices))) {
            $leftVolume = $left >= 0 ? $byPrice[$prices[$left]] : -1;
            $rightVolume = $right < count($prices) ? $byPrice[$prices[$right]] : -1;

            if ($rightVolume > $leftVolume) {
                $included[$right] = true;
                $covered += $rightVolume;
                $right++;
            } else {
                $included[$left] = true;
                $covered += $leftVolume;
                $left--;
            }
        }

        $includedIndexes = array_keys($included);
        sort($includedIndexes);

        return [
            'schema_version' => 1,
            'symbol' => strtoupper($symbol),
            'source' => 'binance_spot_aggregate_trades',
            'window' => ['from_ms' => $fromMs, 'to_ms' => $toMs],
            'coverage' => [
                'first_trade_ms' => $firstTime,
                'last_trade_ms' => $lastTime,
                'trade_count' => $tradeCount,
                'complete' => $windowComplete && $firstTime !== null && $lastTime !== null,
            ],
            'volume_unit' => 'quote_asset',
            'total_quote_volume' => $totalVolume,
            'poc' => ['price' => (float) $pocPrice, 'quote_volume' => $byPrice[$pocPrice]],
            'vah' => (float) $prices[max($includedIndexes)],
            'val' => (float) $prices[min($includedIndexes)],
            'value_area_percent' => 70,
            'volume_by_price' => $byPrice,
            'data_as_of' => gmdate('c', (int) ($lastTime / 1000)),
            'last_updated_at' => now()->toIso8601String(),
        ];
    }
}
