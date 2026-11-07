<?php

namespace App\Jobs\Crypto;

use App\Models\CryptoAsset;
use App\Services\Crypto\BinanceApiService;
use App\Services\Crypto\SwingEligibilityJsonStorage;
use App\Services\Crypto\SwingMarketStructureAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSwingEligibleAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public readonly string $syncId) {}

    public function handle(
        BinanceApiService $api,
        SwingMarketStructureAnalyzer $analyzer,
        SwingEligibilityJsonStorage $storage
    ): void {
        $progress = $storage->readProgress($this->syncId);
        $progress = array_merge($progress, [
            'status' => 'running',
            'stage' => 'Loading Binance Spot assets',
            'progress' => 1,
            'started_at' => now()->toIso8601String(),
        ]);
        $storage->writeProgress($progress);

        try {
            $symbols = collect($api->getSpotExchangeInfo()['symbols'] ?? [])
                ->filter(fn ($item) =>
                    ($item['status'] ?? null) === 'TRADING'
                    && ($item['quoteAsset'] ?? null) === 'USDT'
                    && !empty($item['isSpotTradingAllowed'])
                )->values();

            $assets = [];
            foreach ($symbols as $item) {
                $assets[] = CryptoAsset::updateOrCreate(
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
            }

            $progress['initial_assets'] = count($assets);
            $progress['stage'] = 'Filtering 24h quote volume';
            $progress['progress'] = 5;
            $storage->writeProgress($progress);

            $tickers = collect($api->getAll24HourTickers())->keyBy('symbol');
            $qualified = [];

            foreach ($assets as $asset) {
                $volume = (float) ($tickers->get($asset->symbol)['quoteVolume'] ?? 0);
                if ($volume > 5_000_000) {
                    $qualified[] = ['asset' => $asset, 'volume' => $volume];
                }
            }

            $total = count($qualified);
            $progress['volume_qualified'] = $total;
            $progress['stage'] = 'Analyzing Swing market structure';
            $progress['progress'] = 10;
            $progress['analyzed_assets'] = 0;
            $progress['analysis_errors'] = 0;
            $progress['eligible_assets'] = 0;
            $storage->writeProgress($progress);

            $eligible = [];
            $analyzedSuccessfully = 0;
            $analysisErrors = 0;
            $timeframePassCounts = [
                '4h' => 0,
                '1h' => 0,
                '15m' => 0,
                '5m' => 0,
            ];

            foreach ($qualified as $index => $row) {
                $asset = $row['asset'];

                try {
                    $candlesByTimeframe = [];

                    foreach (['4h', '1h', '15m', '5m'] as $timeframe) {
                        $rawCandles = $api->getKlines($asset->symbol, $timeframe, 150);
                        $nowMs = (int) floor(microtime(true) * 1000);

                        $candlesByTimeframe[$timeframe] = array_values(array_filter(
                            $rawCandles,
                            fn ($candle) => isset($candle[6]) && (int) $candle[6] < $nowMs
                        ));
                    }

                    $result = $analyzer->analyze($candlesByTimeframe);
                    $analyzedSuccessfully++;

                    foreach (array_keys($timeframePassCounts) as $timeframe) {
                        if (!empty($result['timeframes'][$timeframe]['passed'])) {
                            $timeframePassCounts[$timeframe]++;
                        }
                    }

                    if (!empty($result['eligible'])) {
                        $eligible[] = [
                            'symbol' => $asset->symbol,
                            'asset_id' => $asset->id,
                            'volume_24h_usdt' => $row['volume'],
                            'timeframes' => [
                                '4h' => (bool) ($result['timeframes']['4h']['passed'] ?? false),
                                '1h' => (bool) ($result['timeframes']['1h']['passed'] ?? false),
                                '15m' => (bool) ($result['timeframes']['15m']['passed'] ?? false),
                                '5m' => (bool) ($result['timeframes']['5m']['passed'] ?? false),
                            ],
                        ];
                    }
                } catch (Throwable $error) {
                    // Keep rejection details out of storage; only count technical errors.
                    $analysisErrors++;
                    report($error);
                }

                $done = $index + 1;
                $progress['analyzed_assets'] = $analyzedSuccessfully;
                $progress['analysis_errors'] = $analysisErrors;
                $progress['eligible_assets'] = count($eligible);
                $progress['progress'] = min(95, 10 + (int) floor(($done / max(1, $total)) * 85));
                $progress['stage'] = "Analyzing {$asset->symbol} ({$done}/{$total})";
                $storage->writeProgress($progress);
            }

            // Replace the previous eligible snapshot only after the entire job completes.
            $storage->replaceAssets([
                'last_sync_at' => now()->toIso8601String(),
                'status' => 'completed',
                'summary' => [
                    'initial_assets' => count($assets),
                    'volume_qualified' => $total,
                    'analyzed' => $analyzedSuccessfully,
                    'analysis_errors' => $analysisErrors,
                    'timeframe_pass_counts' => $timeframePassCounts,
                    'eligible' => count($eligible),
                ],
                'eligible_assets' => $eligible,
            ]);

            $progress['status'] = 'completed';
            $progress['progress'] = 100;
            $progress['stage'] = 'Completed';
            $progress['analyzed_assets'] = $analyzedSuccessfully;
            $progress['analysis_errors'] = $analysisErrors;
            $progress['eligible_assets'] = count($eligible);
            $progress['completed_at'] = now()->toIso8601String();
            $storage->writeProgress($progress);
        } catch (Throwable $error) {
            $progress['status'] = 'failed';
            $progress['stage'] = 'Failed';
            $progress['error_message'] = $error->getMessage();
            $progress['completed_at'] = now()->toIso8601String();
            $storage->writeProgress($progress);
            report($error);
            throw $error;
        }
    }
}
