@extends('admin.layout.master')

@php
    $route = 'admin.stocks';
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
            <li class="breadcrumb-item text-gray-600">{{ __('lang.details') }}</li>
        </ul>
    </div>
</div>
<!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">

    <div class="card">
        <div class="card-body p-lg-10">

            @if($data->logo)
                <div class="row mb-8">
                    <div class="col-xl-2">
                        <div class="fs-6 fw-semibold">{{ __('lang.logo') }} :</div>
                    </div>
                    <div class="col-lg-9">
                        <img src="{{ $data->logo_url }}" alt="{{ $data->name }}" style="max-height:80px" />
                    </div>
                </div>
            @endif

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.symbol') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->symbol}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.name') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->name}} @if($data->arabic_name) / {{$data->arabic_name}} @endif</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.market') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->market}} @if($data->exchange) - {{$data->exchange}} @endif</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.industry') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->industry}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.status') }} :</div>
                </div>
                <div class="col-lg-9">
                    <span class="badge badge-light-{{ $data->is_tradable ? 'success' : 'danger' }} me-2">{{ $data->is_tradable ? __('lang.active') : __('lang.inactive') }}</span>
                    @if($data->is_egx30)<span class="badge badge-light-primary me-2">EGX30</span>@endif
                    @if($data->is_egx70)<span class="badge badge-light-primary me-2">EGX70</span>@endif
                    @if($data->is_egx100)<span class="badge badge-light-primary me-2">EGX100</span>@endif
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.description') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->description}}</div>
                </div>
            </div>

        </div>
    </div>

</div>

@endsection

@section('script')
@endsection
