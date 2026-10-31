<?php

namespace App\Services\Thndr;

/**
 * محرك التحليل الكامل (Market Watch → Candles → Swings → Trend → S/R → Volume →
 * Entry Zone → Opportunity/Fast Score) — منقول من core/* في stocks/tarek ومُقيّد
 * ليعمل فقط على أسهم Watchlist المحدد، والتوكن يأتي من إعدادات قاعدة البيانات.
 */
class StockAnalysisService
{
    /**
     * Algorithm parity target:
     * standalone ThndrLoadCandles + TrendAnalyzer + SupportResistance
     * + VolumeStrength + EntryZone + OpportunityScores.
     *
     * PatternDetectorV4 remains a separate standalone module, just as it
     * is not part of process2.php's OpportunityScores pipeline.
     */
    private const CANDLES_DAYS = 90;

    private const SWING_WINDOW = 3;

    private const VOLUME_PERIOD = 20;

    private const ATR_PERIOD = 20;

    private const RVOL_VERY_LOW = 0.80;

    private const RVOL_NORMAL = 1.20;

    private const RVOL_HIGH = 1.80;

    private const VOLUME_CHANGE_THRESHOLD = 0.05;

    private const ACCUMULATION_THRESHOLD = 70;

    private const DISTRIBUTION_THRESHOLD = 30;

    private const MERGE_PERCENT = 0.003;

    public function __construct(private readonly ThndrApi $api) {}

    /**
     * @param  array<int, string>  $assetIds  asset_id لأسهم الـ Watchlist فقط
     * @param  string  $resolution  الفريم الزمني (1MIN/5MIN/10MIN/1HR/1D/1W/1M)
     * @param  int  $candlesDays  عدد أيام الاسترجاع لبناء الشموع
     * @return array<int, array<string, mixed>> الأسهم مرتبة حسب opportunity_score
     */
    public function analyzeAssetIds(array $assetIds, string $resolution = '1D', int $candlesDays = self::CANDLES_DAYS): array
    {
        $assetIds = array_values(array_unique(array_filter($assetIds)));

        if (empty($assetIds)) {
            return [];
        }

        $stocks = $this->loadAndMergeStocks($assetIds);
        $stocks = $this->filterTradableStocks($stocks);
        $stocks = $this->loadCandles($stocks, $resolution, $candlesDays);
        $stocks = $this->findSwingPoints($stocks);
        $stocks = $this->detectStructure($stocks);
        $stocks = $this->extractSupportResistance($stocks);
        $stocks = $this->detectVolumeStrength($stocks);
        $stocks = $this->calculateEntryZone($stocks);
        $stocks = $this->calculateOpportunityScores($stocks);

        return $this->rankStocks($stocks);
    }

    /** المرحلة 1: جلب marketwatch + تفاصيل كل سهم من الـ watchlist فقط */
    private function loadAndMergeStocks(array $assetIds): array
    {
        $marketWatch = $this->api->fetchMarketWatch();

        $marketIndex = [];
        foreach ($marketWatch['assets'] ?? [] as $item) {
            if (in_array($item['asset_id'] ?? null, $assetIds, true)) {
                $marketIndex[$item['asset_id']] = $item;
            }
        }

        $stocks = [];
        foreach ($assetIds as $assetId) {
            if (! isset($marketIndex[$assetId])) {
                continue;
            }

            $info = $this->api->fetchAsset($assetId) ?? [];

            $stocks[] = array_merge($info, $marketIndex[$assetId]);
        }

        return $stocks;
    }

    /** المرحلة 2: استبعاد الأسهم غير القابلة للتحليل */
    private function filterTradableStocks(array $stocks): array
    {
        return array_values(array_filter($stocks, function ($stock) {
            if (($stock['is_tradable'] ?? false) !== true) {
                return false;
            }

            if (($stock['is_otc'] ?? false) === true) {
                return false;
            }

            if (($stock['is_right'] ?? false) === true) {
                return false;
            }

            if (($stock['symbol_state'] ?? '') !== 'A') {
                return false;
            }

            return true;
        }));
    }

    /** المرحلة 3: تحميل الشموع لكل سهم على الفريم وعدد الأيام المطلوبين */
    private function loadCandles(array $stocks, string $resolution = '1D', int $candlesDays = self::CANDLES_DAYS): array
    {
        $start = (string) strtotime('-'.$candlesDays.' days');
        $end = (string) time();

        foreach ($stocks as &$stock) {
            $assetId = $stock['id'] ?? $stock['asset_id'] ?? null;

            $stock['candles'] = $assetId
                ? $this->api->normalizeCandles($this->api->fetchCandles($assetId, $resolution, (int) $start, (int) $end))
                : [];
        }
        unset($stock);

        return $stocks;
    }

    /** المرحلة 4: نقاط الـ Swing (Fractal بنافذة 3 شموع) */
    private function findSwingPoints(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $candles = $stock['candles'];
            $count = count($candles);
            $swings = [];

            for ($i = self::SWING_WINDOW; $i < $count - self::SWING_WINDOW; $i++) {
                $isHigh = true;
                $isLow = true;

                for ($j = $i - self::SWING_WINDOW; $j <= $i + self::SWING_WINDOW; $j++) {
                    if ($j === $i) {
                        continue;
                    }

                    if ($candles[$j]['high'] >= $candles[$i]['high']) {
                        $isHigh = false;
                    }

                    if ($candles[$j]['low'] <= $candles[$i]['low']) {
                        $isLow = false;
                    }

                    if (! $isHigh && ! $isLow) {
                        break;
                    }
                }

                if ($isHigh) {
                    $swings[] = [
                        'type' => 'HIGH',
                        'index' => $i,
                        'price' => (float) $candles[$i]['high'],
                        'timestamp' => $candles[$i]['timestamp'],
                        'candle' => $candles[$i],
                        'broken' => false,
                        'broken_at' => null,
                        'break_index' => null,
                    ];
                } elseif ($isLow) {
                    $swings[] = [
                        'type' => 'LOW',
                        'index' => $i,
                        'price' => (float) $candles[$i]['low'],
                        'timestamp' => $candles[$i]['timestamp'],
                        'candle' => $candles[$i],
                        'broken' => false,
                        'broken_at' => null,
                        'break_index' => null,
                    ];
                }
            }

            $stock['swings'] = $swings;
        }
        unset($stock);

        return $stocks;
    }

    /** المرحلة 5: تحديد الاتجاه العام (Higher Highs/Lows) */
    private function detectStructure(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $swings = $stock['swings'] ?? [];
            $candles = $stock['candles'] ?? [];

            if (empty($swings) || empty($candles)) {
                $stock['trend'] = 'UNKNOWN';
                $stock['trend_score'] = 0;
                $stock['hh'] = 0;
                $stock['hl'] = 0;
                $stock['lh'] = 0;
                $stock['ll'] = 0;
                $stock['bos'] = 0;
                $stock['bullish_bos'] = 0;
                $stock['bearish_bos'] = 0;
                $stock['choch'] = 0;
                $stock['liquidity_sweep'] = false;
                $stock['last_bos_price'] = null;
                $stock['last_swing_high'] = null;
                $stock['last_swing_low'] = null;
                continue;
            }

            $hh = 0;
            $hl = 0;
            $lh = 0;
            $ll = 0;
            $bos = 0;
            $bullishBos = 0;
            $bearishBos = 0;
            $choch = 0;
            $liquiditySweep = false;
            $lastBosPrice = null;
            $lastSwingHigh = null;
            $lastSwingLow = null;

            $swingHighs = array_values(array_filter(
                $swings,
                fn ($s) => $s['type'] === 'HIGH'
            ));
            $swingLows = array_values(array_filter(
                $swings,
                fn ($s) => $s['type'] === 'LOW'
            ));

            if (!empty($swingHighs)) {
                $lastSwingHigh = end($swingHighs)['price'];
            }

            if (!empty($swingLows)) {
                $lastSwingLow = end($swingLows)['price'];
            }

            $sequence = [];

            foreach ($swings as $key => $swing) {
                $sequence[] = [
                    'key' => $key,
                    'type' => $swing['type'],
                    'price' => $swing['price'],
                    'index' => $swing['index'],
                    'broken' => $swing['broken'] ?? false,
                ];
            }

            $lastSwingIndex = !empty($sequence)
                ? end($sequence)['index']
                : -1;

            $lastSwingPrice = !empty($sequence)
                ? end($sequence)['price']
                : 0;

            // مطابق للمحرك المستقل: آخر 11 Swing فقط في تحليل الهيكل.
            $sequence = array_slice($sequence, -11);

            $previousHigh = null;
            $previousLow = null;

            foreach ($sequence as $current) {
                $currentType = $current['type'];
                $currentPrice = (float) $current['price'];
                $originalKey = $current['key'];
                $originalIndex = $current['index'];

                if ($currentType === 'HIGH') {
                    if ($previousHigh !== null && $currentPrice > $previousHigh) {
                        $hh++;
                    }

                    if ($previousHigh !== null && $currentPrice < $previousHigh) {
                        $lh++;
                    }

                    $previousHigh = $currentPrice;
                } else {
                    if ($previousLow !== null && $currentPrice > $previousLow) {
                        $hl++;
                    }

                    if ($previousLow !== null && $currentPrice < $previousLow) {
                        $ll++;
                    }

                    $previousLow = $currentPrice;
                }

                if (!($current['broken'] ?? false)) {
                    $breakIndex = null;

                    for ($j = $originalIndex + 1; $j < count($candles); $j++) {
                        $candleClose = (float) $candles[$j]['close'];

                        if (
                            $currentType === 'HIGH'
                            && $candleClose > $currentPrice
                        ) {
                            $breakIndex = $j;
                            break;
                        }

                        if (
                            $currentType === 'LOW'
                            && $candleClose < $currentPrice
                        ) {
                            $breakIndex = $j;
                            break;
                        }
                    }

                    if ($breakIndex !== null) {
                        $stock['swings'][$originalKey]['broken'] = true;
                        $stock['swings'][$originalKey]['broken_at'] =
                            $candles[$breakIndex]['timestamp'];
                        $stock['swings'][$originalKey]['break_index'] = $breakIndex;

                        $bos++;
                        $lastBosPrice = $currentPrice;

                        if ($currentType === 'HIGH') {
                            $bullishBos++;
                        } else {
                            $bearishBos++;
                        }
                    }
                }
            }

            $choch = $this->detectChoch($sequence, $candles);
            $liquiditySweep = $this->detectLiquiditySweep(
                $candles,
                $lastSwingIndex,
                (float) $lastSwingPrice
            );

            $trend = $this->determineTrend(
                $hh,
                $hl,
                $lh,
                $ll,
                $bos,
                $choch,
                $bullishBos,
                $bearishBos
            );

            $trendScore = $this->calculateTrendScore(
                $hh,
                $hl,
                $lh,
                $ll,
                $bos,
                $choch,
                $liquiditySweep,
                $bullishBos,
                $bearishBos
            );

            $stock['trend'] = $trend;
            $stock['trend_score'] = $trendScore;
            $stock['hh'] = $hh;
            $stock['hl'] = $hl;
            $stock['lh'] = $lh;
            $stock['ll'] = $ll;
            $stock['bos'] = $bos;
            $stock['bullish_bos'] = $bullishBos;
            $stock['bearish_bos'] = $bearishBos;
            $stock['choch'] = $choch;
            $stock['liquidity_sweep'] = $liquiditySweep;
            $stock['last_bos_price'] = $lastBosPrice;
            $stock['last_swing_high'] = $lastSwingHigh;
            $stock['last_swing_low'] = $lastSwingLow;
        }

        unset($stock);

        return $stocks;
    }

    private function detectChoch(array $sequence, array $candles): int
    {
        $chochCount = 0;
        $trendDirection = 'UNKNOWN';
        $lastHigherLow = null;
        $lastLowerHigh = null;

        if (count($sequence) < 4) {
            return 0;
        }

        for ($i = 0; $i < count($sequence) - 1; $i++) {
            $current = $sequence[$i];

            if (
                $i === 0
                && $current['type'] === 'LOW'
                && $sequence[$i + 1]['type'] === 'HIGH'
            ) {
                $trendDirection = 'UP';
            } elseif (
                $i === 0
                && $current['type'] === 'HIGH'
                && $sequence[$i + 1]['type'] === 'LOW'
            ) {
                $trendDirection = 'DOWN';
            }

            if (
                $i >= 2
                && $sequence[$i - 2]['type'] === 'LOW'
                && $sequence[$i - 1]['type'] === 'HIGH'
            ) {
                $trendDirection = 'UP';
                $lastHigherLow = $sequence[$i - 2]['price'];
            } elseif (
                $i >= 2
                && $sequence[$i - 2]['type'] === 'HIGH'
                && $sequence[$i - 1]['type'] === 'LOW'
            ) {
                $trendDirection = 'DOWN';
                $lastLowerHigh = $sequence[$i - 2]['price'];
            }

            if (
                $trendDirection === 'UP'
                && $current['type'] === 'LOW'
                && $lastHigherLow !== null
                && $current['price'] < $lastHigherLow
            ) {
                for ($j = $current['index'] + 1; $j < count($candles); $j++) {
                    if ($candles[$j]['close'] < $lastHigherLow) {
                        $chochCount++;
                        $trendDirection = 'DOWN';
                        break;
                    }
                }
            } elseif (
                $trendDirection === 'DOWN'
                && $current['type'] === 'HIGH'
                && $lastLowerHigh !== null
                && $current['price'] > $lastLowerHigh
            ) {
                for ($j = $current['index'] + 1; $j < count($candles); $j++) {
                    if ($candles[$j]['close'] > $lastLowerHigh) {
                        $chochCount++;
                        $trendDirection = 'UP';
                        break;
                    }
                }
            }
        }

        return $chochCount;
    }

    private function detectLiquiditySweep(
        array $candles,
        int $lastSwingIndex,
        float $lastSwingPrice
    ): bool {
        if (
            empty($candles)
            || $lastSwingIndex < 0
            || $lastSwingIndex >= count($candles) - 1
        ) {
            return false;
        }

        for ($j = $lastSwingIndex + 1; $j < count($candles); $j++) {
            $candle = $candles[$j];

            if (
                $candle['high'] > $lastSwingPrice
                && $candle['close'] < $lastSwingPrice
            ) {
                return true;
            }

            if (
                $candle['low'] < $lastSwingPrice
                && $candle['close'] > $lastSwingPrice
            ) {
                return true;
            }
        }

        return false;
    }

    private function determineTrend(
        int $hh,
        int $hl,
        int $lh,
        int $ll,
        int $bos,
        int $choch,
        int $bullishBos,
        int $bearishBos
    ): string {
        $bullScore = 0;

        if ($hh >= $lh) {
            $bullScore += 2;
        }

        if ($hl >= $ll) {
            $bullScore += 2;
        }

        if ($bullishBos > $bearishBos) {
            $bullScore += 3;
        }

        if ($choch === 0) {
            $bullScore += 1;
        }

        $bearScore = 0;

        if ($ll > $hl) {
            $bearScore += 2;
        }

        if ($lh > $hh) {
            $bearScore += 2;
        }

        if ($bearishBos > $bullishBos) {
            $bearScore += 3;
        }

        if ($choch > 0) {
            $bearScore += 1;
        }

        if ($bullScore >= 7) {
            return 'STRONG_UP';
        }

        if ($bullScore >= 5) {
            return 'UP';
        }

        if ($bearScore >= 7) {
            return 'DOWN';
        }

        return 'SIDEWAYS';
    }

    private function calculateTrendScore(
        int $hh,
        int $hl,
        int $lh,
        int $ll,
        int $bos,
        int $choch,
        bool $liquiditySweep,
        int $bullishBos,
        int $bearishBos
    ): int {
        $score = 0;

        $netHighs = $hh - $lh;
        $netLows = $hl - $ll;

        if ($netHighs > 0) {
            $score += min($netHighs * 10, 30);
        } elseif ($netHighs < 0) {
            $score += max($netHighs * 10, -30);
        }

        if ($netLows > 0) {
            $score += min($netLows * 10, 30);
        } elseif ($netLows < 0) {
            $score += max($netLows * 10, -30);
        }

        $netBos = $bullishBos - $bearishBos;

        if ($netBos > 0) {
            $score += min($netBos * 8, 25);
        } elseif ($netBos < 0) {
            $score += max($netBos * 8, -25);
        }

        if ($choch > 0) {
            $score += min($choch * 12, 20);
        }

        if ($liquiditySweep) {
            $score += 5;
        }

        return max(0, min(100, $score + 50));
    }

    /** المرحلة 6: الدعم والمقاومة */
    private function extractSupportResistance(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $raw = $this->extractRawLevels($stock);

            $supports = $this->countLevelTouches($raw['candles'], $raw['supports'], 'LOW');
            $supports = $this->mergeLevels($supports);
            $supports = $this->calculateLevelStrength($supports, (float) ($stock['last_trade_price'] ?? 0), count($raw['candles']) - 1);
            $supports = $this->finalizeLevels($supports);

            $resistances = $this->countLevelTouches($raw['candles'], $raw['resistances'], 'HIGH');
            $resistances = $this->mergeLevels($resistances);
            $resistances = $this->calculateLevelStrength($resistances, (float) ($stock['last_trade_price'] ?? 0), count($raw['candles']) - 1);
            $resistances = $this->finalizeLevels($resistances);

            $stock['supports'] = $supports;
            $stock['resistances'] = $resistances;
        }
        unset($stock);

        return $stocks;
    }

    private function extractRawLevels(array $stock): array
    {
        $candles = array_slice($stock['candles'], -100);
        $firstIndex = count($stock['candles']) - count($candles);

        $supports = [];
        $resistances = [];

        foreach ($stock['swings'] as $swing) {
            if ($swing['index'] < $firstIndex) {
                continue;
            }

            $level = [
                'price' => (float) $swing['price'],
                'touches' => 0,
                'group_touches' => 0,
                'strength' => 0,
                'distance_percent' => 0,
                'swing_index' => $swing['index'],
                'timestamp' => $swing['timestamp'],
                'volume' => (float) ($swing['candle']['volume'] ?? 0),
                'source' => $swing['type'] === 'LOW' ? 'SWING_LOW' : 'SWING_HIGH',
            ];

            if ($swing['type'] === 'LOW') {
                $supports[] = $level;
            } else {
                $resistances[] = $level;
            }
        }

        return ['supports' => $supports, 'resistances' => $resistances, 'candles' => $candles];
    }

    private function countLevelTouches(array $candles, array $levels, string $type, float $tolerance = 0.003): array
    {
        foreach ($levels as &$level) {
            $touches = 0;
            $zoneLow = $level['price'] * (1 - $tolerance);
            $zoneHigh = $level['price'] * (1 + $tolerance);

            foreach ($candles as $candle) {
                if ($candle['timestamp'] === $level['timestamp']) {
                    continue;
                }

                if ($type === 'LOW') {
                    if ($candle['low'] <= $zoneHigh && $candle['low'] >= $zoneLow) {
                        $touches++;
                    }
                } elseif ($candle['high'] >= $zoneLow && $candle['high'] <= $zoneHigh) {
                    $touches++;
                }
            }

            $level['touches'] = $touches;
            $level['group_touches'] = $touches;
        }
        unset($level);

        return $levels;
    }

    private function mergeLevels(array $levels): array
    {
        if (empty($levels)) {
            return [];
        }

        usort($levels, fn ($a, $b) => $a['price'] <=> $b['price']);

        $merged = [];

        foreach ($levels as $level) {
            if (empty($merged)) {
                $merged[] = $level;

                continue;
            }

            $last = count($merged) - 1;
            $difference = abs($level['price'] - $merged[$last]['price']) / $merged[$last]['price'];

            if ($difference > self::MERGE_PERCENT) {
                $merged[] = $level;

                continue;
            }

            $merged[$last]['group_touches'] += $level['group_touches'];

            $replace = false;

            if ($level['touches'] > $merged[$last]['touches']) {
                $replace = true;
            } elseif ($level['touches'] === $merged[$last]['touches']) {
                // timestamps are epoch seconds (see ThndrApi::normalizeCandles)
                $levelTime = (int) $level['timestamp'];
                $mergedTime = (int) $merged[$last]['timestamp'];

                if ($levelTime > $mergedTime) {
                    $replace = true;
                } elseif ($levelTime === $mergedTime && $level['volume'] > $merged[$last]['volume']) {
                    $replace = true;
                }
            }

            if ($replace) {
                $merged[$last]['price'] = $level['price'];
                $merged[$last]['touches'] = $level['touches'];
                $merged[$last]['timestamp'] = $level['timestamp'];
                $merged[$last]['swing_index'] = $level['swing_index'];
                $merged[$last]['volume'] = $level['volume'];
                $merged[$last]['source'] = $level['source'];
            }
        }

        return array_values($merged);
    }

    private function calculateLevelStrength(array $levels, float $currentPrice, int $lastCandleIndex): array
    {
        foreach ($levels as &$level) {
            $touchScore = min(50, $level['group_touches'] * 10);
            $age = max(1, $lastCandleIndex - $level['swing_index']);
            $recencyScore = max(0, 30 - ($age * 0.5));
            $volumeScore = $level['volume'] > 0 ? min(20, log10($level['volume']) * 3) : 0;

            $level['strength'] = round($touchScore + $recencyScore + $volumeScore, 2);

            $level['distance_percent'] = $currentPrice > 0
                ? round(abs($currentPrice - $level['price']) / $currentPrice * 100, 2)
                : null;
        }
        unset($level);

        return $levels;
    }

    private function finalizeLevels(array $levels, int $limit = 3): array
    {
        usort($levels, function ($a, $b) {
            if ($a['strength'] !== $b['strength']) {
                return $b['strength'] <=> $a['strength'];
            }

            if ($a['distance_percent'] !== $b['distance_percent']) {
                return $a['distance_percent'] <=> $b['distance_percent'];
            }

            if ($a['group_touches'] !== $b['group_touches']) {
                return $b['group_touches'] <=> $a['group_touches'];
            }

            return (int) $b['timestamp'] <=> (int) $a['timestamp'];
        });

        return array_slice($levels, 0, $limit);
    }

    /** المرحلة 7: قوة الحجم (RVOL, Trend, Accumulation) */
    private function detectVolumeStrength(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $relativeVolume = $this->calculateRelativeVolume($stock['candles']);
            $volumeTrend = $this->detectVolumeTrend($stock['candles']);
            $accumulation = $this->detectAccumulation($stock['candles']);

            $stock = array_merge($stock, $this->calculateVolumeScore($relativeVolume, $volumeTrend, $accumulation));
        }
        unset($stock);

        return $stocks;
    }

    private function calculateAverageVolume(array $candles): float
    {
        if (count($candles) < self::VOLUME_PERIOD + 1) {
            return 0;
        }

        $completed = array_slice($candles, 0, -1);
        $last = array_slice($completed, -self::VOLUME_PERIOD);

        $total = array_sum(array_map(fn ($c) => (float) $c['volume'], $last));

        return round($total / self::VOLUME_PERIOD, 2);
    }

    private function calculateRelativeVolume(array $candles): array
    {
        if (count($candles) < self::VOLUME_PERIOD + 1) {
            return ['relative_volume' => 0, 'classification' => 'UNKNOWN'];
        }

        $completed = array_slice($candles, 0, -1);
        $lastCandle = end($completed);
        $averageVolume = $this->calculateAverageVolume($candles);

        if ($averageVolume <= 0) {
            return ['relative_volume' => 0, 'classification' => 'UNKNOWN'];
        }

        $relativeVolume = round((float) $lastCandle['volume'] / $averageVolume, 2);

        $classification = match (true) {
            $relativeVolume < self::RVOL_VERY_LOW => 'VERY_LOW',
            $relativeVolume < self::RVOL_NORMAL => 'NORMAL',
            $relativeVolume < self::RVOL_HIGH => 'HIGH',
            default => 'VERY_HIGH',
        };

        return [
            'relative_volume' => $relativeVolume,
            'classification' => $classification,
            'average_volume' => round($averageVolume, 2),
            'last_volume' => (float) $lastCandle['volume'],
        ];
    }

    private function detectVolumeTrend(array $candles): array
    {
        if (count($candles) < self::VOLUME_PERIOD + 1) {
            return ['trend' => 'UNKNOWN', 'slope' => 0];
        }

        $completed = array_slice($candles, 0, -1);
        $volumes = array_column(array_slice($completed, -self::VOLUME_PERIOD), 'volume');

        $count = count($volumes);
        $sumX = $sumY = $sumXY = $sumXX = 0;

        foreach ($volumes as $i => $volume) {
            $x = $i + 1;
            $y = (float) $volume;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($count * $sumXX) - ($sumX * $sumX);
        $slope = $denominator != 0 ? (($count * $sumXY) - ($sumX * $sumY)) / $denominator : 0;

        $trend = $slope > 0 ? 'UP' : ($slope < 0 ? 'DOWN' : 'SIDEWAYS');

        return ['trend' => $trend, 'slope' => round($slope, 2)];
    }

    private function detectAccumulation(array $candles): array
    {
        $neutral = ['accumulation_score' => 50, 'bullish_volume' => 0, 'bearish_volume' => 0, 'state' => 'NEUTRAL'];

        if (count($candles) < self::VOLUME_PERIOD + 1) {
            return $neutral;
        }

        $completed = array_slice($candles, 0, -1);
        $candles = array_slice($completed, -self::VOLUME_PERIOD);

        $bullish = 0;
        $bearish = 0;

        foreach ($candles as $index => $current) {
            if ($index === 0) {
                continue;
            }

            $previous = $candles[$index - 1];
            $previousVolume = (float) $previous['volume'];

            if ($previousVolume <= 0) {
                continue;
            }

            $volumeChange = ((float) $current['volume'] - $previousVolume) / $previousVolume;

            if (abs($volumeChange) < self::VOLUME_CHANGE_THRESHOLD) {
                continue;
            }

            $priceChange = (float) $current['close'] - (float) $previous['close'];

            if ($volumeChange > 0 && $priceChange > 0) {
                $bullish++;
            } elseif ($volumeChange > 0 && $priceChange < 0) {
                $bearish++;
            }
        }

        $total = $bullish + $bearish;

        if ($total === 0) {
            return $neutral;
        }

        $score = round(($bullish / $total) * 100, 2);

        $state = match (true) {
            $score >= self::ACCUMULATION_THRESHOLD => 'ACCUMULATION',
            $score <= self::DISTRIBUTION_THRESHOLD => 'DISTRIBUTION',
            default => 'NEUTRAL',
        };

        return ['accumulation_score' => $score, 'bullish_volume' => $bullish, 'bearish_volume' => $bearish, 'state' => $state];
    }

    private function calculateVolumeScore(array $relativeVolume, array $volumeTrend, array $accumulation): array
    {
        $rvol = $relativeVolume['relative_volume'];
        $rvolScore = $rvol <= 0 ? 0 : min(40, ($rvol / 2.0) * 40);

        $trendScore = match ($volumeTrend['trend']) {
            'UP' => 25,
            'SIDEWAYS' => 12,
            default => 0,
        };

        $accumulationScore = ($accumulation['accumulation_score'] / 100) * 35;

        $volumeScore = round($rvolScore + $trendScore + $accumulationScore, 2);

        return [
            'volume_score' => min(100, $volumeScore),
            'relative_volume' => $relativeVolume['relative_volume'] ?? 0,
            'relative_volume_classification' => $relativeVolume['classification'] ?? 'UNKNOWN',
            'average_volume' => $relativeVolume['average_volume'] ?? 0,
            'last_volume' => $relativeVolume['last_volume'] ?? 0,
            'volume_trend' => $volumeTrend['trend'] ?? 'UNKNOWN',
            'volume_slope' => $volumeTrend['slope'] ?? 0,
            'accumulation_score' => $accumulation['accumulation_score'] ?? 50,
            'bullish_volume' => $accumulation['bullish_volume'] ?? 0,
            'bearish_volume' => $accumulation['bearish_volume'] ?? 0,
            'accumulation_state' => $accumulation['state'] ?? 'NEUTRAL',
        ];
    }

    /** المرحلة 8: منطقة الدخول / وقف الخسارة / الأهداف */
    private function calculateEntryZone(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $atr = $this->calculateATR($stock['candles']);
            $trade = $this->calculateTradeLevels($stock, $atr);

            $stock = array_merge($stock, $atr, $trade);

            // Internal precision must not leak into the final analysis payload.
            unset($stock['_atr_raw']);
        }
        unset($stock);

        return $stocks;
    }

    private function calculateATR(array $candles): array
    {
        if (count($candles) < self::ATR_PERIOD + 1) {
            return ['atr' => 0, 'atr_percent' => 0];
        }

        $completed = array_slice($candles, 0, -1);
        $candles = array_slice($completed, -(self::ATR_PERIOD + 1));

        $trueRanges = [];

        for ($i = 1; $i < count($candles); $i++) {
            $current = $candles[$i];
            $previous = $candles[$i - 1];

            $high = (float) $current['high'];
            $low = (float) $current['low'];
            $previousClose = (float) $previous['close'];

            $trueRanges[] = max($high - $low, abs($high - $previousClose), abs($low - $previousClose));
        }

        $atr = $trueRanges[0];

        for ($i = 1; $i < count($trueRanges); $i++) {
            $atr = (($atr * (self::ATR_PERIOD - 1)) + $trueRanges[$i]) / self::ATR_PERIOD;
        }

        $lastClose = (float) end($completed)['close'];
        $atrPercent = $lastClose > 0 ? ($atr / $lastClose) * 100 : 0;

        return [
            'atr' => round($atr, 4),
            'atr_percent' => round($atrPercent, 2),
            // Keep full precision for downstream trade-level calculations.
            '_atr_raw' => $atr,
        ];
    }

    private function calculateTradeLevels(array $stock, array $atr): array
    {
        $currentPrice = (float) ($stock['last_trade_price'] ?? 0);
        $rejectionReason = null;

        // Rejection rules must NOT stop the technical analysis.
        // Keep the original reason/status, but continue calculating every
        // metric that can be calculated from the available data.
        if (($stock['trend'] ?? null) === 'DOWN') {
            $rejectionReason = 'DOWNTREND';
        }

        $supportsAll = $stock['supports'] ?? [];
        $resistances = $stock['resistances'] ?? [];

        if (empty($supportsAll) || empty($resistances)) {
            $rejectionReason ??= 'NO_SUPPORT_OR_RESISTANCE';

            return [
                'trade' => false,
                'reason' => $rejectionReason,
                'entry_distance' => null,
                'entry_low' => null,
                'entry_high' => null,
                'entry_price' => null,
                'stop_loss' => null,
                'target1' => null,
                'target2' => null,
                'risk_reward' => null,
                'support_rank' => null,
                'entry_score' => null,
                'selected_support' => null,
                'selected_resistance' => null,
            ];
        }

        $supports = [];

        foreach ($supportsAll as $support) {
            if ($support['price'] >= $currentPrice) {
                continue;
            }

            $maxDistance = max(5, $atr['atr_percent'] * 3);

            if ($support['distance_percent'] > $maxDistance) {
                continue;
            }

            $proximity = max(0, 100 - ($support['distance_percent'] * 20));
            $support['support_rank'] = ($proximity * 0.60) + ($support['strength'] * 0.40);

            $supports[] = $support;
        }

        // If no support is close enough, use the best available support for
        // analysis, but preserve ENTRY_TOO_FAR as the rejection reason.
        if (empty($supports)) {
            $rejectionReason ??= 'ENTRY_TOO_FAR';

            $fallbackSupports = [];
            foreach ($supportsAll as $support) {
                if ($support['price'] >= $currentPrice) {
                    continue;
                }

                $proximity = max(0, 100 - ((float) ($support['distance_percent'] ?? 999) * 20));
                $support['support_rank'] = ($proximity * 0.60) + ((float) ($support['strength'] ?? 0) * 0.40);
                $fallbackSupports[] = $support;
            }

            $supports = $fallbackSupports;
        }

        if (empty($supports)) {
            // There is no usable support below the current price, so there is
            // no honest way to manufacture Entry/SL values.
            return [
                'trade' => false,
                'reason' => $rejectionReason ?? 'ENTRY_TOO_FAR',
                'entry_distance' => null,
                'entry_low' => null,
                'entry_high' => null,
                'entry_price' => null,
                'stop_loss' => null,
                'target1' => null,
                'target2' => null,
                'risk_reward' => null,
                'support_rank' => null,
                'entry_score' => null,
                'selected_support' => null,
                'selected_resistance' => null,
            ];
        }

        usort($supports, fn ($a, $b) => $b['support_rank'] <=> $a['support_rank']);
        $support = $supports[0];

        $entryDistance = round(abs($currentPrice - $support['price']) / $currentPrice * 100, 2);

        $entryLow = $support['price'];
        $atrValue = (float) ($atr['_atr_raw'] ?? $atr['atr']);

        $entryHigh = $support['price'] + ($atrValue * 0.25);
        $entryPrice = ($entryLow + $entryHigh) / 2;

        $stopLoss = $support['price'] - ($atrValue * 0.75);

        $targets = [];

        foreach ($resistances as $resistance) {
            if ($resistance['price'] > $entryPrice) {
                $targets[] = $resistance['price'];
            }
        }

        if (empty($targets)) {
            $rejectionReason ??= 'NO_VALID_TARGETS';

            return [
                'trade' => false,
                'reason' => $rejectionReason,
                'entry_distance' => $entryDistance,
                'entry_low' => round($entryLow, 4),
                'entry_high' => round($entryHigh, 4),
                'entry_price' => round($entryPrice, 4),
                'stop_loss' => round($stopLoss, 4),
                'target1' => null,
                'target2' => null,
                'risk_reward' => null,
                'support_rank' => round($support['support_rank'], 2),
                'entry_score' => null,
                'selected_support' => $support,
                'selected_resistance' => null,
            ];
        }

        sort($targets);
        $target1 = $targets[0];
        $target2 = $targets[1] ?? null;

        if ($target1 <= $entryPrice) {
            $rejectionReason ??= 'INVALID_TARGET';
        }

        $risk = $entryPrice - $stopLoss;
        $reward = $target1 - $entryPrice;

        if ($risk <= 0) {
            $rejectionReason ??= 'INVALID_RISK';

            return [
                'trade' => false,
                'reason' => $rejectionReason,
                'entry_distance' => $entryDistance,
                'entry_low' => round($entryLow, 4),
                'entry_high' => round($entryHigh, 4),
                'entry_price' => round($entryPrice, 4),
                'stop_loss' => round($stopLoss, 4),
                'target1' => round($target1, 4),
                'target2' => $target2 !== null ? round($target2, 4) : null,
                'risk_reward' => null,
                'support_rank' => round($support['support_rank'], 2),
                'entry_score' => null,
                'selected_support' => $support,
                'selected_resistance' => $target1,
            ];
        }

        $riskReward = round($reward / $risk, 2);

        if ($riskReward < 1.50) {
            $rejectionReason ??= 'LOW_RISK_REWARD';
        }

        $riskRewardScore = min(15, ($riskReward / 4) * 15);

        $entryScore = round(
            ($support['strength'] * 0.35)
            + (($stock['trend_score'] ?? 0) * 0.25)
            + $riskRewardScore
            + ($support['support_rank'] * 0.10)
            + (($stock['volume_score'] ?? 0) * 0.15),
            2
        );

        return [
            'trade' => $rejectionReason === null,
            'reason' => $rejectionReason,
            'entry_distance' => $entryDistance,
            'entry_low' => round($entryLow, 4),
            'entry_high' => round($entryHigh, 4),
            'entry_price' => round($entryPrice, 4),
            'stop_loss' => round($stopLoss, 4),
            'target1' => round($target1, 4),
            'target2' => $target2 !== null ? round($target2, 4) : null,
            'risk_reward' => $riskReward,
            'support_rank' => round($support['support_rank'], 2),
            'entry_score' => min(100, round($entryScore, 2)),
            'selected_support' => $support,
            'selected_resistance' => $target1,
        ];
    }

    /** المرحلة 9: Opportunity Score + Fast Trade */
    private function calculateOpportunityScores(array $stocks): array
    {
        foreach ($stocks as &$stock) {
            $stock = array_merge($stock, $this->calculateOpportunityScore($stock));

            // Rejected stocks are still fully analyzed. Calculate speed/fast
            // diagnostics whenever the required entry metrics are available.
            if (($stock['entry_distance'] ?? null) !== null || ($stock['support_rank'] ?? null) !== null) {
                $stock['speed_score'] = $this->calculateSpeedScore($stock);
                $stock['speed_class'] = $this->getSpeedClass($stock['speed_score']);
                $stock = array_merge($stock, $this->calculateFastTrade($stock));
            }
        }
        unset($stock);

        return $stocks;
    }

    private function calculateOpportunityScore(array $stock): array
    {
        $trendScore = min(20, (($stock['trend_score'] ?? 0) / 100) * 20);
        $entryScore = min(30, (($stock['entry_score'] ?? 0) / 100) * 30);
        $supportScore = min(10, (($stock['support_rank'] ?? 0) / 100) * 10);

        $riskReward = min($stock['risk_reward'] ?? 0, 4);
        $riskRewardScore = ($riskReward / 4) * 10;

        $volumeScore = min(15, (($stock['volume_score'] ?? 0) / 100) * 15);

        $atrPercent = $stock['atr_percent'] ?? 0;
        $atrScore = match (true) {
            $atrPercent >= 2 && $atrPercent <= 6 => 5,
            $atrPercent >= 1 && $atrPercent <= 8 => 3,
            default => 1,
        };

        $distance = $stock['entry_distance'] ?? 999;
        $distanceScore = match (true) {
            $distance <= 1 => 10,
            $distance <= 2 => 8,
            $distance <= 3 => 5,
            $distance <= 5 => 3,
            default => 0,
        };

        $score = round($trendScore + $entryScore + $supportScore + $riskRewardScore + $volumeScore + $atrScore + $distanceScore, 2);

        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            default => 'D',
        };

        return ['opportunity_score' => min(100, round($score, 2)), 'opportunity_grade' => $grade];
    }

    private function calculateSpeedScore(array $stock): float
    {
        $atrPercent = (float) ($stock['atr_percent'] ?? 0);
        $entryDistance = abs((float) ($stock['entry_distance'] ?? 999));
        $relativeVolume = (float) ($stock['relative_volume'] ?? 0);
        $volumeScore = (float) ($stock['volume_score'] ?? 0);
        $trendScore = (float) ($stock['trend_score'] ?? 0);
        $volumeTrend = strtoupper((string) ($stock['volume_trend'] ?? ''));

        $atrScore = match (true) {
            $atrPercent <= 1 => 15,
            $atrPercent <= 2 => 35 + (($atrPercent - 1) * 25),
            $atrPercent <= 4 => 60 + (($atrPercent - 2) * 15),
            $atrPercent <= 6 => 90 - (($atrPercent - 4) * 5),
            default => max(45, 80 - (($atrPercent - 6) * 8)),
        };

        $distanceScore = match (true) {
            $entryDistance <= 0.5 => 100,
            $entryDistance <= 1 => 95,
            $entryDistance <= 2 => 80,
            $entryDistance <= 3 => 60,
            $entryDistance <= 5 => 35,
            $entryDistance <= 8 => 15,
            default => 0,
        };

        $rvolScore = match (true) {
            $relativeVolume <= 0 => 0,
            $relativeVolume < 0.8 => 20,
            $relativeVolume < 1.2 => 40 + (($relativeVolume - 0.8) / 0.4) * 20,
            $relativeVolume < 1.5 => 60 + (($relativeVolume - 1.2) / 0.3) * 20,
            $relativeVolume < 2.0 => 80 + (($relativeVolume - 1.5) / 0.5) * 20,
            default => 100,
        };

        $volumeMomentumScore = min(100, ($volumeScore * 0.7) + ($volumeTrend === 'UP' ? 30 : ($volumeTrend === 'SIDEWAYS' ? 15 : 0)));
        $trendMomentumScore = min(100, $trendScore);

        $score = ($atrScore * 0.20) + ($distanceScore * 0.30) + ($rvolScore * 0.25) + ($volumeMomentumScore * 0.10) + ($trendMomentumScore * 0.15);

        return round(max(0, min(100, $score)), 2);
    }

    private function getSpeedClass(float $score): string
    {
        return match (true) {
            $score >= 80 => 'VERY_FAST',
            $score >= 65 => 'FAST',
            $score >= 50 => 'MODERATE',
            default => 'SLOW',
        };
    }

    private function calculateFastTrade(array $stock): array
    {
        $entryDistance = abs((float) ($stock['entry_distance'] ?? 999));
        $relativeVolume = (float) ($stock['relative_volume'] ?? 0);
        $volumeTrend = strtoupper((string) ($stock['volume_trend'] ?? ''));
        $trend = strtoupper((string) ($stock['trend'] ?? ''));
        $trendScore = (float) ($stock['trend_score'] ?? 0);
        $speedScore = (float) ($stock['speed_score'] ?? 0);
        $riskReward = (float) ($stock['risk_reward'] ?? 0);
        $volumeScore = (float) ($stock['volume_score'] ?? 0);

        $supportBounce = $this->detectSupportBounce($stock);
        $supportBounceScore = (float) $supportBounce['support_bounce_score'];
        $supportConfirmed = (bool) $supportBounce['support_confirmed'];
        $reversalRisk = (bool) ($supportBounce['reversal_risk'] ?? false);

        $rvolScore = match (true) {
            $relativeVolume >= 2.0 => 100,
            $relativeVolume >= 1.5 => 80 + (($relativeVolume - 1.5) / 0.5) * 20,
            $relativeVolume >= 1.2 => 60 + (($relativeVolume - 1.2) / 0.3) * 20,
            $relativeVolume >= 0.8 => 40 + (($relativeVolume - 0.8) / 0.4) * 20,
            $relativeVolume > 0 => 25,
            default => 0,
        };

        $volumeTrendScore = $volumeTrend === 'UP' ? 100 : ($volumeTrend === 'SIDEWAYS' ? 55 : 20);

        $volumeQuality = min(100, ($rvolScore * 0.60) + ($volumeTrendScore * 0.20) + (min(100, max(0, $volumeScore)) * 0.20));

        $trendQuality = in_array($trend, ['STRONG_UP', 'UP'], true)
            ? max(75, min(100, $trendScore))
            : max(0, min(55, $trendScore));

        $rrScore = match (true) {
            $riskReward >= 2.5 => 100,
            $riskReward >= 2.0 => 90,
            $riskReward >= 1.5 => 75,
            $riskReward >= 1.0 => 50,
            default => 20,
        };

        $fastScore = round(
            ($supportBounceScore * 0.40)
            + ($speedScore * 0.25)
            + ($volumeQuality * 0.20)
            + ($trendQuality * 0.10)
            + ($rrScore * 0.05),
            2
        );

        $priceConfirmationFast = $supportConfirmed && $supportBounceScore >= 70;
        $priceConfirmationVeryFast = $supportConfirmed && $supportBounceScore >= 85;

        $veryFast = $priceConfirmationVeryFast && $fastScore >= 80 && $entryDistance <= 5.0 && ! $reversalRisk;

        $fastTrade = ! $veryFast && $priceConfirmationFast && $fastScore >= 65 && $entryDistance <= 3.0 && ! $reversalRisk;

        $fastWatch = ! $fastTrade && ! $veryFast && ! $reversalRisk && $fastScore >= 50 && $entryDistance <= 3.0 && $supportBounceScore >= 40;

        $class = $veryFast ? 'VERY_FAST' : ($fastTrade ? 'FAST' : ($fastWatch ? 'FAST_WATCH' : 'NOT_FAST'));

        return [
            'fast_trade' => $fastTrade || $veryFast,
            'fast_class' => $class,
            'fast_score' => $fastScore,
            'support_bounce_score' => $supportBounceScore,
            'support_bounce_signal' => $supportBounce['signal'],
            'support_confirmed' => $supportConfirmed,
            'support_bounce' => $supportBounce,
        ];
    }

    /** اكتشاف تأكيد الارتداد من الدعم من آخر 5 شموع مكتملة */
    private function detectSupportBounce(array $stock): array
    {
        $selectedSupport = $stock['selected_support'] ?? null;
        $supportPrice = is_array($selectedSupport) ? (float) ($selectedSupport['price'] ?? 0) : 0.0;

        if ($supportPrice <= 0) {
            $supportPrice = (float) ($stock['last_swing_low'] ?? 0);
        }

        $candles = $stock['candles'] ?? [];
        $count = count($candles);

        $empty = fn (float $price = 0) => [
            'support_price' => $price > 0 ? round($price, 4) : null,
            'signal' => 'NONE',
            'support_confirmed' => false,
            'support_bounce_score' => 0,
            'reversal_risk' => false,
        ];

        if ($supportPrice <= 0 || $count < 2) {
            return $empty($supportPrice);
        }

        $closeTolerance = $supportPrice * 0.001;
        $testTolerance = $supportPrice * 0.001;

        $windowStart = max(0, $count - 5);
        $window = array_slice($candles, $windowStart, 5);

        $last = $candles[$count - 1];
        $lastOpen = (float) ($last['open'] ?? 0);
        $lastHigh = (float) ($last['high'] ?? 0);
        $lastLow = (float) ($last['low'] ?? 0);
        $lastClose = (float) ($last['close'] ?? 0);

        if ($lastHigh <= 0 || $lastLow <= 0 || $lastClose <= 0) {
            return $empty($supportPrice);
        }

        $lastRange = max(0.000001, $lastHigh - $lastLow);
        $lastBody = abs($lastClose - $lastOpen);
        $lastLowerWick = max(0, min($lastOpen, $lastClose) - $lastLow);
        $lastClosePosition = ($lastClose - $lastLow) / $lastRange;
        $lastLowerWickRatio = $lastBody > 0 ? $lastLowerWick / $lastBody : 0;
        $lastBullish = $lastClose > $lastOpen;
        $lastCloseAbove = $lastClose > ($supportPrice + $closeTolerance);

        $prevLastClose = $count >= 2 ? (float) ($candles[$count - 2]['close'] ?? 0) : 0;

        $reversalRisk = $lastCloseAbove && ! $lastBullish && $lastClosePosition <= 0.35
            && $prevLastClose > 0 && $lastClose < $prevLastClose;

        $testIndices = [];
        $rejectIndices = [];

        foreach ($window as $relativeIndex => $candle) {
            $index = $windowStart + $relativeIndex;

            $open = (float) ($candle['open'] ?? 0);
            $high = (float) ($candle['high'] ?? 0);
            $low = (float) ($candle['low'] ?? 0);
            $close = (float) ($candle['close'] ?? 0);

            if ($high <= 0 || $low <= 0 || $close <= 0) {
                continue;
            }

            $range = max(0.000001, $high - $low);
            $body = abs($close - $open);
            $lowerWick = max(0, min($open, $close) - $low);
            $closePosition = ($close - $low) / $range;
            $lowerWickRatio = $body > 0 ? $lowerWick / $body : 0;
            $bullish = $close > $open;

            $tested = $low <= ($supportPrice + $testTolerance);
            $closeAbove = $close > ($supportPrice + $closeTolerance);

            if ($tested) {
                $testIndices[] = $index;
            }

            if ($tested && $closeAbove && $closePosition >= 0.60 && ($bullish || $lowerWickRatio >= 1.5)) {
                $rejectIndices[] = $index;
            }
        }

        $testedSupport = ! empty($testIndices);
        $testIndex = ! empty($testIndices) ? max($testIndices) : null;

        $twoClosesAbove = false;

        if ($testIndex !== null && $testIndex < $count - 2) {
            $c1Close = (float) ($candles[$count - 2]['close'] ?? 0);
            $c2Close = (float) ($candles[$count - 1]['close'] ?? 0);

            $twoClosesAbove = $c1Close > ($supportPrice + $closeTolerance) && $c2Close > ($supportPrice + $closeTolerance);
        }

        $lastTwoTested = ($count >= 2 && (float) ($candles[$count - 2]['low'] ?? 0) <= ($supportPrice + $testTolerance))
            || $lastLow <= ($supportPrice + $testTolerance);

        $strongBounce = $lastTwoTested && $lastCloseAbove && $lastClosePosition >= 0.70
            && ($lastBullish || $lastLowerWickRatio >= 1.5);

        $recentSupportRebound = false;

        if ($lastCloseAbove && ! empty($rejectIndices)) {
            $rejectAge = $count - 1 - max($rejectIndices);
            $recentSupportRebound = $rejectAge <= 3;
        }

        if (! $lastCloseAbove) {
            $twoClosesAbove = false;
            $strongBounce = false;
            $recentSupportRebound = false;
        }

        [$signal, $score] = match (true) {
            $twoClosesAbove => ['TWO_CLOSES_ABOVE_SUPPORT', 100],
            $strongBounce => ['STRONG_BOUNCE', 90],
            $recentSupportRebound => ['RECENT_SUPPORT_REBOUND', 80],
            $lastCloseAbove && $testedSupport => ['ONE_CLOSE_ABOVE_SUPPORT', 40],
            default => ['NONE', 0],
        };

        return [
            'support_price' => round($supportPrice, 4),
            'signal' => $signal,
            'support_confirmed' => $twoClosesAbove || $strongBounce || $recentSupportRebound,
            'support_bounce_score' => $score,
            'tested_support' => $testedSupport,
            'reversal_risk' => $reversalRisk,
            'two_closes_above_support' => $twoClosesAbove,
            'one_close_above_support' =>
                $lastCloseAbove &&
                !$twoClosesAbove &&
                !$strongBounce &&
                !$recentSupportRebound,
            'strong_bounce' => $strongBounce,
            'recent_support_rebound' => $recentSupportRebound,
            'test_candle_index' => $testIndex,
            'test_candle_age' => $testIndex !== null
                ? ($count - 1 - $testIndex)
                : null,
            'last_candle' => [
                'timestamp' => $last['timestamp'] ?? null,
                'open' => $lastOpen,
                'high' => $lastHigh,
                'low' => $lastLow,
                'close' => $lastClose,
            ],
        ];
    }

    /** المرحلة 10: تصنيف وترتيب النتائج النهائية */
    private function rankStocks(array $stocks): array
    {
        $qualified = [];
        $rejected = [];

        foreach ($stocks as $stock) {
            if (($stock['trade'] ?? false) === true) {
                $stock['status'] = 'QUALIFIED';
                $stock['reason'] = '';
                $qualified[] = $stock;
            } else {
                $stock['status'] = 'REJECTED';
                $stock['reason'] = $stock['reason'] ?? 'UNKNOWN';
                $rejected[] = $stock;
            }
        }

        usort($qualified, function ($a, $b) {
            if ($a['opportunity_score'] != $b['opportunity_score']) {
                return $b['opportunity_score'] <=> $a['opportunity_score'];
            }

            if (($a['speed_score'] ?? 0) != ($b['speed_score'] ?? 0)) {
                return ($b['speed_score'] ?? 0) <=> ($a['speed_score'] ?? 0);
            }

            if ($a['entry_score'] != $b['entry_score']) {
                return $b['entry_score'] <=> $a['entry_score'];
            }

            if ($a['trend_score'] != $b['trend_score']) {
                return $b['trend_score'] <=> $a['trend_score'];
            }

            if ($a['volume_score'] != $b['volume_score']) {
                return $b['volume_score'] <=> $a['volume_score'];
            }

            if ($a['support_rank'] != $b['support_rank']) {
                return $b['support_rank'] <=> $a['support_rank'];
            }

            return strcmp(
                $a['symbol_code'] ?? ($a['symbol'] ?? ''),
                $b['symbol_code'] ?? ($b['symbol'] ?? '')
            );
        });

        usort($rejected, fn ($a, $b) => strcmp($a['symbol'] ?? '', $b['symbol'] ?? ''));

        return array_merge($qualified, $rejected);
    }
}
