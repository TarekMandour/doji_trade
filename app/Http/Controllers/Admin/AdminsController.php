<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use App\Models\Admin;
use App\Http\Requests\AdminsRequest;

class AdminsController extends Controller
{
    protected $viewPath = 'admin.admins';
    private $route = 'admin.admins';

    private $objectModel = Admin::class;

    public function __construct(Admin $model)
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
                $data->where('name', 'LIKE', "%$request->search%");
                $data->orWhere('phone', 'LIKE', "%$request->search%");
            }

            if (!empty($request->is_active)) {
                $data->where('is_active', $request->is_active);
            }
            
            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    $checkbox = '<div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="' . $row->id . '" />
                                </div>';
                    return $checkbox;
                })
                ->addColumn('action', function($row){
                    $btn = '<a href="'.route($this->route.'.edit', $row->id).'" class="btn btn-xs btn-icon btn-primary me-2"><i class="bi bi-pencil-square fs-4"></i></a>';
                    return $btn;
                })
                ->rawColumns(['action','checkbox'])
                ->make(true);

        }
        return view($this->viewPath . '.index');
    }

    // For export with filters
    public function export(Request $request)
    {

        $data = $this->objectModel::query();
        
        // Apply the same filters as index
        if (!empty($request->is_active)) {
           $data = $data->where('is_active', $request->is_active );
        }

        $data = $data->get();

        $result = [];

        $result[] = ['ID', 'Full Name', 'Phone', 'Email Address'];

        foreach ($data as $admin) {
            $result[] = [
                $admin->id,
                $admin->name,
                $admin->phone,
                $admin->email,
            ];
        }

        return Excel::download(new class($result) implements FromArray {
            protected $result;

            public function __construct($result)
            {
                $this->result = $result;
            }

            public function array(): array
            {
                return $this->result;
            }
        }, 'admins.xlsx');

    }

    public function show($id)
    {
        $data = $this->objectModel::find($id);
        return view($this->viewPath . '.show', compact('data'));
    }

    public function create()
    {
        return view($this->viewPath . '.create');
    }

    public function store(AdminsRequest $request)
    {
        $data = $request->validated();
        $data['password']=Hash::make($request->password);
        $result = $this->objectModel::create($data);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $result->clearMediaCollection('image');
            $result->addMediaFromRequest('image')->toMediaCollection('image');
        }

        $role = Role::find($request->role_id);
        $result->syncRoles([]);
        $result->assignRole($role->name);

        return redirect()->route($this->route . '.index')->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function edit($id)
    {
        $data = $this->objectModel::find($id);
        return view($this->viewPath . '.edit', compact('data'));
    }

    public function update(AdminsRequest $request)
    {

        $data = $request->validated(); 

        $result = $this->objectModel::whereId($request->id)->first();

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        } else {
            $data['password'] = $result->password;
        }

        $result->update($data);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $result->clearMediaCollection('image');
            $result->addMediaFromRequest('image')->toMediaCollection('image');
        }

        $role = Role::find($request->role_id);
        $result->syncRoles([]);
        $result->assignRole($role->name);

        return redirect()->route($this->route . '.index')->with('message', __('lang.update_success'))->with('status', 'success');
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
}
