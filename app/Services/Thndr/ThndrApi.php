<?php

namespace App\Services\Thndr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * عميل Thndr API — كل الطلبات هنا تمرر التوكن من الجلسة فقط ولا يُحفظ في DB.
 */
class ThndrApi
{
    protected string $token;

    public function __construct(?string $token = null)
    {
        // لو مفيش توكن متبعت، نستخدم المتخزّن في settings (استقبله الامتداد من المتصفح)
        $this->token = $token ?? (string) setting('thndr_api_token', '');
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /** قائمة السوق الكاملة (marketwatch) */
    public function fetchMarketWatch(): ?array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'StockSignalEG/2.0',
                'Authorization' => 'Bearer '.$this->token,
            ])->timeout(15)->get('https://prod.thndr.app/assets-service/assets/marketwatch?market=egypt');

        if ($response->failed()) {
            Log::warning('Thndr marketwatch failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return null;
        }

        return $response->json();
    }

    /** بيانات سهم واحد (asset details + feed) */
    public function fetchAsset(string $assetId): ?array
    {
        $url = str_replace('{id}', $assetId, 'https://prod.thndr.app/assets-service/assets/{id}?include_yearly_return=true&include_feed=true&feed_detail=true');

        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'StockSignalEG/2.0',
                'Authorization' => 'Bearer '.$this->token,
            ])->timeout(15)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /** شموع سهم واحد على فريم معين ضمن النطاق الزمني */
    public function fetchCandles(string $assetId, string $resolution, int $startTs, int $endTs): ?array
    {
        $res = config("thndr.resolutions.{$resolution}", $resolution);

        $url = str_replace(
            ['{id}', '{resolution}', '{start}', '{end}'],
            [$assetId, $res, $startTs, $endTs],
            'https://prod.thndr.app/krakend-thndr-x/feed/advanced-charts/v2/{id}/trades?resolution={resolution}&start_timestamp={start}&end_timestamp={end}'
        );

        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'StockSignalEG/2.0',
                    'Authorization' => 'Bearer '.$this->token,
                ])
                ->timeout(20)
                ->get($url);

            if ($response->failed()) {
                Log::warning('Thndr candles request failed', [
                    'asset_id' => $assetId,
                    'resolution' => $resolution,
                    'resolved_resolution' => $res,
                    'start' => $startTs,
                    'end' => $endTs,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 1000),
                    'url' => $url,
                ]);

                return null;
            }

            $json = $response->json();

            if (!is_array($json)) {
                Log::warning('Thndr candles returned invalid JSON payload', [
                    'asset_id' => $assetId,
                    'resolution' => $resolution,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 1000),
                    'url' => $url,
                ]);

                return null;
            }

            if (!array_key_exists('trades_candles', $json)) {
                Log::warning('Thndr candles payload missing trades_candles', [
                    'asset_id' => $assetId,
                    'resolution' => $resolution,
                    'keys' => array_keys($json),
                    'body' => substr($response->body(), 0, 1000),
                    'url' => $url,
                ]);
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Thndr candles request exception', [
                'asset_id' => $assetId,
                'resolution' => $resolution,
                'start' => $startTs,
                'end' => $endTs,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** تحويل JSON الشموع إلى مصفوفة قياسية (timestamp يتحول لثواني epoch) */
    public function normalizeCandles(?array $json): array
    {
        $candles = [];

        if (! is_array($json)) {
            return $candles;
        }

        foreach ($json['trades_candles'] ?? [] as $candle) {
            $candles[] = [
                'timestamp' => self::timestampToEpoch($candle['timestamp'] ?? 0),
                'open' => (float) ($candle['open'] ?? 0),
                'high' => (float) ($candle['high'] ?? 0),
                'low' => (float) ($candle['low'] ?? 0),
                'close' => (float) ($candle['close'] ?? 0),
                'volume' => (float) ($candle['volume'] ?? 0),
            ];
        }

        usort($candles, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $candles;
    }

    /**
     * يحوّل timestamp بأي صيغة (ISO نصية أو epoch رقمية قد تكون ms) إلى ثواني epoch.
     * الـ API يعيد أحياناً '2026-09-22T00:00:00Z' — التحويل القديم (int) كان يكسرها.
     */
    public static function timestampToEpoch(mixed $ts): int
    {
        if (is_numeric($ts)) {
            $n = (int) $ts;
            if ($n > 100000000000) { // ملي ثانية
                $n = intdiv($n, 1000);
            }

            return max(0, $n);
        }

        $epoch = strtotime((string) $ts);

        return $epoch !== false ? $epoch : 0;
    }

    /** ثواني epoch → ISO UTC بنفس صيغة المرجع القديم (2026-09-22T00:00:00Z) */
    public static function epochToIso(int $ts): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /** استيراد شموع من ملفات المرجعة القديمة (candles/*.json) */
    public static function candlesFromLegacyArray(array $legacy): array
    {
        $candles = [];

        foreach ($legacy as $candle) {
            $candles[] = [
                'timestamp' => self::timestampToEpoch($candle['timestamp'] ?? 0),
                'open' => (float) ($candle['open'] ?? 0),
                'high' => (float) ($candle['high'] ?? 0),
                'low' => (float) ($candle['low'] ?? 0),
                'close' => (float) ($candle['close'] ?? 0),
                'volume' => (float) ($candle['volume'] ?? 0),
            ];
        }

        usort($candles, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $candles;
    }
}
