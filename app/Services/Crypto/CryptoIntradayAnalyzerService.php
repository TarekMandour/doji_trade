<?php

namespace App\Services\Crypto;

class CryptoIntradayAnalyzerService
{
    public function analyze(array $candlesByInterval, array $volumeProfile): array
    {
        foreach (['1h', '4h', '1d'] as $interval) {
            if (count($candlesByInterval[$interval] ?? []) < 60) {
                return ['state' => 'rejected', 'signal_type' => null, 'reason' => "Insufficient {$interval} candles; at least 60 are required."];
            }
        }

        if (empty($volumeProfile['coverage']['complete'])) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'The last 24-hour trade-based volume profile is incomplete.'];
        }

        $closes = [];
        foreach (['1h', '4h', '1d'] as $interval) {
            $closes[$interval] = array_map(static fn ($candle) => (float) $candle[4], $candlesByInterval[$interval]);
        }

        $price = end($closes['1h']);
        $ema20h = $this->ema($closes['1h'], 20);
        $ema50h = $this->ema($closes['1h'], 50);
        $ema20_4h = $this->ema($closes['4h'], 20);
        $ema50_4h = $this->ema($closes['4h'], 50);
        $ema20d = $this->ema($closes['1d'], 20);

        $bullish = $price > $ema20h && $ema20h > $ema50h
            && end($closes['4h']) > $ema20_4h && $ema20_4h > $ema50_4h
            && end($closes['1d']) > $ema20d;

        $bearish = $price < $ema20h && $ema20h < $ema50h
            && end($closes['4h']) < $ema20_4h && $ema20_4h < $ema50_4h
            && end($closes['1d']) < $ema20d;

        if (!$bullish && !$bearish) {
            return [
                'state' => 'rejected',
                'signal_type' => null,
                'reason' => 'The 1h, 4h and 1d trend conditions are not aligned.',
                'market' => ['last_price' => $price, 'ema20_1h' => $ema20h, 'ema50_1h' => $ema50h],
            ];
        }

        $recent = array_slice($candlesByInterval['1h'], -20);
        $swingLow = min(array_map(static fn ($c) => (float) $c[3], $recent));
        $swingHigh = max(array_map(static fn ($c) => (float) $c[2], $recent));
        $poc = (float) ($volumeProfile['poc']['price'] ?? 0);

        if ($bullish) {
            $stop = min($swingLow, $price * 0.99);
            $risk = $price - $stop;
            if ($risk <= 0) return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Could not calculate a valid stop loss.'];
            $target1 = $price + $risk * 1.5;
            $target2 = $price + $risk * 2.5;
            $signal = 'bullish_trend';
        } else {
            $stop = max($swingHigh, $price * 1.01);
            $risk = $stop - $price;
            if ($risk <= 0) return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Could not calculate a valid stop loss.'];
            $target1 = $price - $risk * 1.5;
            $target2 = $price - $risk * 2.5;
            $signal = 'bearish_trend';
        }

        $trendStrength = abs(($price - $ema20h) / max($ema20h, 0.00000001)) * 100;
        $confidence = min(95, max(50, (int) round(55 + $trendStrength * 3)));
        $opportunity = min(100, max(1, (int) round($confidence - 10)));
        $riskScore = min(100, max(1, (int) round(($risk / max($price, 0.00000001)) * 1000)));

        return [
            'state' => 'accepted',
            'signal_type' => $signal,
            'entry_price' => $price,
            'stop_loss' => $stop,
            'target_1' => $target1,
            'target_2' => $target2,
            'opportunity_score' => $opportunity,
            'risk_score' => $riskScore,
            'confidence_score' => $confidence,
            'reason' => $bullish
                ? 'Bullish trend alignment across 1h, 4h and 1d. Spot long setup only; confirm liquidity and risk before acting.'
                : 'Bearish trend alignment across 1h, 4h and 1d. No short order is recommended in Spot; treat this as a risk/avoidance signal.',
            'market' => ['last_price' => $price, 'ema20_1h' => $ema20h, 'ema50_1h' => $ema50h, 'volume_poc' => $poc],
        ];
    }

    private function ema(array $values, int $period): float
    {
        $values = array_values($values);
        $slice = array_slice($values, -($period * 3));
        $alpha = 2 / ($period + 1);
        $ema = (float) array_shift($slice);
        foreach ($slice as $value) $ema = ((float) $value * $alpha) + ($ema * (1 - $alpha));
        return $ema;
    }
}
