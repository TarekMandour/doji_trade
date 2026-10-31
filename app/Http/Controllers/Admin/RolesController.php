<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Requests\RoleRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RolesController extends Controller
{
    protected $viewPath = 'admin.role';
    private $route = 'admin.roles';

    private $objectModel = Role::class;

    public function __construct(Role $model)
    {
        $this->objectModel = $model;
    }

    public function index(Request $request)
    {
        $data = $this->objectModel::get();

        if ($request->ajax()) {
            $data = $this->objectModel::query();
            $data = $data->orderBy('id', 'DESC');

            return Datatables::of($data)
            ->addIndexColumn()
                ->addColumn('checkbox', function($row){
                    $checkbox = '<div class="form-check form-check-sm p-3 form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="'.$row->id.'" />
                                </div>';
                    return $checkbox;
                })
                ->addColumn('actions', function($row){
                    $actions = '<div class="ms-2">
                                <a href="'.route($this->route.'.edit', $row->id).'" class="btn btn-sm btn-icon btn-info btn-active-dark me-2" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    <i class="bi bi-pencil-square fs-1x"></i>
                                </a>
                            </div>';
                    return $actions;
                })
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                            $instance->where(function($w) use($request){
                            $search = $request->get('search');
                            $w->where('name', 'LIKE', "%$search%");
                        });
                    }
                })
                ->rawColumns(['checkbox','actions'])
                ->make(true);
        }

        return view($this->viewPath .'.index');
    }

    public function show($id)
    {
        $data = $this->objectModel::find($id);
        return view($this->viewPath .'.show', compact('data'));
    }

    public function create()
    {
        return view($this->viewPath .'.create');
    }

    public function store(RoleRequest $request)
    {

        $data = $request->validated();

        try {
            if(!empty($request->permissions)) {
                $role = $this->objectModel::create($data);
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                $permissionNames = $permissions->pluck('name')->toArray();
                $role->givePermissionTo($permissions);
            } else {
                $role = $this->objectModel::create($data);
                $role->givePermissionTo([]);
            }
                $role->save();
    
                return redirect(route($this->route . '.index'))->with('message', __('lang.add_success'))->with('status', 'success');
          } catch (\Exception $ex) {
              return redirect(route($this->route . '.index'))->with('message', __('lang.error'))->with('status', 'error');
          }
        
        return redirect(route($this->route . '.index'))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function edit($id)
    {
        $data = $this->objectModel::find($id);
        return view($this->viewPath .'.edit', compact('data'));
    }

    public function update(RoleRequest $request)
    {

        $data = $request->validated();

        $role = $this->objectModel::whereId($request->id)->first();

        try {

            if($role->name != $request->name) {
                $role->update($data);
            }
    
            if(!empty($request->permissions)) {
              $permissions = Permission::whereIn('id', $request->permissions)->get();
    
              $permissionNames = $permissions->pluck('name')->toArray();
    
              $role->syncPermissions($permissionNames);
            } else {
              $role->syncPermissions([]);
            }
    
    
            $role->save();
            return redirect(route($this->route . '.index'))->with('message', __('lang.update_success'))->with('status', 'success');
    
          } catch (\Exception $ex) {
              return redirect(route($this->route . '.index'))->with('message', __('lang.error'))->with('status', 'error');
          }

        return redirect(route($this->route . '.index'))->with('message', __('lang.update_success'))->with('status', 'success');
    }

    public function destroy(Request $request)
    {   

        try{
            $this->objectModel::whereIn('id',$request->id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => __('lang.error')]);
        }
        return response()->json(['message' => __('lang.success')]);

    }
}
