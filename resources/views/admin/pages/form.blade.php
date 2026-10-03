<div class="row">
    <div class="col-lg-12">

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.title') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="text" class="form-control form-control-solid" name="name" value="{{old('name',$data->name ?? '')}}" id="name" placeholder="{{ __('lang.title') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.slug') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="text" class="form-control form-control-solid" name="slug" value="{{old('slug',$data->slug ?? '')}}" id="slug" placeholder="{{ __('lang.slug') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7" style="display: none">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.type') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <select class="form-select mb-2" data-control="select2" name="type" data-hide-search="true" data-placeholder="{{ __('lang.choose') }}" id="kt_ecommerce_add_category_store_template">
                    <option value="section" >{{ __('lang.section') }}</option>
                </select>
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.description') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <textarea name="description" id="kt_docs_tinymce_basic">
                    {{old('description',$data->description ?? '')}}
                </textarea>
            </div>
        </div>
        
    </div>
</div>

<script src="{{ URL::asset('dash/assets/plugins/custom/tinymce/tinymce.bundle.js')}}"></script>

<script>
    var options = {selector: "#kt_docs_tinymce_basic"};

    tinymce.init(options);

    document.getElementById("name").addEventListener("input", function () {
        let value = this.value.trim(); // trim input

        let slug = value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')   // remove special characters
            .replace(/\s+/g, '-')           // replace spaces with hyphens
            .replace(/-+/g, '-');           // remove duplicate hyphens

        document.getElementById("slug").value = slug;
    });
</script>
