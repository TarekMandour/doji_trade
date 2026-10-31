<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Imports\StockImport;
use App\Models\Stock;
use App\Http\Requests\StockRequest;

class StockController extends Controller
{
    protected $viewPath = 'admin.stocks';
    private $route = 'admin.stocks';
    private $objectModel = Stock::class;

    public function __construct(Stock $model)
    {
        $this->objectModel = $model;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->objectModel::query();
            $data = $data->orderBy('id', 'DESC');

            // Apply filters
            if (!empty($request->search)) {
                $data->where(function ($query) use ($request) {
                    $query->where('name', 'LIKE', "%$request->search%")
                        ->orWhere('symbol', 'LIKE', "%$request->search%");
                });
            }

            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    $checkbox = '<div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="' . $row->id . '" />
                                </div>';
                    return $checkbox;
                })
                ->addColumn('date', function ($row) {
                    return $row->created_at->format('F j, Y');
                })
                ->addColumn('status', function ($row) {
                    return $row->is_tradable
                        ? '<span class="badge badge-light-success">' . __('lang.active') . '</span>'
                        : '<span class="badge badge-light-danger">' . __('lang.inactive') . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="' . route($this->route . '.show', $row->id) . '" class="btn btn-xs btn-icon btn-light-primary me-2"><i class="bi bi-eye fs-4"></i></a>';
                    $btn .= '<a href="' . route($this->route . '.edit', $row->id) . '" class="btn btn-xs btn-icon btn-primary me-2"><i class="bi bi-pencil-square fs-4"></i></a>';
                    return $btn;
                })
                ->rawColumns(['date', 'action', 'checkbox', 'status'])
                ->make(true);
        }
        return view($this->viewPath . '.index');
    }

    public function show($id)
    {
        $data = $this->objectModel::findOrFail($id);
        return view($this->viewPath . '.show', compact('data'));
    }

    public function create()
    {
        return view($this->viewPath . '.create');
    }

    public function store(StockRequest $request)
    {
        $data = $request->validated();
        $data['asset_id'] = (string) Str::uuid();

        $result = $this->objectModel::create($data);

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $path = $request->file('logo')->store('stocks', 'public');
            $result->update(['logo' => $path]);
        }

        return redirect(route($this->route . '.index'))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function edit($id)
    {
        $data = $this->objectModel::findOrFail($id);

        return view($this->viewPath . '.edit', compact('data'));
    }

    public function update(StockRequest $request)
    {
        $data = $request->validated();

        $result = $this->objectModel::whereId($request->id)->firstOrFail();

        $result->update($data);

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $path = $request->file('logo')->store('stocks', 'public');
            $result->update(['logo' => $path]);
        }

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

    public function export(Request $request)
    {

        $data = $this->objectModel::query();

        $data = $data->get();

        return Excel::download(new class($data) implements FromCollection {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection(): \Illuminate\Support\Collection
            {
                return collect($this->data);
            }
        }, 'file.xlsx');

    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new StockImport, $request->file('file'));

        return redirect(route($this->route . '.index'))->with('message', __('lang.import_success'))->with('status', 'success');
    }
}
