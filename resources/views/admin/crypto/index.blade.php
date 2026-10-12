@extends('admin.layout.master')

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="text-gray-900 fw-bold my-1 fs-3">Crypto Spot Analysis</h1>
        <div class="text-muted fs-7 mt-2">Binance Spot · 1h / 4h / 1d · Trade-based 24-hour Volume Profile</div>
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
        <div class="card-header"><h3 class="card-title">Binance Spot Assets</h3></div>
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div>
                <div class="fs-2 fw-bold">{{ number_format($assetCount) }}</div>
                <div class="text-muted">Tradable USDT pairs synced</div>
            </div>
            <form method="POST" action="{{ route('admin.crypto.sync-assets') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Sync Spot Assets</button>
            </form>
        </div>
    </div>

    <div class="card mb-7">
        <div class="card-header"><h3 class="card-title">Create Crypto Watchlist</h3></div>
        <div class="card-body">
            @if($assets->isEmpty())
                <p class="text-muted mb-0">Click “Sync Spot Assets” first to load available Binance USDT pairs.</p>
            @else
                <form method="POST" action="{{ route('admin.crypto.watchlists.store') }}">
                    @csrf
                    <div class="mb-5">
                        <label class="form-label required">Watchlist name</label>
                        <input name="name" class="form-control form-control-solid" maxlength="120" required value="{{ old('name') }}" placeholder="e.g. Liquid USDT pairs">
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
                        <label class="form-label required mb-0">Select pairs (up to {{ $assets->count() }} shown)</label>
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
                        <td class="fw-bold">{{ $watchlist->name }}</td>
                        <td>{{ $watchlist->assets_count }}</td>
                        <td>{{ $watchlist->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <form class="d-inline" method="POST" action="{{ route('admin.crypto.run', $watchlist->id) }}" onsubmit="return confirm('Start a new Binance Spot scan for this watchlist?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary" {{ $watchlist->assets_count === 0 ? 'disabled' : '' }}>Scan</button>
                            </form>
                        </td>
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
                        <td>{{ $run->started_at?->format('Y-m-d H:i') ?? $run->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $run->watchlist?->name ?? 'Deleted watchlist' }}</td>
                        <td><span class="badge badge-light-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($run->status) }}</span></td>
                        <td>{{ $run->processed_assets }} / {{ $run->total_assets }}</td>
                        <td>{{ $run->accepted_assets }}</td>
                        <td>{{ $run->rejected_assets }}</td>
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
        items.filter(item => item.style.display !== 'none').forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = true;
        });
    });
    document.getElementById('crypto-clear-selection')?.addEventListener('click', function () {
        document.querySelectorAll('.crypto-asset-checkbox').forEach(checkbox => checkbox.checked = false);
    });
});
</script>
@endsection
