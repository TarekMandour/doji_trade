<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class IntradayAutoScanController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'enabled' => setting('intraday_auto_scan_enabled') === '1',
            'nextWatchlist' => setting('intraday_auto_scan_next_watchlist', 'egx30'),
            'nextAt' => setting('intraday_auto_scan_next_at'),
            'intervalMinutes' => 10,
            'resolution' => '1D',
            'candlesCount' => 180,
        ]);
    }

    public function start(): JsonResponse
    {
        $missing = collect(['egx30', 'egx70'])->filter(function (string $name): bool {
            return ! Watchlist::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
                ->exists();
        })->values();

        if ($missing->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'لم يتم العثور على مجموعات المراقبة: '.$missing->implode('، '),
            ], 422);
        }

        $this->saveSetting('intraday_auto_scan_next_watchlist', 'egx30');
        $this->saveSetting('intraday_auto_scan_next_at', now()->toDateTimeString());
        $this->saveSetting('intraday_auto_scan_enabled', '1');

        return $this->status();
    }

    public function stop(): JsonResponse
    {
        $this->saveSetting('intraday_auto_scan_enabled', '0');

        return $this->status();
    }

    private function saveSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['type' => 'general', 'value' => $value]
        );

        Cache::forget('settings');
    }
}
