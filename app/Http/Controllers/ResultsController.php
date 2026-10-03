<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'analysis');
        abort_unless(in_array($type, ['analysis', 'intraday'], true), 401);

        $runs = DB::table('analysis')->where('type', $type)
            ->orderByDesc('analysis_date')->orderByDesc('created_at')->get();

        $selected = $runs->firstWhere('id', (int)$request->query('analysis_id')) ?? $runs->first();
        $result = $selected ? $this->readRun($selected) : ['stocks'=>[], 'raw'=>[]];

        $symbols = collect($result['stocks'])->map(fn($s)=>
            strtoupper((string)($s['symbol'] ?? data_get($s,'feed.symbol','')))
        )->filter()->unique()->values()->all();

        $companies = collect();
        if ($symbols) {
            $companies = DB::table('stocks')->whereIn('symbol', $symbols)->get()->keyBy(fn($s)=>strtoupper($s->symbol));
        }

        $stocks = array_map(function(array $s) use ($companies) {
            $symbol = strtoupper((string)($s['symbol'] ?? data_get($s,'feed.symbol','')));
            $company = $companies->get($symbol);
            $s['_symbol'] = $symbol;
            $s['_arabic'] = $s['arb_name'] ?? ($company->arabic_name ?? $company->arb_name ?? null);
            $s['_english'] = $s['eng_name'] ?? ($company->name ?? $company->eng_name ?? $s['name'] ?? '');
            $s['_logo'] = $s['logo'] ?? ($company->logo ?? null);
            $s['_accepted'] = self::accepted($s);
            $s['_signal_ar'] = self::signalArabic($s, request()->query('type','analysis'));
            $s['_reason_ar'] = self::reasonArabic($s);
            $s['_search'] = mb_strtolower(implode(' ', [$symbol,$s['_arabic']??'',$s['_english']??'']));
            return $s;
        }, $result['stocks']);

        return view('results.index', compact('type','runs','selected','result','stocks'));
    }

    public function show(string $date, string $symbol)
    {
        $run = DB::table('analysis')->where('id', $date)->first();
        abort_if(!$run,401,'سجل التحليل غير موجود');
        $result = $this->readRun($run);
        $stock = collect($result['stocks'])->first(fn($s)=>strtoupper((string)($s['symbol']??data_get($s,'feed.symbol','')))===strtoupper($symbol));
        abort_if(!$stock,401,'السهم غير موجود في هذا التشغيل');
        $stock['_symbol']=strtoupper($symbol);
        $stock['_accepted']=self::accepted($stock);
        $stock['_signal_ar']=self::signalArabic($stock,$run->type);
        $stock['_reason_ar']=self::reasonArabic($stock);
        return view('results.show',compact('run','stock','result'));
    }

    private function readRun(object $run): array
    {
        $stored = trim((string)$run->file_path);
        $candidates = [
            $stored,
            storage_path('app/'.ltrim($stored,'/\\')),
            storage_path('app/private/analysis/'.basename($stored)),
            storage_path('app/private/analysis/'.$stored),
            storage_path('app/results/days/'.basename($stored)),
        ];
        $path = collect($candidates)->first(fn($p)=>$p && File::exists($p));
        abort_if(!$path,401,'ملف التحليل غير موجود: '.$stored);
        $data=json_decode(File::get($path),true);
        abort_if(json_last_error()!==JSON_ERROR_NONE || !is_array($data),422,'ملف JSON غير صالح');
        if(array_is_list($data)) $stocks=$data;
        else $stocks=$data['stocks']??$data['results']??array_merge(
            is_array($data['qualified']??null)?$data['qualified']:[],
            is_array($data['rejected']??null)?$data['rejected']:[]
        );
        return ['raw'=>$data,'stocks'=>array_values(array_filter(is_array($stocks)?$stocks:[], 'is_array'))];
    }

    public static function accepted(array $s): bool
    {
        $status=strtoupper((string)($s['status']??$s['state']??''));
        if(in_array($status,['QUALIFIED','ACCEPTED','APPROVED','مقبول'],true)) return true;
        if(in_array($status,['REJECTED','FAILED','مرفوض'],true)) return false;
        return !in_array(strtoupper((string)($s['signal']??'')),['','NONE','WAIT','REJECTED'],true);
    }

    public static function signalArabic(array $s,string $type): string
    {
        if($type==='analysis') return self::valueArabic($s['trend']??$s['support_bounce_signal']??$s['support_bounce']??'—');
        $v=strtoupper((string)($s['signal']??''));
        return ['BUY'=>'شراء','STRONG_BUY'=>'شراء قوي','SELL'=>'بيع','HOLD'=>'احتفاظ','WAIT'=>'انتظار','NONE'=>'بدون إشارة'][$v]??($s['strategy']??'—');
    }

    public static function reasonArabic(array $s): string
    {
        $v=$s['signal_reason']??$s['reason']??$s['rejection_reason']??$s['reject_reason']??null;
        if(is_string($v)&&trim($v)!==''){
            $key=strtoupper(trim($v));
            return ['LOW_RISK_REWARD'=>'نسبة العائد إلى المخاطرة أقل من الحد المحدد.','ENTRY_TOO_FAR'=>'السعر الحالي بعيد عن منطقة الدخول.','MISSING_CANDLES'=>'بيانات الشموع غير مكتملة.'][$key]??str_replace('_',' ',$v);
        }
        foreach(['data_errors','errors'] as $k) if(!empty($s[$k])) return self::valueArabic($s[$k]);
        return 'لا توجد ملاحظة تفصيلية مسجلة في ملف التحليل.';
    }

    public static function valueArabic($v): string
    {
        if(is_bool($v)) return $v?'نعم':'لا';
        if(is_array($v)) return implode('،',array_map(fn($x)=>is_scalar($x)?(string)$x:json_encode($x,JSON_UNESCAPED_UNICODE),$v));
        return is_scalar($v)?(string)$v:'—';
    }

    public static function label(string $key): string
    {
        return ['symbol'=>'رمز السهم','status'=>'الحالة','signal'=>'الإشارة','strategy'=>'الاستراتيجية','entry_timeframe'=>'إطار الدخول','entry'=>'سعر الدخول','entry_price'=>'سعر الدخول','entry_low'=>'أقل الدخول','entry_high'=>'أعلى الدخول','entry_distance'=>'المسافة عن الدخول','stop_loss'=>'وقف الخسارة','target_1'=>'الهدف الأول','target_2'=>'الهدف الثاني','target1'=>'الهدف الأول','target2'=>'الهدف الثاني','last_trade_price'=>'السعر الحالي','close_price'=>'الإغلاق','signal_strength'=>'قوة الإشارة','opportunity_score'=>'درجة الفرصة','speed_score'=>'درجة السرعة','fast_score'=>'درجة التداول السريع','reason'=>'السبب','signal_reason'=>'سبب الإشارة','trend'=>'الاتجاه','trend_score'=>'درجة الاتجاه','swings'=>'القمم والقيعان','bos'=>'كسر هيكل السوق BOS','choch'=>'تغير السلوك CHoCH','liquidity_sweep'=>'سحب السيولة','supports'=>'الدعوم','resistances'=>'المقاومات','volume_score'=>'درجة الحجم','relative_volume'=>'الحجم النسبي','atr'=>'متوسط المدى الحقيقي ATR','atr_percent'=>'نسبة ATR','trade'=>'خطة التداول','timeframes'=>'الأطر الزمنية','hierarchy'=>'توافق الأطر','strategies'=>'الاستراتيجيات','support_bounce_score'=>'درجة الارتداد من الدعم','support_confirmed'=>'تأكيد الدعم','opportunity_grade'=>'تصنيف الفرصة','fast_class'=>'تصنيف السرعة','fast_trade'=>'تداول سريع','feed'=>'بيانات السوق','candles'=>'الشموع','data_errors'=>'أخطاء البيانات'][$key]??str_replace('_',' ',$key);
    }
}
