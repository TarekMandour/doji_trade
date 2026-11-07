@extends('admin.layout.master')

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="text-gray-900 fw-bold my-1 fs-3">Crypto Spot Analysis</h1>
        <div class="text-muted fs-7 mt-2">Swing eligible assets only · Binance Spot USDT · Market Structure + EMA20/50</div>
    </div>
</div>
@endsection

@section('content')
<div class="content flex-column-fluid" id="kt_content">
    @if(session('message'))
        <div class="alert alert-{{ session('status') === 'error' ? 'danger' : 'success' }} mb-5">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-5"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-7">
        <div class="card-header"><h3 class="card-title">Swing Asset Synchronization</h3></div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
                <div>
                    <div class="fs-2 fw-bold">{{ number_format($assetCount) }}</div>
                    <div class="text-muted">Currently eligible USDT pairs</div>
                    <div class="text-muted fs-8 mt-2">24h quote volume &gt; 5,000,000 USDT · 4H mandatory + 2 of 1H/15M/5M</div>
                </div>
                <button type="button" class="btn btn-primary" id="start-swing-sync">Sync Spot Assets &amp; Apply Swing Filter</button>
            </div>
            <div id="swing-sync-progress" class="mt-7 d-none">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold" id="swing-sync-stage">Preparing...</span>
                    <span class="fw-bold" id="swing-sync-percent">0%</span>
                </div>
                <div class="progress h-8px mb-4"><div id="swing-sync-bar" class="progress-bar" role="progressbar" style="width:0%" aria-valuemin="0" aria-valuemax="100"></div></div>
                <div class="row g-4 text-center">
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="fs-3 fw-bold" id="swing-initial">0</div><div class="text-muted fs-8">Initial Assets</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="fs-3 fw-bold" id="swing-volume">0</div><div class="text-muted fs-8">Volume Qualified</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="fs-3 fw-bold" id="swing-analyzed">0</div><div class="text-muted fs-8">Analyzed</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="fs-3 fw-bold" id="swing-eligible">0</div><div class="text-muted fs-8">Eligible</div></div></div>
                </div>
                <div id="swing-sync-message" class="mt-3 text-muted fs-7"></div>
            </div>
        </div>
    </div>

    <div class="card mb-7">
        <div class="card-header"><h3 class="card-title">Create Crypto Watchlist</h3></div>
        <div class="card-body">
            @if($assets->isEmpty())
                <p class="text-muted mb-0">No Swing eligible assets are available. Run the synchronization above first.</p>
            @else
                <form method="POST" action="{{ route('admin.crypto.watchlists.store') }}">
                    @csrf
                    <div class="mb-5">
                        <label class="form-label required">Watchlist name</label>
                        <input name="name" class="form-control form-control-solid" maxlength="120" required value="{{ old('name') }}" placeholder="e.g. Swing Watchlist">
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
                        <label class="form-label required mb-0">Eligible pairs ({{ $assets->count() }})</label>
                        <input id="crypto-asset-search" class="form-control form-control-solid w-250px" placeholder="Filter symbols, e.g. BTC">
                        <button type="button" class="btn btn-sm btn-light-primary" id="crypto-select-visible">Select visible</button>
                        <button type="button" class="btn btn-sm btn-light" id="crypto-clear-selection">Clear selection</button>
                    </div>
                    <div class="border rounded p-4 mb-5" style="max-height: 320px; overflow-y: auto;">
                        <div class="row" id="crypto-assets-list">
                            @foreach($assets as $asset)
                                <div class="col-6 col-md-4 col-lg-3 mb-3 crypto-asset-item" data-symbol="{{ strtolower($asset->symbol) }}">
                                    <label class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input crypto-asset-checkbox" type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" {{ in_array($asset->id, old('asset_ids', [])) ? 'checked' : '' }}>
                                        <span class="form-check-label">{{ $asset->symbol }}</span>
                                        <span class="text-muted fs-8 ms-1">{{ number_format((float) ($swingVolumes[$asset->symbol] ?? 0) / 1000000, 1) }}M</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Watchlist</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-7">
        <div class="card-header"><h3 class="card-title">Crypto Watchlists</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle table-row-dashed gy-4">
                <thead><tr><th>Name</th><th>Pairs</th><th>Created</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @forelse($watchlists as $watchlist)
                    <tr>
                        <td class="fw-bold">{{ $watchlist->name }}</td><td>{{ $watchlist->assets_count }}</td><td>{{ $watchlist->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end"><form class="d-inline" method="POST" action="{{ route('admin.crypto.run', $watchlist->id) }}" onsubmit="return confirm('Start a new Binance Spot scan for this watchlist?');">@csrf<button type="submit" class="btn btn-sm btn-primary" {{ $watchlist->assets_count === 0 ? 'disabled' : '' }}>Scan</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-8">No crypto watchlists created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Scan Runs</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle table-row-dashed gy-4">
                <thead><tr><th>Started</th><th>Watchlist</th><th>Status</th><th>Processed</th><th>Accepted</th><th>Rejected / Errors</th><th></th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->started_at?->format('Y-m-d H:i') ?? $run->created_at?->format('Y-m-d H:i') }}</td><td>{{ $run->watchlist?->name ?? 'Deleted watchlist' }}</td>
                        <td><span class="badge badge-light-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($run->status) }}</span></td>
                        <td>{{ $run->processed_assets }} / {{ $run->total_assets }}</td><td>{{ $run->accepted_assets }}</td><td>{{ $run->rejected_assets }}</td>
                        <td class="text-end"><a href="{{ route('admin.crypto.show', $run->uuid) }}" class="btn btn-sm btn-light-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-8">No scan runs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('crypto-asset-search');
    const items = Array.from(document.querySelectorAll('.crypto-asset-item'));
    search?.addEventListener('input', function () {
        const value = this.value.toLowerCase().trim();
        items.forEach(item => item.style.display = item.dataset.symbol.includes(value) ? '' : 'none');
    });
    document.getElementById('crypto-select-visible')?.addEventListener('click', function () {
        items.filter(item => item.style.display !== 'none').forEach(item => { const checkbox = item.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = true; });
    });
    document.getElementById('crypto-clear-selection')?.addEventListener('click', function () {
        document.querySelectorAll('.crypto-asset-checkbox').forEach(checkbox => checkbox.checked = false);
    });

    const startButton = document.getElementById('start-swing-sync');
    const progressPanel = document.getElementById('swing-sync-progress');
    let pollHandle = null;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value ?? 0; };
    const renderRun = (run) => {
        progressPanel.classList.remove('d-none');
        const progress = Number(run.progress || 0);
        setText('swing-sync-stage', run.stage || run.status);
        setText('swing-sync-percent', progress + '%');
        setText('swing-initial', run.initial_assets);
        setText('swing-volume', run.volume_qualified);
        setText('swing-analyzed', run.analyzed_assets);
        setText('swing-eligible', run.eligible_assets);
        const bar = document.getElementById('swing-sync-bar');
        bar.style.width = progress + '%'; bar.setAttribute('aria-valuenow', progress);
        if (run.status === 'completed') {
            setText('swing-sync-message', 'Sync completed. The eligible list has been replaced successfully. Refreshing the page...');
            clearInterval(pollHandle); setTimeout(() => window.location.reload(), 1200);
        } else if (run.status === 'failed') {
            setText('swing-sync-message', run.error_message || 'Synchronization failed. The previous eligible list was preserved.');
            clearInterval(pollHandle); startButton.disabled = false;
        }
    };
    startButton?.addEventListener('click', async function () {
        startButton.disabled = true;
        try {
            const response = await fetch(@json(route('admin.crypto.swing-sync.start')), {
                method: 'POST', headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'}
            });
            const payload = await response.json();
            if (!response.ok && response.status !== 202) throw new Error(payload.message || 'Unable to start synchronization.');
            renderRun(payload.run);
            const runId = payload.run.id;
            pollHandle = setInterval(async () => {
                try {
                    const statusUrl = @json(route('admin.crypto.swing-sync.status', ['id' => 'RUN_ID'])).replace('RUN_ID', runId);
                    const statusResponse = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});
                    const statusPayload = await statusResponse.json();
                    renderRun(statusPayload.run);
                } catch (error) { setText('swing-sync-message', error.message); }
            }, 2500);
        } catch (error) {
            progressPanel.classList.remove('d-none'); setText('swing-sync-message', error.message); startButton.disabled = false;
        }
    });
});
</script>
@endsection
