<?php

namespace App\Services\Crypto;

class CryptoIntradayAnalyzerService
{
    /**
     * Binance Spot adaptation of the proven intraday entry rule:
     * close above current UTC-session VWAP + MACD above signal + positive histogram
     * + volume confirmation. Entry is evaluated on 15m candles; 1h/4h provide context.
     */
    public function analyze(array $candlesByInterval): array
    {
        foreach (['15m' => 35, '5m' => 60, '1m' => 60, '1h' => 60, '4h' => 60, '30m' => 60, '1d' => 30] as $interval => $minimum) {
            if (count($candlesByInterval[$interval] ?? []) < $minimum) {
                return ['state' => 'rejected', 'signal_type' => null, 'reason' => "Insufficient {$interval} candle history."];
            }
        }

        $entryCandles = array_values($candlesByInterval['15m']);
        // Binance kline format: open time, open, high, low, close, base volume, close time, quote volume...
        $nowMs = (int) floor(microtime(true) * 1000);
        $utcDayStart = (int) (strtotime(gmdate('Y-m-d 00:00:00')) * 1000);
        $session = array_values(array_filter($entryCandles, static fn ($c) => (int) $c[0] >= $utcDayStart && (int) $c[6] < $nowMs));

        // If current-day candles are missing (e.g. cache predates today's session), reject explicitly.
        if (count($session) < 3) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Insufficient 15m candles for the current UTC session VWAP.'];
        }

        // Exclude the currently forming candle from all indicators.
        $closed = array_values(array_filter($entryCandles, static fn ($c) => (int) $c[6] < $nowMs));
        if (count($closed) < 35) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Not enough closed 15m candles to calculate MACD and volume.'];
        }

        $session = array_values(array_filter($closed, static fn ($c) => (int) $c[0] >= $utcDayStart));
        if (count($session) < 3) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Insufficient closed candles for current-session VWAP.'];
        }

        $weighted = 0.0;
        $volumeSum = 0.0;
        foreach ($session as $candle) {
            $typical = ((float) $candle[2] + (float) $candle[3] + (float) $candle[4]) / 3;
            $volume = (float) $candle[5];
            $weighted += $typical * $volume;
            $volumeSum += $volume;
        }
        if ($volumeSum <= 0) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Session volume is zero; VWAP cannot be calculated.'];
        }
        $vwap = $weighted / $volumeSum;

        $closes = array_map(static fn ($c) => (float) $c[4], $closed);
        $macd = $this->macd($closes);
        $last = count($closed) - 1;
        $price = $closes[$last];
        $macdLine = $macd['line'][$last];
        $signalLine = $macd['signal'][$last];
        $histogram = $macdLine - $signalLine;

        $volumes = array_map(static fn ($c) => (float) $c[5], $closed);
        $recentVolumes = array_slice($volumes, -21, 20);
        $averageVolume = count($recentVolumes) ? array_sum($recentVolumes) / count($recentVolumes) : 0;
        $currentVolume = $volumes[$last];
        $volumeConfirmed = $averageVolume > 0 && $currentVolume >= $averageVolume * 1.05;

        $oneHour = array_values($candlesByInterval['1h']);
        $fourHour = array_values($candlesByInterval['4h']);
        $last1h = $this->lastClosed($oneHour, $nowMs);
        $last4h = $this->lastClosed($fourHour, $nowMs);
        if (!$last1h || !$last4h) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'No closed 1h/4h candles available for context.'];
        }

        $priceAboveVwap = $price > $vwap;
        $macdAboveSignal = $macdLine > $signalLine;
        $positiveHistogram = $histogram > 0;

        $conditions = [
            'entry_timeframe' => '15m',
            'available_timeframes' => ['1d', '1h', '30m', '15m', '5m', '1m'],
            'last_closed_5m_close' => (float) ($this->lastClosed(array_values($candlesByInterval['5m']), $nowMs)[4] ?? 0),
            'last_closed_1m_close' => (float) ($this->lastClosed(array_values($candlesByInterval['1m']), $nowMs)[4] ?? 0),
            'price_above_session_vwap' => $priceAboveVwap,
            'macd_above_signal' => $macdAboveSignal,
            'positive_histogram' => $positiveHistogram,
            'volume_confirmed' => $volumeConfirmed,
            'session_vwap_utc' => $vwap,
            'macd' => $macdLine,
            'macd_signal' => $signalLine,
            'macd_histogram' => $histogram,
            'current_volume' => $currentVolume,
            'average_volume_20' => $averageVolume,
            'volume_ratio' => $averageVolume > 0 ? $currentVolume / $averageVolume : null,
            'context_1h_close' => (float) $last1h[4],
            'context_4h_close' => (float) $last4h[4],
        ];

        if (!$priceAboveVwap || !$macdAboveSignal || !$positiveHistogram || !$volumeConfirmed) {
            $failed = [];
            if (!$priceAboveVwap) $failed[] = 'price is not above Session VWAP';
            if (!$macdAboveSignal) $failed[] = 'MACD is not above Signal';
            if (!$positiveHistogram) $failed[] = 'MACD histogram is not positive';
            if (!$volumeConfirmed) $failed[] = 'volume confirmation failed';

            return [
                'state' => 'rejected',
                'signal_type' => null,
                'reason' => implode('; ', $failed) . '.',
                'market' => $conditions + ['last_price' => $price],
            ];
        }

        $recent = array_slice($closed, -20);
        $swingLow = min(array_map(static fn ($c) => (float) $c[3], $recent));
        $risk = $price - $swingLow;
        if ($risk <= 0) {
            return ['state' => 'rejected', 'signal_type' => null, 'reason' => 'Could not calculate a valid stop loss below entry.'];
        }

        $confidence = 80;
        $opportunity = min(100, 70 + (int) round(min(30, (($currentVolume / max($averageVolume, 0.00000001)) - 1) * 20)));
        $riskScore = min(100, max(1, (int) round(($risk / max($price, 0.00000001)) * 1000)));

        return [
            'state' => 'accepted',
            'signal_type' => 'vwap_macd_volume',
            'entry_price' => $price,
            'stop_loss' => $swingLow,
            'target_1' => $price + ($risk * 1.5),
            'target_2' => $price + ($risk * 2.5),
            'opportunity_score' => $opportunity,
            'risk_score' => $riskScore,
            'confidence_score' => $confidence,
            'reason' => 'Price above Session VWAP + MACD above Signal + positive Histogram + volume confirmation on the 15m timeframe.',
            'market' => $conditions + ['last_price' => $price],
        ];
    }

    private function lastClosed(array $candles, int $nowMs): ?array
    {
        for ($i = count($candles) - 1; $i >= 0; $i--) {
            if (isset($candles[$i][6]) && (int) $candles[$i][6] < $nowMs) return $candles[$i];
        }
        return null;
    }

    private function macd(array $values): array
    {
        $fast = $this->emaSeries($values, 12);
        $slow = $this->emaSeries($values, 26);
        $line = [];
        foreach ($values as $i => $_) $line[] = $fast[$i] - $slow[$i];
        return ['line' => $line, 'signal' => $this->emaSeries($line, 9)];
    }

    private function emaSeries(array $values, int $period): array
    {
        $alpha = 2 / ($period + 1);
        $ema = (float) ($values[0] ?? 0);
        $result = [];
        foreach ($values as $i => $value) {
            $ema = $i === 0 ? (float) $value : ((float) $value * $alpha) + ($ema * (1 - $alpha));
            $result[] = $ema;
        }
        return $result;
    }
}
