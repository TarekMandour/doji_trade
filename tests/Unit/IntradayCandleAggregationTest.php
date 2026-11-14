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

    public function test_it_rejects_only_the_incomplete_fifteen_minute_bucket_and_keeps_later_buckets(): void
    {
        $candles = [
            $this->candle('10:00', 10, 11, 9, 10.5, 100),
            // 10:05 is missing, so only the 10:00-10:15 bucket is rejected.
            $this->candle('10:10', 10.5, 12, 8.5, 11, 200),
            $this->candle('10:15', 11, 12, 10, 11.5, 100),
            $this->candle('10:20', 11.5, 12.5, 11, 12, 100),
            $this->candle('10:25', 12, 13, 11.5, 12.5, 100),
        ];

        $result = $this->aggregate($candles, '15m');

        $this->assertCount(1, $result);
        $this->assertSame($candles[2]['timestamp'], $result[0]['timestamp']);
        $this->assertSame(11.0, $result[0]['open']);
        $this->assertSame(12.5, $result[0]['high']);
        $this->assertSame(10.0, $result[0]['low']);
        $this->assertSame(12.5, $result[0]['close']);
        $this->assertSame(300.0, $result[0]['volume']);
    }

    public function test_it_rejects_only_the_incomplete_thirty_minute_bucket_and_keeps_later_buckets(): void
    {
        $candles = [
            $this->candle('10:00', 10, 11, 9, 10.5, 100),
            $this->candle('10:05', 10.5, 11, 10, 10.8, 100),
            // 10:10 is missing, so only the 10:00-10:30 bucket is rejected.
            $this->candle('10:15', 10.8, 12, 10.5, 11.5, 100),
            $this->candle('10:20', 11.5, 12, 11, 11.8, 100),
            $this->candle('10:25', 11.8, 12.5, 11.5, 12, 100),
            $this->candle('10:30', 12, 13, 11.8, 12.5, 100),
            $this->candle('10:35', 12.5, 13.5, 12, 13, 100),
            $this->candle('10:40', 13, 14, 12.5, 13.5, 100),
            $this->candle('10:45', 13.5, 14.5, 13, 14, 100),
            $this->candle('10:50', 14, 15, 13.5, 14.5, 100),
            $this->candle('10:55', 14.5, 15.5, 14, 15, 100),
        ];

        $result = $this->aggregate($candles, '30m');

        $this->assertCount(1, $result);
        $this->assertSame($candles[6]['timestamp'], $result[0]['timestamp']);
        $this->assertSame(12.0, $result[0]['open']);
        $this->assertSame(15.5, $result[0]['high']);
        $this->assertSame(11.8, $result[0]['low']);
        $this->assertSame(15.0, $result[0]['close']);
        $this->assertSame(600.0, $result[0]['volume']);
    }

    public function test_it_anchors_buckets_to_cairo_local_session_open_during_summer_time(): void
    {
        $candles = [
            $this->candle('10:00', 10, 11, 9, 10.5, 100, '2026-04-27'),
            $this->candle('10:05', 10.5, 11, 10, 10.8, 100, '2026-04-27'),
            $this->candle('10:10', 10.8, 12, 10.5, 11.5, 100, '2026-04-27'),
        ];

        $result = $this->aggregate($candles, '15m');

        $this->assertCount(1, $result);
        $this->assertSame($candles[0]['timestamp'], $result[0]['timestamp']);
        $this->assertSame('07:00', Carbon::createFromTimestampUTC($result[0]['timestamp'])->format('H:i'));
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
        float $volume,
        string $date = '2026-11-13'
    ): array {
        $timestamp = Carbon::parse($date.' '.$time, self::MARKET_TZ)->timestamp;

        return compact('timestamp', 'open', 'high', 'low', 'close', 'volume');
    }
}
