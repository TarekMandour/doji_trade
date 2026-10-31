@extends('admin.layout.master')

@php
    $route = 'admin.watchlists';
@endphp

@section('css')
@endsection

@section('style')
@endsection

@section('breadcrumb')
<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.watchlists') }}</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.watchlists') }}</a>
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

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.name') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="fw-bold fs-5">{{$data->name}}</div>
                </div>
            </div>

            <div class="row mb-8">
                <div class="col-xl-2">
                    <div class="fs-6 fw-semibold">{{ __('lang.stocks') }} :</div>
                </div>
                <div class="col-lg-9">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>{{ __('lang.symbol') }}</th>
                                    <th>{{ __('lang.name') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data->stocks as $stock)
                                    <tr>
                                        <td>{{ $stock->symbol }}</td>
                                        <td>{{ $stock->name }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2">{{ __('lang.no_data') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

@endsection

@section('script')
@endsection
