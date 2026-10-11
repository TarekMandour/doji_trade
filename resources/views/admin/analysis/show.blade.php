@extends('admin.layout.master')

@php
    $route = 'admin.analysis';
@endphp

@section('css')
@endsection

@section('style')
@endsection

@section('breadcrumb')
<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.analysis') }}</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.analysis') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">{{ __('lang.details') }}</li>
        </ul>
    </div>
</div>
<!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">

    <div class="card mb-6">
        <div class="card-body p-lg-10">

            <div class="row mb-4">
                <div class="col-xl-2"><div class="fs-6 fw-semibold">{{ __('lang.title') }} :</div></div>
                <div class="col-lg-9"><div class="fw-bold fs-5">{{ $data->title }}</div></div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2"><div class="fs-6 fw-semibold">{{ __('lang.watchlist') }} :</div></div>
                <div class="col-lg-9"><div class="fw-bold fs-5">{{ $data->watchlist->name ?? '-' }}</div></div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2"><div class="fs-6 fw-semibold">{{ __('lang.analysis_date') }} :</div></div>
                <div class="col-lg-9"><div class="fw-bold fs-5">{{ $data->analysis_date->format('F j, Y') }}</div></div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2"><div class="fs-6 fw-semibold">{{ __('lang.stocks_count') }} :</div></div>
                <div class="col-lg-9"><div class="fw-bold fs-5">{{ $data->stocks_count }}</div></div>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('lang.analysis_results') }}</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-3">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>{{ __('lang.symbol') }}</th>
                            <th>{{ __('lang.name') }}</th>
                            <th>{{ __('lang.status') }}</th>
                            @if(($data->type ?? '') === 'fixed_volume_profile')
                                <th>POC</th>
                                <th>VAH</th>
                                <th>VAL</th>
                                <th>{{ __('lang.entry_zone') }}</th>
                                <th>{{ __('lang.stop_loss') }}</th>
                                <th>Target 1</th>
                                <th>Target 2</th>
                                <th>{{ __('lang.reason') }}</th>
                            @else
                                <th>{{ __('lang.trend') }}</th>
                                <th>{{ __('lang.opportunity_score') }}</th>
                                <th>{{ __('lang.entry_zone') }}</th>
                                <th>{{ __('lang.stop_loss') }}</th>
                                <th>{{ __('lang.target') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $stock)
                            <tr>
                                <td>{{ $stock['symbol'] ?? $stock['symbol_code'] ?? '-' }}</td>
                                <td>{{ $stock['name'] ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ ($stock['status'] ?? '') === 'QUALIFIED' ? 'badge-light-success' : (($stock['status'] ?? '') === 'NO_SIGNAL' ? 'badge-light-warning' : 'badge-light-danger') }}">
                                        {{ $stock['status'] ?? '-' }}
                                    </span>
                                </td>
                                @if(($data->type ?? '') === 'fixed_volume_profile')
                                    <td>{{ $stock['poc'] ?? '-' }}</td>
                                    <td>{{ $stock['vah'] ?? '-' }}</td>
                                    <td>{{ $stock['val'] ?? '-' }}</td>
                                    <td>{{ $stock['entry'] ?? '-' }}</td>
                                    <td>{{ $stock['stop_loss'] ?? '-' }}</td>
                                    <td>{{ $stock['target_1'] ?? '-' }}</td>
                                    <td>{{ $stock['target_2'] ?? '-' }}</td>
                                    <td>{{ $stock['reason'] ?? '-' }}</td>
                                @else
                                    <td>{{ $stock['trend'] ?? '-' }}</td>
                                    <td>{{ $stock['opportunity_score'] ?? '-' }}</td>
                                    <td>{{ $stock['entry'] ?? $stock['entry_price'] ?? $stock['entry_low'] ?? '-' }}</td>
                                    <td>{{ $stock['stop_loss'] ?? '-' }}</td>
                                    <td>{{ $stock['target_1'] ?? $stock['target1'] ?? '-' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($data->type ?? '') === 'fixed_volume_profile' ? 11 : 8 }}">{{ __('lang.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@section('script')
@endsection
