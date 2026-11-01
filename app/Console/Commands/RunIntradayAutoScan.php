<?php

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\Setting;
use App\Models\Watchlist;
use App\Services\Thndr\IntradayMultiTimeframeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunIntradayAutoScan extends Command
{
    protected $signature = 'intraday:auto-scan';

    protected $description = 'Run one due step of the automatic intraday scan';

    public function handle(IntradayMultiTimeframeService $service): int
    {
        if (setting('intraday_auto_scan_enabled') !== '1') {
            return self::SUCCESS;
        }

        $nextAt = setting('intraday_auto_scan_next_at');

        if (empty($nextAt) || Carbon::parse($nextAt)->isFuture()) {
            return self::SUCCESS;
        }

        $lock = Cache::lock('intraday-auto-scan-running', 7200);

        if (! $lock->get()) {
            $this->warn('An automatic intraday scan is already running.');
            return self::SUCCESS;
        }

        $shouldRotate = false;

        try {
            if (setting('intraday_auto_scan_enabled') !== '1') {
                return self::SUCCESS;
            }

            $watchlistName = trim((string) setting('intraday_auto_scan_next_watchlist', 'egx30'));

            // Match the exact database names: egx30 and egx70 (case-insensitive).
            $watchlist = Watchlist::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($watchlistName)])
                ->first();

            if (! $watchlist) {
                throw new \RuntimeException(
                    "Watchlist '{$watchlistName}' was not found. Expected database names: egx30 and egx70."
                );
            }

            $assetIds = $watchlist->stocks()
                ->get()
                ->pluck('asset_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();

            if ($assetIds === []) {
                throw new \RuntimeException(
                    "Watchlist '{$watchlist->name}' has no linked stocks with an asset_id."
                );
            }

            // Do not change the existing strategy or indicator calculations.
            $results = $service->analyzeAssetIds($assetIds);

            $filePath = 'analysis/'.now()->format('Ymd_His').'_auto_'.$watchlist->id.'.json';

            Storage::disk('local')->put(
                $filePath,
                json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );

            Analysis::create([
                'title' => 'Auto Scan - '.$watchlist->name.' - Intraday',
                'type' => 'intraday',
                'watchlist_id' => $watchlist->id,
                'analysis_date' => now()->toDateString(),
                'file_path' => $filePath,
                'stocks_count' => count($results),
            ]);

            Log::info('Automatic intraday scan completed', [
                'watchlist_id' => $watchlist->id,
                'watchlist_name' => $watchlist->name,
                'stocks_count' => count($results),
            ]);

            $this->info("Automatic intraday scan completed for {$watchlist->name}.");
            $shouldRotate = true;
        } catch (Throwable $e) {
            Log::error('Automatic intraday scan failed', [
                'watchlist' => setting('intraday_auto_scan_next_watchlist'),
                'message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());
            // Rotate after a failed attempt too, so a bad list doesn't block the other list.
            $shouldRotate = true;
        } finally {
            // If stopped while the scan was running, do not schedule another scan.
            if ($shouldRotate && setting('intraday_auto_scan_enabled') === '1') {
                $current = mb_strtolower(trim((string) setting('intraday_auto_scan_next_watchlist', 'egx30')));
                $next = $current === 'egx30' ? 'egx70' : 'egx30';

                Setting::updateOrCreate(
                    ['key' => 'intraday_auto_scan_next_watchlist'],
                    ['type' => 'general', 'value' => $next]
                );

                Setting::updateOrCreate(
                    ['key' => 'intraday_auto_scan_next_at'],
                    ['type' => 'general', 'value' => now()->addMinutes(10)->toDateTimeString()]
                );

                Cache::forget('settings');
            }

            $lock->release();
        }

        return self::SUCCESS;
    }
}
