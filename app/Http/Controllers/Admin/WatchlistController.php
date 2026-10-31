<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Watchlist;
use App\Models\Stock;
use App\Http\Requests\WatchlistRequest;

class WatchlistController extends Controller
{
    protected $viewPath = 'admin.watchlists';
    private $route = 'admin.watchlists';
    private $objectModel = Watchlist::class;

    public function __construct(Watchlist $model)
    {
        $this->objectModel = $model;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->objectModel::withCount('stocks');
            $data = $data->orderBy('id', 'DESC');

            if (!empty($request->search)) {
                $data->where('name', 'LIKE', "%$request->search%");
            }

            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    $checkbox = '<div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="' . $row->id . '" />
                                </div>';
                    return $checkbox;
                })
                ->addColumn('stocks_count', function ($row) {
                    return $row->stocks_count;
                })
                ->addColumn('date', function ($row) {
                    return $row->created_at->format('F j, Y');
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="' . route($this->route . '.show', $row->id) . '" class="btn btn-xs btn-icon btn-light-primary me-2"><i class="bi bi-eye fs-4"></i></a>';
                    $btn .= '<a href="' . route($this->route . '.edit', $row->id) . '" class="btn btn-xs btn-icon btn-primary me-2"><i class="bi bi-pencil-square fs-4"></i></a>';
                    return $btn;
                })
                ->rawColumns(['date', 'action', 'checkbox'])
                ->make(true);
        }
        return view($this->viewPath . '.index');
    }

    public function show($id)
    {
        $data = $this->objectModel::with('stocks')->findOrFail($id);
        return view($this->viewPath . '.show', compact('data'));
    }

    public function create()
    {
        $stocks = Stock::orderBy('name')->get();
        return view($this->viewPath . '.create', compact('stocks'));
    }

    public function store(WatchlistRequest $request)
    {
        $data = $request->validated();

        $result = $this->objectModel::create([
            'name' => $data['name'],
            'user_id' => auth('admin')->id(),
        ]);

        $this->syncStocks($result, $data['stock_ids'] ?? []);

        return redirect(route($this->route . '.index'))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function edit($id)
    {
        $data = $this->objectModel::with('stocks')->findOrFail($id);
        $stocks = Stock::orderBy('name')->get();

        return view($this->viewPath . '.edit', compact('data', 'stocks'));
    }

    public function update(WatchlistRequest $request)
    {
        $data = $request->validated();

        $result = $this->objectModel::whereId($request->id)->firstOrFail();

        $result->update(['name' => $data['name']]);

        $this->syncStocks($result, $data['stock_ids'] ?? []);

        return redirect(route($this->route . '.index'))->with('message', __('lang.update_success'))->with('status', 'success');
    }

    public function destroy(Request $request)
    {
        try {
            $this->objectModel::whereIn('id', $request->id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'error']);
        }
        return response()->json(['message' => 'success']);
    }

    /**
     * @param  array<int, int|string>  $stockIds
     */
    private function syncStocks(Watchlist $watchlist, array $stockIds): void
    {
        $pivotData = [];
        foreach (array_values($stockIds) as $position => $stockId) {
            $pivotData[$stockId] = ['position' => $position];
        }

        $watchlist->stocks()->sync($pivotData);
    }
}
