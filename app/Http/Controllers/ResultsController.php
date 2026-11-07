<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'analysis');
        abort_unless(in_array($type, ['analysis', 'intraday', 'scalping'], true), 404);

        $runs = DB::table('analysis')->where('type', $type)
            ->orderByDesc('analysis_date')->orderByDesc('created_at')->get();

        $selected = $runs->firstWhere('id', (int) $request->query('analysis_id')) ?? $runs->first();
        $result = $selected ? $this->readRun($selected) : ['stocks' => [], 'raw' => []];

        $symbols = collect($result['stocks'])->map(fn ($s) =>
            strtoupper((string) ($s['symbol'] ?? data_get($s, 'feed.symbol', '')))
        )->filter()->unique()->values()->all();

        $companies = collect();
        if ($symbols) {
            $companies = DB::table('stocks')->whereIn('symbol', $symbols)
                ->get()->keyBy(fn ($s) => strtoupper($s->symbol));
        }

        $stocks = array_map(function (array $s) use ($companies, $type) {
            $symbol = strtoupper((string) ($s['symbol'] ?? data_get($s, 'feed.symbol', '')));
            $company = $companies->get($symbol);

            $s['_symbol'] = $symbol;
            $s['_arabic'] = $s['arb_name'] ?? ($company->arabic_name ?? $company->arb_name ?? null);
            $s['_english'] = $s['eng_name'] ?? ($company->name ?? $company->eng_name ?? $s['name'] ?? '');
            $s['_logo'] = $s['logo'] ?? ($company->logo ?? null);
            $s['_accepted'] = self::accepted($s);
            $s['_signal_ar'] = self::signalArabic($s, $type);
            $s['_reason_ar'] = self::reasonArabic($s);
            $s['_timeframe'] = self::timeframeLabel($s, $type);
            $s['_search'] = mb_strtolower(implode(' ', [
                $symbol, $s['_arabic'] ?? '', $s['_english'] ?? '', $s['_timeframe'],
            ]));

            return $s;
        }, $result['stocks']);

        return view('results.index', compact('type', 'runs', 'selected', 'result', 'stocks'));
    }

    public function show(string $date, string $symbol)
    {
        $run = DB::table('analysis')->where('id', $date)->first();
        abort_if(!$run, 404, 'سجل التحليل غير موجود');

        $result = $this->readRun($run);
        $stock = collect($result['stocks'])->first(fn ($s) =>
            strtoupper((string) ($s['symbol'] ?? data_get($s, 'feed.symbol', ''))) === strtoupper($symbol)
        );
        abort_if(!$stock, 404, 'السهم غير موجود في هذا التشغيل');

        $stock['_symbol'] = strtoupper($symbol);
        $stock['_accepted'] = self::accepted($stock);
        $stock['_signal_ar'] = self::signalArabic($stock, $run->type);
        $stock['_reason_ar'] = self::reasonArabic($stock);
        $stock['_timeframe'] = self::timeframeLabel($stock, $run->type);

        return view('results.show', compact('run', 'stock', 'result'));
    }

    private function readRun(object $run): array
    {
        $stored = trim((string) $run->file_path);
        $candidates = [
            $stored,
            storage_path('app/' . ltrim($stored, '/\\\\')),
            storage_path('app/private/analysis/' . basename($stored)),
            storage_path('app/private/analysis/' . $stored),
            storage_path('app/results/days/' . basename($stored)),
        ];

        $path = collect($candidates)->first(fn ($p) => $p && File::exists($p));
        abort_if(!$path, 404, 'ملف التحليل غير موجود: ' . $stored);

        $data = json_decode(File::get($path), true);
        abort_if(json_last_error() !== JSON_ERROR_NONE || !is_array($data), 422, 'ملف JSON غير صالح');

        if (array_is_list($data)) {
            $stocks = $data;
        } else {
            $stocks = $data['stocks'] ?? $data['results'] ?? array_merge(
                is_array($data['qualified'] ?? null) ? $data['qualified'] : [],
                is_array($data['rejected'] ?? null) ? $data['rejected'] : []
            );
        }

        return [
            'raw' => $data,
            'stocks' => array_values(array_filter(is_array($stocks) ? $stocks : [], 'is_array')),
        ];
    }

    public static function accepted(array $s): bool
    {
        $status = strtoupper((string) ($s['status'] ?? $s['state'] ?? ''));

        if (in_array($status, ['QUALIFIED', 'ACCEPTED', 'APPROVED', 'مقبول'], true)) {
            return true;
        }
        if (in_array($status, ['REJECTED', 'FAILED', 'ERROR', 'مرفوض'], true)) {
            return false;
        }

        return !in_array(strtoupper((string) ($s['signal'] ?? '')), ['', 'NONE', 'WAIT', 'REJECTED'], true);
    }

    public static function signalArabic(array $s, string $type): string
    {
        if ($type === 'analysis') {
            return self::valueArabic($s['trend'] ?? $s['support_bounce_signal'] ?? $s['support_bounce'] ?? '—');
        }

        if ($type === 'scalping') {
            return self::accepted($s) ? 'اختراق EMA 34' : 'لا توجد إشارة دخول';
        }

        $v = strtoupper((string) ($s['signal'] ?? ''));
        return [
            'BUY' => 'شراء',
            'STRONG_BUY' => 'شراء قوي',
            'SELL' => 'بيع',
            'HOLD' => 'احتفاظ',
            'WAIT' => 'انتظار',
            'NONE' => 'بدون إشارة',
        ][$v] ?? ($s['strategy'] ?? '—');
    }

    public static function timeframeLabel(array $s, string $type): string
    {
        $normalize = static function ($value): ?string {
            if (!is_scalar($value)) {
                return null;
            }
            $value = strtolower(trim((string) $value));
            $map = [
                '1min' => '1m', '1 minute' => '1m', '1minute' => '1m',
                '5min' => '5m', '5 minute' => '5m', '5minute' => '5m',
                '15min' => '15m', '15 minute' => '15m',
                '30min' => '30m', '1hr' => '1h', '60min' => '1h',
                '1hour' => '1h', '1 day' => '1d',
            ];
            return $map[$value] ?? $value;
        };

        if ($type === 'scalping') {
            $frames = $s['qualified_timeframes'] ?? [];
            if (!is_array($frames) || $frames === []) {
                $frames = [];
                foreach (($s['entry_timeframes'] ?? []) as $key => $entry) {
                    if (is_array($entry) && strtoupper((string) ($entry['status'] ?? '')) === 'QUALIFIED') {
                        $frames[] = $entry['timeframe'] ?? $key;
                    }
                }
            }
            if ($frames === [] && !empty($s['signals']) && is_array($s['signals'])) {
                foreach ($s['signals'] as $signal) {
                    if (is_array($signal) && !empty($signal['timeframe'])) {
                        $frames[] = $signal['timeframe'];
                    }
                }
            }
            $frames = array_values(array_unique(array_filter(array_map($normalize, $frames))));
            if ($frames) {
                return implode(' + ', $frames);
            }

            // Rejected stocks still show which entry frames were evaluated,
            // clearly marked as scan frames rather than an entry signal.
            $checked = [];
            foreach (($s['entry_timeframes'] ?? []) as $key => $entry) {
                $checked[] = is_array($entry) ? ($entry['timeframe'] ?? $key) : $key;
            }
            $checked = array_values(array_unique(array_filter(array_map($normalize, $checked))));
            return $checked ? 'فحص: ' . implode(' + ', $checked) : '—';
        }

        $candidates = [
            $s['entry_timeframe'] ?? null,
            $s['signal_timeframe'] ?? null,
            $s['execution_timeframe'] ?? null,
            $s['timeframe'] ?? null,
            data_get($s, 'trade.timeframe'),
            data_get($s, 'signal.timeframe'),
            data_get($s, 'entry.timeframe'),
        ];

        foreach ($candidates as $candidate) {
            $value = $normalize($candidate);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        // Some Intraday outputs store a timeframe map with the active entry marker.
        $timeframes = $s['timeframes'] ?? [];
        if (is_array($timeframes)) {
            foreach ($timeframes as $key => $value) {
                if (is_array($value) && (($value['is_entry'] ?? false) || ($value['entry'] ?? false) || strtoupper((string) ($value['signal'] ?? '')) === 'BUY')) {
                    return $normalize($value['timeframe'] ?? $key) ?? '—';
                }
            }
        }

        return '—';
    }

    public static function reasonArabic(array $s): string
    {
        $v = $s['signal_reason'] ?? $s['reason'] ?? $s['rejection_reason'] ?? $s['reject_reason'] ?? null;

        if (is_string($v) && trim($v) !== '') {
            $key = strtoupper(trim($v));
            return [
                'LOW_RISK_REWARD' => 'نسبة العائد إلى المخاطرة أقل من الحد المحدد.',
                'ENTRY_TOO_FAR' => 'السعر الحالي بعيد عن منطقة الدخول.',
                'MISSING_CANDLES' => 'بيانات الشموع غير مكتملة.',
                'EMA_BREAKOUT_CONFIRMED' => 'تم تأكيد اختراق EMA 34 مع تحقق شروط الاستراتيجية.',
                'NO_ENTRY_TIMEFRAME_CONFIRMED' => 'اتجاه EMA متوافق، لكن شروط الدخول لم تكتمل على فريم 1m أو 5m.',
                'HIGHER_TIMEFRAME_TREND_NOT_CONFIRMED' => 'ترتيب EMA 20 و34 و50 غير متوافق على فريم 15m و1h.',
                'ENTRY_CONDITIONS_NOT_MET' => 'شروط الاختراق أو الفوليوم أو الاستوكاستك غير مكتملة.',
                'INSUFFICIENT_ENTRY_CANDLES' => 'عدد شموع فريم الدخول غير كافٍ.',
                'INSUFFICIENT_CANDLES' => 'عدد الشموع غير كافٍ لحساب المؤشرات.',
                'ANALYSIS_ERROR' => 'حدث خطأ أثناء تحليل السهم.',
                'ASSET_NOT_FOUND_IN_MARKETWATCH' => 'السهم غير موجود في بيانات Market Watch.',
            ][$key] ?? str_replace('_', ' ', $v);
        }

        foreach (['data_errors', 'errors'] as $k) {
            if (!empty($s[$k])) {
                return self::valueArabic($s[$k]);
            }
        }

        return 'لا توجد ملاحظة تفصيلية مسجلة في ملف التحليل.';
    }

    public static function label(string $key): string
    {
        return [
            'symbol' => 'رمز السهم',
            'status' => 'الحالة',
            'signal' => 'الإشارة',
            'strategy' => 'الاستراتيجية',
            'entry_timeframe' => 'إطار الدخول',
            'entry' => 'سعر الدخول',
            'entry_price' => 'سعر الدخول',
            'entry_low' => 'أقل الدخول',
            'entry_high' => 'أعلى الدخول',
            'entry_distance' => 'المسافة عن الدخول',
            'stop_loss' => 'وقف الخسارة',
            'target_1' => 'الهدف الأول',
            'target_2' => 'الهدف الثاني',
            'target1' => 'الهدف الأول',
            'target2' => 'الهدف الثاني',
            'last_trade_price' => 'السعر الحالي',
            'close_price' => 'الإغلاق',
            'signal_strength' => 'قوة الإشارة',
            'opportunity_score' => 'درجة الفرصة',
            'speed_score' => 'درجة السرعة',
            'fast_score' => 'درجة التداول السريع',
            'reason' => 'السبب',
            'signal_reason' => 'سبب الإشارة',
            'trend' => 'الاتجاه',
            'trend_score' => 'درجة الاتجاه',
            'swings' => 'القمم والقيعان',
            'bos' => 'كسر هيكل السوق BOS',
            'choch' => 'تغير السلوك CHoCH',
            'liquidity_sweep' => 'سحب السيولة',
            'supports' => 'الدعوم',
            'resistances' => 'المقاومات',
            'volume_score' => 'درجة الحجم',
            'relative_volume' => 'الحجم النسبي',
            'atr' => 'متوسط المدى الحقيقي ATR',
            'atr_percent' => 'نسبة ATR',
            'trade' => 'خطة التداول',
            'timeframes' => 'الأطر الزمنية',
            'hierarchy' => 'توافق الأطر',
            'strategies' => 'الاستراتيجيات',
            'support_bounce_score' => 'درجة الارتداد من الدعم',
            'support_confirmed' => 'تأكيد الدعم',
            'opportunity_grade' => 'تصنيف الفرصة',
            'fast_class' => 'تصنيف السرعة',
            'fast_trade' => 'تداول سريع',
            'feed' => 'بيانات السوق',
            'candles' => 'الشموع',
            'data_errors' => 'أخطاء البيانات',
        ][$key] ?? str_replace('_', ' ', $key);
    }

    public static function valueArabic($v): string
    {
        if (is_bool($v)) {
            return $v ? 'نعم' : 'لا';
        }
        if (is_array($v)) {
            return implode('،', array_map(
                fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE),
                $v
            ));
        }
        return is_scalar($v) ? (string) $v : '—';
    }
}
