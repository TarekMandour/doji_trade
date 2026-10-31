@extends('admin.layout.master')

@php
    $route = 'admin.pages';
    $viewPath = 'admin.pages';
@endphp

@section('css')
@endsection

@section('style')
    
@endsection

@section('breadcrumb')
<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <!--begin::Page title-->
    <div class="page-title d-flex flex-column me-3">
        <!--begin::Title-->
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.pages') }}</h1>
        <!--end::Title-->
        <!--begin::Breadcrumb-->
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
            </li>
            <!--end::Item-->
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.pages') }}</a>
            </li>
            <!--end::Item-->
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">{{ __('lang.edit') }}</li>
            <!--end::Item-->
        </ul>
        <!--end::Breadcrumb-->
    </div>
    <!--end::Page title-->
</div>
<!--end::Toolbar-->
@endsection

@section('content')

    <div class="card">
        <!--begin::Card body-->
        <div class="card-body p-lg-10">
            <div class="col-lg-8 col-xl-8">
                <!--begin::Form-->
                <form action="{{route($route. '.update')}}" method="POST" enctype="multipart/form-data" id="kt_account_profile_details_form" class="form">
                    @csrf
                    <input type="hidden" name="id" value="{{$data->id}}" />

                    @include($viewPath. '.form')

                    <div class="card-footer d-flex justify-content-end py-6 px-9">
                        <button type="reset" data-kt-ecommerce-settings-type="cancel" class="btn btn-light me-3">{{ __('lang.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" id="kt_account_profile_details_submit">{{ __('lang.save') }}</button>
                    </div>
                    <!--end::Actions-->
                </form>

            </div>
        </div>
    </div>

@endsection

@section('script')

@endsection