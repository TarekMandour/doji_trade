<?php

namespace App\Services\Crypto;

use App\Models\CryptoAsset;
use App\Models\CryptoScanRun;
use App\Models\CryptoWatchlist;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CryptoScanService
{
    private const INTERVALS = [
        '1d' => ['binance' => '1d', 'seconds' => 86400, 'minimum' => 60],
        '1h' => ['binance' => '1h', 'seconds' => 3600, 'minimum' => 80],
        '30m' => ['binance' => '30m', 'seconds' => 1800, 'minimum' => 80],
        '15m' => ['binance' => '15m', 'seconds' => 900, 'minimum' => 80],
        '5m' => ['binance' => '5m', 'seconds' => 300, 'minimum' => 100],
        '1m' => ['binance' => '1m', 'seconds' => 60, 'minimum' => 150],
    ];

    public function __construct(
        private readonly BinanceApiService $binance,
        private readonly CryptoJsonStorageService $storage,
        private readonly CryptoIntradayAnalyzerService $analyzer,
        private readonly CryptoScanPersistenceService $persistence,
    ) {}

    public function syncSpotAssets(): int
    {
        $info = $this->binance->getSpotExchangeInfo();
        $synced = 0;
        CryptoAsset::where('quote_asset', 'USDT')->update(['status' => 'BREAK']);

        foreach ($info['symbols'] ?? [] as $item) {
            if (($item['status'] ?? null) !== 'TRADING'
                || ($item['quoteAsset'] ?? null) !== 'USDT'
                || empty($item['isSpotTradingAllowed'])) {
                continue;
            }

            CryptoAsset::updateOrCreate(
                ['symbol' => $item['symbol']],
                [
                    'base_asset' => $item['baseAsset'],
                    'quote_asset' => $item['quoteAsset'],
                    'status' => $item['status'],
                    'base_asset_precision' => $item['baseAssetPrecision'] ?? null,
                    'quote_asset_precision' => $item['quoteAssetPrecision'] ?? null,
                    'filters_json' => $item['filters'] ?? [],
                    'last_synced_at' => now(),
                ]
            );
            $synced++;
        }

        return $synced;
    }

    public function run(int $watchlistId): CryptoScanRun
    {
        // Never scan the whole exchange here: only the explicitly selected watchlist.
        $watchlist = CryptoWatchlist::with('assets')->findOrFail($watchlistId);
        $assets = $watchlist->assets->where('status', 'TRADING')->values();

        if ($assets->isEmpty()) {
            throw new RuntimeException('The selected crypto watchlist has no active Binance Spot assets.');
        }

        $uuid = (string) Str::uuid();
        $stamp = now()->format('Ymd_His');
        $sequence = CryptoScanRun::whereDate('created_at', now()->toDateString())->count() + 1;
        $filePath = $this->storage->scanFilePath($stamp, $sequence);
        $now = now();

        $settings = [
            'exchange' => 'binance_spot',
            'quote_asset' => 'USDT',
            'strategy' => 'VWAP_MACD_ORIGINAL',
            'conditions' => [
                'price_above_session_vwap',
                'macd_above_signal',
                'positive_macd_histogram',
                'volume_confirmation',
            ],
            'intervals' => array_keys(self::INTERVALS),
            'entry_timeframes' => ['1m', '5m', '15m'],
            'candle_policy' => 'only required closed candles; no shared candle cache',
            'vwap_session' => 'UTC calendar day',
        ];

        $run = CryptoScanRun::create([
            'uuid' => $uuid,
            'crypto_watchlist_id' => $watchlist->id,
            'status' => 'running',
            'settings_json' => $settings,
            'metadata_path' => $filePath,
            'results_path' => $filePath,
            'total_assets' => $assets->count(),
            'started_at' => $now,
        ]);

        $document = [
            'schema_version' => 2,
            'run' => [
                'uuid' => $uuid,
                'status' => 'running',
                'watchlist_id' => $watchlist->id,
                'watchlist_name' => $watchlist->name,
                'started_at' => $now->toIso8601String(),
                'completed_at' => null,
                'settings' => $settings,
                'total_assets' => $assets->count(),
                'processed_assets' => 0,
                'accepted_assets' => 0,
                'rejected_assets' => 0,
            ],
            'assets' => [],
            'results' => [],
        ];
        $this->storage->write($filePath, $document);

        $accepted = 0;
        $rejected = 0;

        try {
            foreach ($assets as $asset) {
                $candles = [];
                try {
                    foreach (self::INTERVALS as $interval => $config) {
                        $candles[$interval] = $this->fetchRequiredCandles(
                            $asset->symbol,
                            $interval,
                            $config
                        );
                    }

                    $result = $this->analyzer->analyze($candles);
                    $result['state'] === 'accepted' ? $accepted++ : $rejected++;
                } catch (Throwable $e) {
                    report($e);
                    $result = [
                        'state' => 'error',
                        'signal_type' => null,
                        'reason' => $e->getMessage(),
                    ];
                    $rejected++;
                }

                $result['symbol'] = $asset->symbol;
                $result['analyzed_at'] = now()->toIso8601String();

                // One complete immutable-per-run file contains both the candles used and results.
                $document['assets'][$asset->symbol] = [
                    'symbol' => $asset->symbol,
                    'candles_by_timeframe' => $candles,
                ];
                $document['results'][] = $result;

                $this->persistence->saveResult($run, $asset, $result);
                $run->increment('processed_assets');

                $document['run']['processed_assets'] = (int) $run->processed_assets;
                $document['run']['accepted_assets'] = $accepted;
                $document['run']['rejected_assets'] = $rejected;
                $document['run']['updated_at'] = now()->toIso8601String();
                $this->storage->write($filePath, $document);
            }

            $run->update([
                'status' => 'completed',
                'accepted_assets' => $accepted,
                'rejected_assets' => $rejected,
                'completed_at' => now(),
            ]);

            $document['run']['status'] = 'completed';
            $document['run']['completed_at'] = now()->toIso8601String();
            $document['run']['accepted_assets'] = $accepted;
            $document['run']['rejected_assets'] = $rejected;
            $document['data_as_of'] = now()->toIso8601String();
            $this->storage->write($filePath, $document);

            return $run->fresh();
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $document['run']['status'] = 'failed';
            $document['run']['error_message'] = $e->getMessage();
            $document['run']['completed_at'] = now()->toIso8601String();
            $this->storage->write($filePath, $document);
            throw $e;
        }
    }

    private function fetchRequiredCandles(string $symbol, string $interval, array $config): array
    {
        $now = now('UTC');
        $sessionStart = $now->copy()->startOfDay();
        $secondsSinceSessionStart = max(0, $now->timestamp - $sessionStart->timestamp);
        $sessionCandles = (int) ceil($secondsSinceSessionStart / $config['seconds']) + 1;

        // Session VWAP needs all candles from UTC midnight. Other indicators need their warm-up history.
        $required = max((int) $config['minimum'], $sessionCandles);
        $endTime = $now->getTimestamp() * 1000;
        $startTime = ($endTime - ($required * $config['seconds'] * 1000));

        $candles = [];
        $cursor = $startTime;
        while (count($candles) < $required) {
            $limit = min(1000, $required - count($candles));
            $batch = $this->binance->getKlines(
                $symbol,
                $config['binance'],
                $limit,
                $cursor,
                $endTime
            );
            if ($batch === []) {
                break;
            }

            foreach ($batch as $candle) {
                $openTime = (int) ($candle[0] ?? 0);
                if ($openTime > 0 && (!isset($candles[$openTime]))) {
                    $candles[$openTime] = $candle;
                }
            }

            $last = end($batch);
            $lastOpen = (int) ($last[0] ?? 0);
            if ($lastOpen <= 0) {
                break;
            }
            $cursor = $lastOpen + ($config['seconds'] * 1000);
            if ($cursor >= $endTime) {
                break;
            }
        }

        ksort($candles);
        $candles = array_values($candles);

        // Exclude the still-open candle; the strategy must only use closed bars.
        $nowMs = $now->getTimestamp() * 1000;
        $candles = array_values(array_filter($candles, static fn ($c) => isset($c[6]) && (int) $c[6] < $nowMs));

        if (count($candles) < (int) $config['minimum']) {
            throw new RuntimeException("Insufficient closed candle history for {$symbol} {$interval}: need {$config['minimum']}, received " . count($candles) . '.');
        }

        return $candles;
    }
}
