<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'captureEnabled' => setting('thndr_capture_enabled') === '1',
            'tokenMasked' => $this->maskToken(setting('thndr_api_token')),
            'tokenUpdatedAt' => setting('thndr_token_updated_at'),
        ]);
    }

    /** حالة الالتقاط والـ token بصيغة JSON — لتحديث الداشبورد بدون إعادة تحميل */
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'enabled' => setting('thndr_capture_enabled') === '1',
            'tokenMasked' => $this->maskToken(setting('thndr_api_token')),
            'tokenUpdatedAt' => setting('thndr_token_updated_at'),
        ]);
    }

    public function captureStart(): JsonResponse
    {
        return $this->setCaptureEnabled(true);
    }

    public function captureStop(): JsonResponse
    {
        return $this->setCaptureEnabled(false);
    }

    private function setCaptureEnabled(bool $enabled): JsonResponse
    {
        Setting::updateOrCreate(
            ['key' => 'thndr_capture_enabled'],
            ['type' => 'general', 'value' => $enabled ? '1' : '0']
        );

        Cache::forget('settings');

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    private function maskToken(?string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        return '••••••'.substr($token, -6);
    }
}
