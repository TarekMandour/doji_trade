@extends('admin.layout.master')

@section('css')
@endsection

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.home') }}</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <li class="breadcrumb-item text-gray-600">{{ __('lang.home') }}</li>
        </ul>
    </div>
    <div class="d-flex align-items-center fs-7 text-gray-500 py-2">
        <i class="bi bi-calendar3 me-2"></i>{{ now()->format('l, d M Y') }}
    </div>
</div>
@endsection

@section('content')
<div class="content flex-column-fluid" id="kt_content">

    <!--begin::Thndr token control-->
    <div class="card">
        <!--begin::Header-->
        <div class="card-header align-items-center py-5 gap-2 gap-md-5">
            <div class="card-title align-items-start flex-column">
                <div class="d-flex align-items-center">
                    <i class="bi bi-droplet-half text-primary fs-2 me-3"></i>
                    <h3 class="fw-bold my-0">{{ __('lang.thndr_title') }}</h3>
                </div>
                <span class="text-muted mt-2 ms-8">{{ __('lang.thndr_subtitle') }}</span>
            </div>
            <div class="card-toolbar">
                <button type="button" class="btn btn-success fw-bold me-2" id="btn_thndr_start" data-token="{{ csrf_token() }}">
                    <i class="bi bi-play-fill fs-4 me-1"></i>{{ __('lang.thndr_start') }}
                </button>
                <button type="button" class="btn btn-danger fw-bold" id="btn_thndr_stop" data-token="{{ csrf_token() }}">
                    <i class="bi bi-stop-fill fs-4 me-1"></i>{{ __('lang.thndr_stop') }}
                </button>
            </div>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body pt-4">
            <div class="row g-7">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge badge-light-primary me-3" id="thndr_badge">
                            {{ $captureEnabled ? __('lang.thndr_capture_on') : __('lang.thndr_capture_off') }}
                        </span>
                        <span class="text-muted fw-semibold">{{ __('lang.thndr_token_status') }}</span>
                    </div>
                    <div class="fs-2 fw-bold text-gray-900" id="thndr_token_display">
                        @if($tokenMasked)
                            {{ $tokenMasked }}
                            <span class="fs-7 text-muted fw-normal">({{ $tokenUpdatedAt }})</span>
                        @else
                            <span class="text-danger">—</span>
                        @endif
                    </div>
                    <p class="text-muted mb-0 mt-4">{{ __('lang.thndr_capture_hint') }}</p>
                </div>
                <div class="col-md-6 d-flex flex-column justify-content-center align-items-md-end">
                    <span class="text-muted">{{ __('lang.thndr_capture_state') }}</span>
                    <span class="fw-bold fs-4" id="thndr_state_text">
                        {{ $captureEnabled ? __('lang.thndr_capture_on') : __('lang.thndr_capture_off') }}
                    </span>
                </div>
            </div>
        </div>
        <!--end::Body-->
    </div>
    <!--end::Thndr token control-->

    @include('admin.dashboard.partials.intraday-auto-scan')

</div>{{-- end kt_content --}}
@endsection

@section('script')
<script>
    jQuery(function ($) {
        var thndrEnabled = @json($captureEnabled);

        function thndrRender(res) {
            thndrEnabled = !!(res.enabled);
            $("#btn_thndr_start").prop("disabled", thndrEnabled);
            $("#btn_thndr_stop").prop("disabled", !thndrEnabled);

            var onText  = "{{ __('lang.thndr_capture_on') }}";
            var offText = "{{ __('lang.thndr_capture_off') }}";

            var stateText = thndrEnabled ? onText : offText;
            $("#thndr_state_text").text(stateText);

            $("#thndr_badge").removeClass("badge-light-primary");
            $("#thndr_badge")
                .toggleClass("badge-light-success", thndrEnabled)
                .toggleClass("badge-light-danger", !thndrEnabled)
                .text(stateText);

            if (res.tokenMasked) {
                var updated = res.tokenUpdatedAt ? " (" + res.tokenUpdatedAt + ")" : "";
                $("#thndr_token_display").html(res.tokenMasked + '<span class="fs-7 text-muted fw-normal">' + updated + '</span>');
            }
        }

        function thndrSet(action) {
            var token = $(this).data("token");
            var url = action === "start"
                ? "{{ route('admin.thndr.start') }}"
                : "{{ route('admin.thndr.stop') }}";

            $.ajax({
                url: url,
                type: "post",
                dataType: "json",
                data: { "_token": token },
                success: function (res) {
                    if (res.ok) {
                        thndrRender(res);
                        thndrPoll();
                    }
                },
                error: function () {
                    toastr.error("", "{{ __('lang.thndr_toggle_failed') }}");
                }
            });
        }

        // بعد التشغيل نبعت poll كل 3 ثواني لحد ما التوكن الجديد يوصل (30 ثانية كحد أقصى)
        function thndrPoll() {
            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                $.getJSON("{{ route('admin.thndr.status') }}", function (res) {
                    thndrRender(res);
                    if (tries >= 10) {
                        clearInterval(timer);
                    }
                });
            }, 3000);
        }

        thndrRender({
            enabled: thndrEnabled,
            tokenMasked: {!! $tokenMasked ? json_encode($tokenMasked) : 'null' !!},
            tokenUpdatedAt: {!! $tokenUpdatedAt ? json_encode($tokenUpdatedAt) : 'null' !!}
        });

        $("#btn_thndr_start").click(function () { thndrSet.call(this, "start"); });
        $("#btn_thndr_stop").click(function () { thndrSet.call(this, "stop"); });
    });
</script>
<script>
jQuery(function ($) {
    const statusUrl = @json(route('admin.intraday-auto-scan.status'));
    const startUrl = @json(route('admin.intraday-auto-scan.start'));
    const stopUrl = @json(route('admin.intraday-auto-scan.stop'));
    const csrfToken = @json(csrf_token());

    function renderAutoScan(data) {
        const enabled = !!data.enabled;

        $('#btn_intraday_auto_start').prop('disabled', enabled);
        $('#btn_intraday_auto_stop').prop('disabled', !enabled);

        $('#intraday_auto_badge')
            .toggleClass('badge-light-success', enabled)
            .toggleClass('badge-light-danger', !enabled)
            .text(enabled ? 'Running' : 'Stopped');

        $('#intraday_auto_next_watchlist').text(data.nextWatchlist || '—');
        $('#intraday_auto_next_at').text(data.nextAt || '—');
    }

    function refreshAutoScan() {
        $.getJSON(statusUrl)
            .done(renderAutoScan)
            .fail(function () {
                $('#intraday_auto_message').text('Could not retrieve auto-scan status.');
            });
    }

    function sendAutoScanAction(action) {
        const url = action === 'start' ? startUrl : stopUrl;
        $('#intraday_auto_message').text('Please wait…');

        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: { _token: csrfToken }
        }).done(function (data) {
            renderAutoScan(data);
            $('#intraday_auto_message').text(
                action === 'start'
                    ? 'Automatic scan enabled. The first EGX 30 scan is due immediately.'
                    : 'Automatic scan disabled. A scan already in progress may finish.'
            );
        }).fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Could not change auto-scan state.';
            $('#intraday_auto_message').text(message);
        });
    }

    $('#btn_intraday_auto_start').on('click', function () {
        sendAutoScanAction('start');
    });

    $('#btn_intraday_auto_stop').on('click', function () {
        sendAutoScanAction('stop');
    });

    refreshAutoScan();
    setInterval(refreshAutoScan, 15000);
});
</script>
@endsection