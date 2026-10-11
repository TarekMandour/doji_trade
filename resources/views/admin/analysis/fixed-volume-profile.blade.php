@extends('admin.layout.master')

@php
    $route = 'admin.analysis';
@endphp

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">Fixed Volume Profile</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">
                <a href="{{ route($route.'.index') }}" class="text-gray-600 text-hover-primary">{{ __('lang.analysis') }}</a>
            </li>
            <li class="breadcrumb-item text-gray-600">Fixed Volume Profile</li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-lg-10">
        <div class="alert alert-warning">
            The profile is an estimate calculated from Thndr 1-minute OHLCV candles. It is not true trade-level volume-at-price data.
            The service builds the profile from the first 30 minutes of the EGX session and checks breakout, VWAP, volume, and retest on completed 5-minute candles.
        </div>

        <div class="col-lg-8 col-xl-8">
            <form action="{{ route($route.'.store-fixed-volume-profile') }}" method="POST" class="form">
                @csrf
                <input type="hidden" name="resolution" value="1MIN">
                <input type="hidden" name="candles_count" value="30">

                <div class="row fv-row mb-7">
                    <div class="col-md-3 text-md-end">
                        <label class="fs-6 fw-semibold form-label mt-3">{{ __('lang.title') }}</label>
                    </div>
                    <div class="col-md-9">
                        <input type="text" class="form-control form-control-solid" name="title" value="{{ old('title') }}" placeholder="Fixed Volume Profile" />
                    </div>
                </div>

                <div class="row fv-row mb-7">
                    <div class="col-md-3 text-md-end">
                        <label class="fs-6 fw-semibold form-label mt-3">
                            <span class="required">{{ __('lang.watchlist') }}</span>
                        </label>
                    </div>
                    <div class="col-md-9">
                        <select class="form-select" data-control="select2" name="watchlist_id" data-placeholder="{{ __('lang.choose') }}" id="kt_watchlist_select" required>
                            <option></option>
                            @foreach($watchlists as $watchlist)
                                <option value="{{ $watchlist->id }}" {{ old('watchlist_id') == $watchlist->id ? 'selected' : '' }}>
                                    {{ $watchlist->name }} ({{ $watchlist->stocks_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <a href="{{ route($route.'.index') }}" class="btn btn-light me-3">{{ __('lang.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('lang.run_analysis') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $('#kt_watchlist_select').select2({ placeholder: "{{ __('lang.choose') }}" });
</script>
@endsection
