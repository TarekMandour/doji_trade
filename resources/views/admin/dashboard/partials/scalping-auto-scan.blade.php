{{-- Independent EMA Breakout Scalping automatic scan controls. --}}
<div class="card mt-7" id="scalping_auto_scan_card">
    <div class="card-header align-items-center py-5 gap-2">
        <div class="card-title align-items-start flex-column">
            <div class="d-flex align-items-center">
                <i class="bi bi-lightning-charge text-warning fs-2 me-3"></i>
                <h3 class="fw-bold my-0">Scalping Auto Scan</h3>
            </div>
            <span class="text-muted mt-2">EMA Breakout — EGX 30 وEGX 70 بالتناوب، فحص كل 5 دقائق</span>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-success fw-bold me-2" id="btn_scalping_auto_start">
                <i class="bi bi-play-fill fs-4 me-1"></i>Start Scalping
            </button>
            <button type="button" class="btn btn-danger fw-bold" id="btn_scalping_auto_stop">
                <i class="bi bi-stop-fill fs-4 me-1"></i>Stop Scalping
            </button>
        </div>
    </div>

    <div class="card-body pt-4">
        <div class="row g-5">
            <div class="col-md-4">
                <div class="text-muted mb-2">Status</div>
                <span class="badge badge-light-danger" id="scalping_auto_badge">Stopped</span>
            </div>
            <div class="col-md-4">
                <div class="text-muted mb-2">Next watchlist</div>
                <div class="fw-bold" id="scalping_auto_next_watchlist">EGX 30</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted mb-2">Next scan time</div>
                <div class="fw-bold" id="scalping_auto_next_at">—</div>
            </div>
            <div class="col-12">
                <div class="text-muted">
                    Independent from Intraday Auto Scan. Uses EmaBreakoutScalpingService and saves each run as an Analysis record with type <strong>scalping</strong>.
                </div>
                <div class="text-muted mt-2" id="scalping_auto_message" role="status"></div>
            </div>
        </div>
    </div>
</div>
