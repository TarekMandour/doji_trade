<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use App\Models\ClassroomStudent;
use App\Classes\FcmNotification;
use App\Models\Notification;

class NotificationController extends Controller
{
    protected $viewPath = 'admin.notifications';
    private $route = 'admin.notifications';
    private $objectModel = Notification::class;

    public function __construct(Notification $model)
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
                $data->where('title', 'LIKE', "%$request->search%");
            }

            if ($request->from_date && $request->to_date) {
                $data->whereBetween('created_at', [$request->from_date, $request->to_date]);
            }
            
            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    $checkbox = '<div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" value="' . $row->id . '" />
                                </div>';
                    return $checkbox;
                })
                ->addColumn('student', function($row){
                    $student = $row->student->name;
                    return $student;
                })
                ->addColumn('action', function($row){
                    $btn = '';
                    return $btn;
                })
                ->rawColumns(['action','checkbox','student'])
                ->make(true);

        }
        return view($this->viewPath . '.index');
    }

    public function export(Request $request)
    {

        $data = $this->objectModel::query();
        
        if ($request->from_date && $request->to_date) {
            $data->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $data = $data->get();

        return Excel::download(new class($data) implements FromCollection {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }
        }, 'file.xlsx');

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

    public function store(Request $request)
    {

        if (!$request->title) {
            return redirect()->back()->with('message', __('lang.title_required'))->with('status', 'error');
        }

        if (!$request->body) {
            return redirect()->back()->with('message', __('lang.body_required'))->with('status', 'error');
        }
        
        $token = [];
        // foreach (ClassroomStudent::where('classroom_id', $request->classroom_id)->get() as $key => $student) {
        //     if ($student->student->token != null) {
        //         $token[] = $student->student->token;
        //     }
        //     if ($student->student->token) {
        //         $send_noti = new FcmNotification($token, $request->title, $request->body, "other", 0, $student->student->id);
        //         $send_noti->sendNotification();
        //     }
        // }
        
        return redirect(route($this->route . '.index'))->with('message', __('lang.add_success'))->with('status', 'success');
    }

    public function destroy(Request $request)
    {   

        try{
            $this->objectModel::whereIn('id',$request->id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'error']);
        }
        return response()->json(['message' => 'success']);

    }

}
