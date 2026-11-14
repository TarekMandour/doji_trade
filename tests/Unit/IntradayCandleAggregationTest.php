<?php

namespace Tests\Unit;

use App\Services\Thndr\IntradayMultiTimeframeService;
use App\Services\Thndr\ThndrApi;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IntradayCandleAggregationTest extends TestCase
{
    private const MARKET_TZ = 'Africa/Cairo';

    public function test_it_aggregates_three_five_minute_candles_from_the_cairo_session_open_into_fifteen_minutes(): void
    {
        $candles = [
            $this->candle('10:00', 10.00, 11.00, 9.00, 10.50, 100),
            $this->candle('10:05', 10.50, 12.00, 8.50, 11.00, 200),
            $this->candle('10:10', 11.00, 11.50, 9.50, 11.20, 300),
        ];

        $result = $this->aggregate($candles, '15m');

        $this->assertCount(1, $result);
        $this->assertSame(10.00, $result[0]['open']);
        $this->assertSame(12.00, $result[0]['high']);
        $this->assertSame(8.50, $result[0]['low']);
        $this->assertSame(11.20, $result[0]['close']);
        $this->assertSame(600.0, $result[0]['volume']);
        $this->assertSame($candles[0]['timestamp'], $result[0]['timestamp']);
    }

    public function test_it_requires_all_six_consecutive_five_minute_candles_for_thirty_minutes(): void
    {
        $candles = [];
        for ($minute = 0; $minute < 30; $minute += 5) {
            $candles[] = $this->candle(
                sprintf('10:%02d', $minute),
                10.0 + $minute,
                11.0 + $minute,
                9.0 + $minute,
                10.5 + $minute,
                100
            );
        }

        $result = $this->aggregate($candles, '30m');

        $this->assertCount(1, $result);
        $this->assertSame(10.0, $result[0]['open']);
        $this->assertSame(36.0, $result[0]['high']);
        $this->assertSame(9.0, $result[0]['low']);
        $this->assertSame(35.5, $result[0]['close']);
        $this->assertSame(600.0, $result[0]['volume']);
    }

    public function test_it_rejects_incomplete_or_non_contiguous_timeframe_groups(): void
    {
        $candles = [
            $this->candle('10:00', 10.0, 11.0, 9.0, 10.5, 100),
            // The 10:05 candle is missing.
            $this->candle('10:10', 10.5, 12.0, 8.5, 11.0, 200),
        ];

        $this->assertSame([], $this->aggregate($candles, '15m'));
    }

    public function test_it_anchors_fifteen_minute_buckets_to_ten_am_cairo_not_utc_epoch_boundaries(): void
    {
        $candles = [
            $this->candle('10:00', 10.0, 11.0, 9.0, 10.5, 100),
            $this->candle('10:05', 10.5, 11.0, 10.0, 10.7, 100),
            $this->candle('10:10', 10.7, 11.5, 10.5, 11.2, 100),
            $this->candle('10:15', 11.2, 12.0, 11.0, 11.8, 100),
            $this->candle('10:20', 11.8, 12.2, 11.5, 12.0, 100),
            $this->candle('10:25', 12.0, 12.5, 11.8, 12.3, 100),
        ];

        $result = $this->aggregate($candles, '15m');

        $this->assertCount(2, $result);
        $this->assertSame($candles[0]['timestamp'], $result[0]['timestamp']);
        $this->assertSame($candles[3]['timestamp'], $result[1]['timestamp']);
    }

    private function aggregate(array $candles, string $timeframe): array
    {
        $service = new IntradayMultiTimeframeService(new ThndrApi('test-token'));
        $method = new ReflectionMethod($service, 'aggregateCandles');

        return $method->invoke($service, $candles, $timeframe);
    }

    private function candle(
        string $time,
        float $open,
        float $high,
        float $low,
        float $close,
        float $volume
    ): array {
        $timestamp = Carbon::parse('2026-11-13 '.$time, self::MARKET_TZ)->timestamp;

        return compact('timestamp', 'open', 'high', 'low', 'close', 'volume');
    }
}
