<div class="row">
    <div class="col-lg-12">

        <div class="row fv-row mb-7">
            <!--begin::Label-->
            <label class="col-lg-3 col-form-label required fw-semibold fs-6">{{__('lang.classrooms')}}</label>
            <!--end::Label-->
            <div class="col-lg-9 fv-row">
                <select  data-control="select2" data-placeholder="Select an option" class="input-text form-control  form-select  mb-3 mb-lg-0"  name="classroom_id">
                    @foreach (App\Models\Classroom::all() as $class_item)
                        <option value="{{$class_item->id}}" @if(isset($data) && $data->classroom_id == $class_item->id) selected @endif>{{$class_item->name}} </option>
                    @endforeach
                </select>
            </div>
            <!--end::Input-->
        </div>

        <div class="row fv-row mb-7">
            <label class="col-lg-3 col-form-label required fw-semibold fs-6">{{__('lang.title')}}</label>
            <div class="col-lg-9 fv-row">
                <input type="text" name="title" placeholder="{{__('lang.title')}}" value="{{old('title',$data->title ?? '')}}" class="form-control form-control-lg form-control-solid mb-3 mb-lg-0" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <label class="col-lg-3 col-form-label required fw-semibold fs-6">{{__('lang.description')}}</label>
            <div class="col-lg-9 fv-row">
                <textarea name="body" id="kt_docs_tinymce_basic">
                    {{old('body',$data->body ?? '')}}
                </textarea>
            </div>
        </div>      

        
    </div>
</div>


<script src="{{ URL::asset('dash/assets/plugins/custom/tinymce/tinymce.bundle.js')}}"></script>

<script>
    var options = {selector: "#kt_docs_tinymce_basic, #kt_docs_tinymce_basic2"};

    tinymce.init(options);

</script>
