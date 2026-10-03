@extends('admin.layout.master')

@php
    $route = 'admin.admins';
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
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.administrators') }}</h1>
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
                <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.administrators') }}</a>
            </li>
            <!--end::Item-->
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">{{ __('lang.details') }}</li>
            <!--end::Item-->
        </ul>
        <!--end::Breadcrumb-->
    </div>
    <!--end::Page title-->
</div>
<!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">

    <div class="card">
        <!--begin::Card body-->
        <div class="card-body p-lg-10">

            <div class="row mb-8">
                <div class="col-xl-3">
                    <div class="symbol symbol-100px">
                        @if ($data->getMedia('profile')->count())
                        <img src="{{$data->getFirstMediaUrl('profile')}}" >
                        @else
                        <img src="{{asset('dash/assets/media/svg/avatars/blank.svg')}}" >
                        @endif
                    </div>
                </div>
                <div class="col-lg-8">
                    
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.name') }} :  </div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->name}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.phone') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->phone}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.email') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->email}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.account_status') }} :</div>
                </div>
                <div class="col-lg-9">
                    <!-- <div class="fw-bold fs-5">{{$data->is_active}}</div> -->
                    @if($data->is_active === '1')
                        <div class="badge badge-light-success fw-bold">{{ __('lang.active') }}</div>
                    @elseif($data->is_active === '2')
                        <div class="badge badge-light-info fw-bold">{{ __('lang.suspended') }}</div>
                    @else 
                        <div class="badge badge-light-danger fw-bold">{{ __('lang.inactive') }}</div>
                    @endif 
                </div>
            </div>

        </div>
        <!--end::Card body-->
    </div>

</div>

@endsection

@section('script')
@endsection