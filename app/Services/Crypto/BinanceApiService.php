<?php

namespace App\Services\Crypto;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BinanceApiService
{
    private const BASE_URL = 'https://api.binance.com';

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(8)
            ->retry(2, 300, throw: false);
    }

    private function get(string $endpoint, array $query = []): array
    {
        $response = $this->client()->get($endpoint, $query);

        if (!$response->successful()) {
            throw new RuntimeException('Binance API error (' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('Binance returned an invalid JSON response.');
        }

        return $data;
    }

    public function getSpotExchangeInfo(): array
    {
        return $this->get('/api/v3/exchangeInfo');
    }

    public function get24HourTicker(string $symbol): array
    {
        return $this->get('/api/v3/ticker/24hr', ['symbol' => strtoupper($symbol)]);
    }

    public function getKlines(string $symbol, string $interval, int $limit = 500, ?int $startTime = null, ?int $endTime = null): array
    {
        $query = ['symbol' => strtoupper($symbol), 'interval' => $interval, 'limit' => min(1000, max(1, $limit))];
        if ($startTime !== null) $query['startTime'] = $startTime;
        if ($endTime !== null) $query['endTime'] = $endTime;

        return $this->get('/api/v3/klines', $query);
    }

    public function getAggregateTrades(string $symbol, ?int $fromId = null, ?int $startTime = null, ?int $endTime = null, int $limit = 1000): array
    {
        $query = ['symbol' => strtoupper($symbol), 'limit' => min(1000, max(1, $limit))];
        if ($fromId !== null) {
            $query['fromId'] = $fromId;
        } else {
            if ($startTime !== null) $query['startTime'] = $startTime;
            if ($endTime !== null) $query['endTime'] = $endTime;
        }

        return $this->get('/api/v3/aggTrades', $query);
    }
}
