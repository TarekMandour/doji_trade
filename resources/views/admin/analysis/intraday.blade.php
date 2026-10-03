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
            <li class="breadcrumb-item text-gray-600">{{ __('lang.run_analysis') }}</li>
        </ul>
    </div>
</div>
<!--end::Toolbar-->
@endsection

@section('content')

   <div class="card">
        <div class="card-body p-lg-10">
            <div class="col-lg-8 col-xl-8">
                <form action="{{route($route. '.store-intraday')}}" method="POST" class="form">
                    @csrf

                    <div class="row fv-row mb-7">
                        <div class="col-md-3 text-md-end">
                            <label class="fs-6 fw-semibold form-label mt-3">{{ __('lang.title') }}</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-solid" name="title" value="{{old('title')}}" placeholder="{{ __('lang.title') }}" />
                        </div>
                    </div>

                    <div class="row fv-row mb-7">
                        <div class="col-md-3 text-md-end">
                            <label class="fs-6 fw-semibold form-label mt-3">
                                <span class="required">{{ __('lang.watchlist') }}</span>
                            </label>
                        </div>
                        <div class="col-md-9">
                            <select class="form-select" data-control="select2" name="watchlist_id" data-placeholder="{{ __('lang.choose') }}" id="kt_watchlist_select">
                                <option></option>
                                @foreach($watchlists as $watchlist)
                                    <option value="{{ $watchlist->id }}" {{ old('watchlist_id') == $watchlist->id ? 'selected' : '' }}>
                                        {{ $watchlist->name }} ({{ $watchlist->stocks_count }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row fv-row mb-7">
                        <div class="col-md-3 text-md-end">
                            <label class="fs-6 fw-semibold form-label mt-3">
                                <span class="required">{{ __('lang.timeframe') }}</span>
                            </label>
                        </div>
                        <div class="col-md-9">
                            <select class="form-select" data-control="select2" name="resolution" data-placeholder="{{ __('lang.choose') }}" id="kt_resolution_select">
                                @foreach($resolutions as $key => $label)
                                    <option value="{{ $key }}" {{ old('resolution', '1D') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row fv-row mb-7">
                        <div class="col-md-3 text-md-end">
                            <label class="fs-6 fw-semibold form-label mt-3">
                                <span class="required">{{ __('lang.candles_count') }}</span>
                            </label>
                        </div>
                        <div class="col-md-9">
                            <input type="number" min="10" max="2000" class="form-control form-control-solid" name="candles_count" value="{{old('candles_count', 90)}}" placeholder="90" />
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end py-6 px-9">
                        <button type="reset" class="btn btn-light me-3">{{ __('lang.cancel') }}</button>
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
    $('#kt_resolution_select').select2({ placeholder: "{{ __('lang.choose') }}" });
</script>
@endsection
