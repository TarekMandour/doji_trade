<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalysisRequest;
use App\Models\Analysis;
use App\Models\Watchlist;
use App\Services\Thndr\IntradayMultiTimeframeService;
use App\Services\Thndr\StockAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class AnalysisController extends Controller
{
    protected $viewPath = 'admin.analysis';
    private $route = 'admin.analysis';
    private $objectModel = Analysis::class;

    public function __construct(Analysis $model)
    {
        $this->objectModel = $model;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->objectModel::with('watchlist')->orderBy('id', 'DESC');

            if (! empty($request->search)) {
                $data->where('title', 'LIKE', "%$request->search%");
            }

            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    return '<div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="'.$row->id.'" />
                                </div>';
                })
                ->addColumn('watchlist_name', function ($row) {
                    return $row->watchlist->name ?? '-';
                })
                ->addColumn('date', function ($row) {
                    return $row->analysis_date->format('F j, Y');
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.route($this->route.'.show', $row->id).'" class="btn btn-xs btn-icon btn-light-primary me-2"><i class="bi bi-eye fs-4"></i></a>';
                })
                ->rawColumns(['date', 'action', 'checkbox'])
                ->make(true);
        }

        return view($this->viewPath.'.index');
    }

    public function create()
    {
        $watchlists = Watchlist::withCount('stocks')->orderBy('name')->get();
        $resolutions = config('thndr.resolutions', []);

        return view($this->viewPath.'.create', compact('watchlists', 'resolutions'));
    }

    public function intraday()
    {
        $watchlists = Watchlist::withCount('stocks')->orderBy('name')->get();
        $resolutions = config('thndr.resolutions', []);

        return view($this->viewPath.'.intraday', compact('watchlists', 'resolutions'));
    }

    public function store(AnalysisRequest $request, StockAnalysisService $service)
    {
        $data = $request->validated();

        $watchlist = Watchlist::with('stocks')->findOrFail($data['watchlist_id']);
        $assetIds = $watchlist->stocks->pluck('asset_id')->all();

        $results = $service->analyzeAssetIds($assetIds, $data['resolution'], (int) $data['candles_count']);

        $filePath = 'analysis/'.now()->format('Ymd_His').'_'.$watchlist->id.'.json';
        Storage::disk('local')->put($filePath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $title = $data['title'] ?: $watchlist->name.' - '.$data['resolution'].' - '.$data['candles_count'];

        $analysis = $this->objectModel::create([
            'title' => $title,
            'type' => 'analysis',
            'watchlist_id' => $watchlist->id,
            'analysis_date' => now()->toDateString(),
            'file_path' => $filePath,
            'stocks_count' => count($results),
        ]);

        return redirect(route($this->route.'.show', $analysis->id))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function storeIntraday(AnalysisRequest $request, IntradayMultiTimeframeService $service)
    {
        $data = $request->validated();

        $watchlist = Watchlist::with('stocks')->findOrFail($data['watchlist_id']);
        $assetIds = $watchlist->stocks->pluck('asset_id')->all();

        $results = $service->analyzeAssetIds($assetIds);

        $filePath = 'analysis/'.now()->format('Ymd_His').'_'.$watchlist->id.'.json';
        Storage::disk('local')->put($filePath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $title = $data['title'] ?: $watchlist->name.' - Intraday';

        $analysis = $this->objectModel::create([
            'title' => $title,
            'type' => 'intraday',
            'watchlist_id' => $watchlist->id,
            'analysis_date' => now()->toDateString(),
            'file_path' => $filePath,
            'stocks_count' => count($results),
        ]);

        return redirect(route($this->route.'.show', $analysis->id))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function show($id)
    {
        $data = $this->objectModel::with('watchlist')->findOrFail($id);

        $results = Storage::disk('local')->exists($data->file_path)
            ? json_decode(Storage::disk('local')->get($data->file_path), true)
            : [];

        return view($this->viewPath.'.show', compact('data', 'results'));
    }

    public function destroy(Request $request)
    {
        try {
            $items = $this->objectModel::whereIn('id', $request->id)->get();

            foreach ($items as $item) {
                Storage::disk('local')->delete($item->file_path);
            }

            $this->objectModel::whereIn('id', $request->id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'error']);
        }

        return response()->json(['message' => 'success']);
    }
}
