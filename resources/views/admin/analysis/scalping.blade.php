@extends('admin.layout.master')

@php
    $route = 'admin.analysis';
@endphp

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">Run Scalping Analysis</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600"><a href="{{ route('admin.dashboard') }}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a></li>
            <li class="breadcrumb-item text-gray-600"><a href="{{ route($route.'.index') }}" class="text-gray-600 text-hover-primary">{{ __('lang.analysis') }}</a></li>
            <li class="breadcrumb-item text-gray-600">Run Scalping</li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-lg-10">
        <div class="col-lg-8 col-xl-8">
            <form action="{{ route($route.'.store-scalping') }}" method="POST" class="form">
                @csrf
                <div class="row fv-row mb-7">
                    <div class="col-md-3 text-md-end"><label class="fs-6 fw-semibold form-label mt-3">{{ __('lang.title') }}</label></div>
                    <div class="col-md-9"><input type="text" class="form-control form-control-solid" name="title" value="{{ old('title') }}" placeholder="EMA Breakout Scalping" /></div>
                </div>
                <div class="row fv-row mb-7">
                    <div class="col-md-3 text-md-end"><label class="fs-6 fw-semibold form-label mt-3"><span class="required">{{ __('lang.watchlist') }}</span></label></div>
                    <div class="col-md-9">
                        <select class="form-select" data-control="select2" name="watchlist_id" data-placeholder="{{ __('lang.choose') }}" id="kt_scalping_watchlist" required>
                            <option></option>
                            @foreach($watchlists as $watchlist)
                                <option value="{{ $watchlist->id }}" {{ old('watchlist_id') == $watchlist->id ? 'selected' : '' }}>
                                    {{ $watchlist->name }} ({{ $watchlist->stocks_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="alert alert-light-info d-flex align-items-start p-5 mb-7">
                    <i class="ki-duotone ki-information-5 fs-2x text-info me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div>
                        <div class="fw-bold mb-1">EMA Breakout – Scalping</div>
                        <div class="fs-7">Trend: 15m OR 1h · Entry scan: 1m AND 5m independently · EMA 20/34/50 · Volume SMA 20 · Stochastic 14,3,3 · Targets 1.5R / 2R (50% each).</div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end py-6 px-0">
                    <a href="{{ route($route.'.index') }}" class="btn btn-light me-3">{{ __('lang.cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="ki-duotone ki-flash fs-2"></i> Start Scalping Scan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $('#kt_scalping_watchlist').select2({ placeholder: @json(__('lang.choose')) });
</script>
@endsection
