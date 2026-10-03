<div class="row">
    <div class="col-lg-12">

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
                    <option></option>
                    <option value="active" @if(isset($data->is_active) && $data->is_active == 'active') selected="selected" @endif >{{ __('lang.active') }}</option>
                    <option value="inactive" @if(isset($data->is_active) && $data->is_active == 'inactive') selected="selected" @endif>{{ __('lang.inactive') }}</option>
                    <option value="suspended" @if(isset($data->is_active) && $data->is_active == 'suspended') selected="selected" @endif>{{ __('lang.suspended') }}</option>
                </select>
            </div>
        </div>
        
    </div>
</div>
