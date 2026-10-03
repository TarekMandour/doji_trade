<div class="row">
    <div class="col-lg-12">

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span>{{ __('lang.image') }}</span>
                    <span class="ms-1" data-bs-toggle="tooltip" title="Allowed file types: png, jpg, jpeg.">
                        <i class="ki-duotone ki-information fs-7">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <div class="mt-1">
                    <!--begin::Image placeholder-->
                    @if (isset($data) && $data->getMedia('image')->count() > 0)
                    <style>.image-input-placeholder { background-image: url({{$data->getFirstMediaUrl('image', 'thumb')}}); } [data-bs-theme="dark"] .image-input-placeholder { background-image: url({{$data->getFirstMediaUrl('image', 'thumb')}}); }</style>
                    @else
                        <style>.image-input-placeholder { background-image: url({{asset('dash/assets/media/svg/files/blank-image.svg')}}); } [data-bs-theme="dark"] .image-input-placeholder { background-image: url({{asset('dash/assets/media/svg/files/blank-image-dark.svg')}}); }</style>
                    @endif
                    
                    <!--end::Image placeholder-->
                    <!--begin::Image input-->
                    <div class="image-input image-input-outline image-input-placeholder image-input-empty image-input-empty" data-kt-image-input="true">
                        <!--begin::Preview existing avatar-->
                        <div class="image-input-wrapper w-100px h-100px" style="background-image: url('')"></div>
                        <!--end::Preview existing avatar-->
                        <!--begin::Edit-->
                        <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="change" data-bs-toggle="tooltip" aria-label="Change avatar" data-bs-original-title="Change avatar" data-kt-initialized="1">
                            <i class="ki-duotone ki-pencil fs-7">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <!--begin::Inputs-->
                            <input type="file" name="image" accept=".png, .jpg, .jpeg">
                            <input type="hidden" name="avatar_remove">
                            <!--end::Inputs-->
                        </label>
                        <!--end::Edit-->
                        <!--begin::Cancel-->
                        <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="cancel" data-bs-toggle="tooltip" aria-label="Cancel avatar" data-bs-original-title="Cancel avatar" data-kt-initialized="1">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <!--end::Cancel-->
                        <!--begin::Remove-->
                        <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="remove" data-bs-toggle="tooltip" aria-label="Remove avatar" data-bs-original-title="Remove avatar" data-kt-initialized="1">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <!--end::Remove-->
                    </div>
                    <!--end::Image input-->
                </div>
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.name') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="text" class="form-control form-control-solid" name="name" value="{{old('name',$data->name ?? '')}}" placeholder="{{ __('lang.name') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.job_title') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="text" class="form-control form-control-solid" name="job_title" value="{{old('job_title',$data->job_title ?? '')}}" placeholder="{{ __('lang.job_title') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.email') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="email" class="form-control form-control-solid" name="email" value="{{old('email',$data->email ?? '')}}" placeholder="{{ __('lang.email') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.phone') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <!--begin::Input-->
                <input type="text" class="form-control form-control-solid" name="phone" value="{{old('phone',$data->phone ?? '')}}" placeholder="{{ __('lang.phone') }}"  />
                <!--end::Input-->
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.password') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">

                <div class="fv-row" data-kt-password-meter="true">
                    <div class="position-relative mb-3">
                        <input class="form-control form-control-lg form-control-solid"
                            type="password" name="password" value="" placeholder="{{ __('lang.password') }}" autocomplete="off" />

                        <!--begin::Visibility toggle-->
                        <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-n2"
                            data-kt-password-meter-control="visibility">
                                <i class="ki-duotone ki-eye-slash fs-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                <i class="ki-duotone ki-eye d-none fs-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        </span>
                        <!--end::Visibility toggle-->
                    </div>

                    <!--begin::Highlight meter-->
                    <div class="d-flex align-items-center mb-3" data-kt-password-meter-control="highlight">
                        <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                        <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                        <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                        <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px"></div>
                    </div>
                    <!--end::Highlight meter-->

                    <!--begin::Hint-->
                    <div class="text-muted">
                        {{ __('lang.password_hint') }}
                    </div>
                    <!--end::Hint-->
                </div>

            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.account_status') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <select class="form-select mb-2" data-control="select2" name="is_active" data-hide-search="true" data-placeholder="{{ __('lang.choose') }} ..." id="kt_ecommerce_add_category_store_template">
                    <option value="active" @if(isset($data->is_active) && $data->is_active == 'active') selected="selected" @endif >{{ __('lang.active') }}</option>
                    <option value="inactive" @if(isset($data->is_active) && $data->is_active == 'inactive') selected="selected" @endif>{{ __('lang.inactive') }}</option>
                    <option value="suspended" @if(isset($data->is_active) && $data->is_active == 'suspended') selected="selected" @endif>{{ __('lang.suspended') }}</option>
                </select>
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <!--begin::Label-->
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.role') }}</span>
                </label>
                <!--end::Label-->
            </div>
            <div class="col-md-9">
                <select class="form-select mb-2" data-control="select2" name="role_id" data-hide-search="true" data-placeholder="{{ __('lang.choose') }} ..." id="kt_ecommerce_add_category_store_template">
                    @foreach (Spatie\Permission\Models\Role::all() as $item=>$row)
                        <option value="{{$row->id}}" {{ ( isset($data->role_id) && $row->id == $data->role_id) ? 'selected' : '' }}>{{$row->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
    </div>
</div>








<script src="{{ URL::asset('dash/assets/plugins/custom/tinymce/tinymce.bundle.js')}}"></script>

<script>
    var options = {selector: "#kt_docs_tinymce_basic"};

    tinymce.init(options);

</script>
