<?php

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\Setting;
use App\Models\Watchlist;
use App\Services\Thndr\EmaBreakoutScalpingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunScalpingAutoScan extends Command
{
    protected $signature = 'scalping:auto-scan';

    protected $description = 'Run one due step of the automatic EMA Breakout scalping scan';

    public function handle(EmaBreakoutScalpingService $service): int
    {
        if (setting('scalping_auto_scan_enabled') !== '1') {
            return self::SUCCESS;
        }

        $nextAt = setting('scalping_auto_scan_next_at');

        if (empty($nextAt) || Carbon::parse($nextAt)->isFuture()) {
            return self::SUCCESS;
        }

        // Separate lock: Scalping can run while the independent Intraday scan is active.
        $lock = Cache::lock('scalping-auto-scan-running', 7200);

        if (! $lock->get()) {
            $this->warn('An automatic scalping scan is already running.');
            return self::SUCCESS;
        }

        $shouldRotate = false;

        try {
            // Re-check after acquiring the lock in case the user stopped it meanwhile.
            if (setting('scalping_auto_scan_enabled') !== '1') {
                return self::SUCCESS;
            }

            $watchlistName = trim((string) setting('scalping_auto_scan_next_watchlist', 'egx30'));

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

            // Dedicated Scalping strategy service; do not call the Intraday service.
            $results = $service->analyzeAssetIds($assetIds);

            $filePath = 'analysis/'.now()->format('Ymd_His').'_auto_scalping_'.$watchlist->id.'.json';

            Storage::disk('local')->put(
                $filePath,
                json_encode(
                    $results,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
            );

            Analysis::create([
                'title' => 'Auto Scan - '.$watchlist->name.' - EMA Breakout Scalping',
                'type' => 'scalping',
                'watchlist_id' => $watchlist->id,
                'analysis_date' => now()->toDateString(),
                'file_path' => $filePath,
                'stocks_count' => count($results),
            ]);

            Log::info('Automatic scalping scan completed', [
                'watchlist_id' => $watchlist->id,
                'watchlist_name' => $watchlist->name,
                'stocks_count' => count($results),
            ]);

            $this->info("Automatic scalping scan completed for {$watchlist->name}.");
            $shouldRotate = true;
        } catch (Throwable $e) {
            Log::error('Automatic scalping scan failed', [
                'watchlist' => setting('scalping_auto_scan_next_watchlist'),
                'message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            // Rotate after a failed attempt too, so one broken list does not block the other.
            $shouldRotate = true;
        } finally {
            // Do not schedule another run if the user stopped the scanner while it was running.
            if ($shouldRotate && setting('scalping_auto_scan_enabled') === '1') {
                $current = mb_strtolower(trim((string) setting('scalping_auto_scan_next_watchlist', 'egx30')));
                $next = $current === 'egx30' ? 'egx70' : 'egx30';

                Setting::updateOrCreate(
                    ['key' => 'scalping_auto_scan_next_watchlist'],
                    ['type' => 'general', 'value' => $next]
                );

                Setting::updateOrCreate(
                    ['key' => 'scalping_auto_scan_next_at'],
                    ['type' => 'general', 'value' => now()->addMinutes(5)->toDateTimeString()]
                );

                Cache::forget('settings');
            }

            $lock->release();
        }

        return self::SUCCESS;
    }
}
