<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * يستقبل توكن Thndr الذي يلتقطه امتداد المتصفح ويخزّنه في جدول settings
 * حتى تستخدمه بقية خدمات Thndr لاحقاً.
 */
class ThndrTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // الدالة مجتمعة: يلزم سر مشترك بين الامتداد و هذا التطبيق
        $captureKey = config('services.thndr.capture_key');

        if (empty($captureKey) || ! hash_equals(
            $captureKey,
            (string) $request->header('X-Capture-Key', '')
        )) {
            return response()->json(['ok' => false, 'reason' => 'unauthorized'], 401);
        }

        // لو المستخدم عمل إيقاف للـ capture من الداشبورد نرفض التحديث
        if (setting('thndr_capture_enabled') !== '1') {
            return response()->json(['ok' => false, 'reason' => 'capture_disabled'], 409);
        }

        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'reason' => 'invalid_token'], 422);
        }

        Setting::updateOrCreate(
            ['key' => 'thndr_api_token'],
            ['type' => 'general', 'value' => $validator->validated()['token']]
        );

        Setting::updateOrCreate(
            ['key' => 'thndr_token_updated_at'],
            ['type' => 'general', 'value' => now()->toDateTimeString()]
        );

        // helper setting() بيرخّم كل المفاتيح — لازم نمسح الكاش عشان القيمة تظهر فوراً
        Cache::forget('settings');

        return response()->json(['ok' => true]);
    }
}
