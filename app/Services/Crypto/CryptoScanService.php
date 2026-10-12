<?php

namespace App\Services\Crypto;

use App\Models\CryptoAsset;
use App\Models\CryptoScanRun;
use App\Models\CryptoWatchlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CryptoScanService
{
    public function __construct(
        private readonly BinanceApiService $binance,
        private readonly CryptoJsonStorageService $storage,
        private readonly CryptoVolumeProfileService $volumeProfiles,
        private readonly CryptoIntradayAnalyzerService $analyzer,
        private readonly CryptoScanPersistenceService $persistence,
    ) {}

    public function syncSpotAssets(): int
    {
        $info = $this->binance->getSpotExchangeInfo();
        $synced = 0;

        // Mark previously known pairs unavailable until confirmed by the latest exchange snapshot.
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
        $watchlist = CryptoWatchlist::with('assets')->findOrFail($watchlistId);
        if ($watchlist->assets->isEmpty()) {
            throw new RuntimeException('The selected crypto watchlist is empty.');
        }

        $uuid = (string) Str::uuid();
        $directory = $this->storage->scanDirectory($uuid);
        $now = now();
        $run = CryptoScanRun::create([
            'uuid' => $uuid,
            'crypto_watchlist_id' => $watchlist->id,
            'status' => 'running',
            'settings_json' => ['exchange' => 'binance_spot', 'intervals' => ['1h', '4h', '1d'], 'volume_profile_hours' => 4],
            'metadata_path' => $directory . '/metadata.json',
            'results_path' => $directory . '/results.json',
            'total_assets' => $watchlist->assets->count(),
            'started_at' => $now,
        ]);

        $this->storage->write($directory . '/metadata.json', [
            'schema_version' => 1, 'run_uuid' => $uuid, 'status' => 'running',
            'watchlist_id' => $watchlist->id, 'watchlist_name' => $watchlist->name,
            'started_at' => $now->toIso8601String(), 'settings' => $run->settings_json,
        ]);

        $allResults = [];
        $accepted = 0;
        $rejected = 0;

        try {
            foreach ($watchlist->assets as $asset) {
                try {
                    $candles = [];
                    foreach (['1h' => '1h', '4h' => '4h', '1d' => '1d'] as $interval => $binanceInterval) {
                        $candles[$interval] = $this->getCandles($asset->symbol, $interval, $binanceInterval);
                    }

                    $toMs = (int) floor(microtime(true) * 1000);
                    $fromMs = $toMs - 4 * 60 * 60 * 1000;
                    $tradeWindow = $this->get24HourTrades($asset->symbol, $fromMs, $toMs);
                    $profile = $this->volumeProfiles->build($tradeWindow['trades'], $asset->symbol, $fromMs, $toMs, $tradeWindow['complete']);
                    $profilePath = $directory . '/volume_profiles/' . $asset->symbol . '.json';
                    $this->storage->write($profilePath, $profile);

                    $result = $this->analyzer->analyze($candles, $profile);
                    $result['symbol'] = $asset->symbol;
                    $result['volume_profile_path'] = $profilePath;
                    $result['analyzed_at'] = now()->toIso8601String();
                    $resultPath = $directory . '/results/' . $asset->symbol . '.json';
                    $this->storage->write($resultPath, $result);
                    $this->persistence->saveResult($run, $asset, $result, $resultPath);
                    $allResults[] = $result;
                    $result['state'] === 'accepted' ? $accepted++ : $rejected++;
                } catch (Throwable $e) {
                    $result = ['symbol' => $asset->symbol, 'state' => 'error', 'signal_type' => null, 'reason' => $e->getMessage()];
                    $this->persistence->saveResult($run, $asset, $result);
                    $allResults[] = $result;
                    $rejected++;
                }

                $run->increment('processed_assets');
            }

            $run->update([
                'status' => 'completed', 'accepted_assets' => $accepted,
                'rejected_assets' => $rejected, 'completed_at' => now(),
            ]);
            $allResults = array_values($allResults);
            $this->storage->write($directory . '/results.json', [
                'schema_version' => 1, 'run_uuid' => $uuid,
                'data_as_of' => now()->toIso8601String(), 'results' => $allResults,
            ]);
            $this->storage->write($directory . '/metadata.json', [
                'schema_version' => 1, 'run_uuid' => $uuid, 'status' => 'completed',
                'watchlist_id' => $watchlist->id, 'watchlist_name' => $watchlist->name,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'total_assets' => $run->total_assets, 'processed_assets' => $run->processed_assets,
                'accepted_assets' => $accepted, 'rejected_assets' => $rejected,
                'settings' => $run->settings_json,
            ]);

            return $run->fresh();
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            $this->storage->write($directory . '/metadata.json', [
                'schema_version' => 1, 'run_uuid' => $uuid, 'status' => 'failed',
                'error_message' => $e->getMessage(), 'completed_at' => now()->toIso8601String(),
            ]);
            throw $e;
        }
    }

    private function getCandles(string $symbol, string $interval, string $binanceInterval): array
    {
        $date = now()->toDateString();
        $path = $this->storage->candlePath($symbol, $interval, $date);
        $cached = $this->storage->read($path);
        if ($cached && !empty($cached['last_updated_at'])
            && \\Illuminate\\Support\\Carbon::parse($cached['last_updated_at'])->gt(now()->subMinutes(5))
            && count($cached['candles'] ?? []) >= 60) {
            return $cached['candles'];
        }

        $candles = $this->binance->getKlines($symbol, $binanceInterval, 1000);
        if (count($candles) < 60) {
            throw new RuntimeException("Insufficient candle history for {$symbol} {$interval}.");
        }

        $this->storage->write($path, [
            'schema_version' => 1, 'symbol' => $symbol, 'interval' => $interval,
            'data_as_of' => now()->toIso8601String(), 'last_updated_at' => now()->toIso8601String(),
            'candles' => $candles,
        ]);

        return $candles;
    }

    private function get24HourTrades(string $symbol, int $fromMs, int $toMs): array
    {
        // Binance aggTrades returns at most 1,000 rows per request. Split busy time
        // windows recursively instead of walking a fixed number of ID pages.
        $trades = $this->fetchTradeWindow($symbol, $fromMs, $toMs, 0);

        if (!$trades) {
            throw new RuntimeException("No aggregate trades returned for {$symbol}.");
        }

        // Deduplicate by aggregate trade ID in case an exchange boundary is repeated.
        $unique = [];
        foreach ($trades as $trade) {
            if (isset($trade['a'], $trade['T']) && (int) $trade['T'] >= $fromMs && (int) $trade['T'] <= $toMs) {
                $unique[(string) $trade['a']] = $trade;
            }
        }

        if (!$unique) {
            throw new RuntimeException("No aggregate trades in the requested 24-hour window for {$symbol}.");
        }

        return ['trades' => array_values($unique), 'complete' => true];
    }

    /**
     * Fetch every aggregate trade in a time window. A full 1,000-row response
     * may be truncated, so divide that window until each segment fits in one page.
     */
    private function fetchTradeWindow(string $symbol, int $fromMs, int $toMs, int $depth): array
    {
        $rows = $this->binance->getAggregateTrades($symbol, null, $fromMs, $toMs, 1000);

        if (count($rows) < 1000) {
            return $rows;
        }

        if ($depth >= 24 || $fromMs >= $toMs) {
            throw new RuntimeException("Could not safely partition Binance aggregate trades for {$symbol}; retry the scan later.");
        }

        $middle = intdiv($fromMs + $toMs, 2);
        $left = $this->fetchTradeWindow($symbol, $fromMs, $middle, $depth + 1);
        $right = $this->fetchTradeWindow($symbol, $middle + 1, $toMs, $depth + 1);

        return array_merge($left, $right);
    }
}
