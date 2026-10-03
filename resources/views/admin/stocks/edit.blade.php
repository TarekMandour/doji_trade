@extends('admin.layout.master')

@php
    $route = 'admin.stocks';
    $viewPath = 'admin.stocks';
@endphp

@section('css')
@endsection

@section('style')
@endsection

@section('breadcrumb')
<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.stocks') }}</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.stocks') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">{{ __('lang.edit') }}</li>
        </ul>
    </div>
</div>
<!--end::Toolbar-->
@endsection

@section('content')

    <div class="card">
        <div class="card-body p-lg-10">
            <div class="col-lg-8 col-xl-8">
                <form action="{{route($route. '.update')}}" method="POST" enctype="multipart/form-data" class="form">
                    @csrf
                    <input type="hidden" name="id" value="{{$data->id}}" />

                    @include($viewPath. '.form')

                    <div class="card-footer d-flex justify-content-end py-6 px-9">
                        <button type="reset" class="btn btn-light me-3">{{ __('lang.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('lang.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
@endsection
