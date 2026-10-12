@extends('admin.layout.master')

@section('breadcrumb')
<div class="toolbar mb-5 mb-lg-7">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="text-gray-900 fw-bold my-1 fs-3">Crypto Scan Results</h1>
        <div class="text-muted fs-7 mt-2">{{ $run->watchlist?->name ?? 'Deleted watchlist' }} · {{ $run->uuid }}</div>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('admin.crypto.index') }}" class="btn btn-light">Back</a>
        <form method="POST" action="{{ route('admin.crypto.destroy', $run->uuid) }}" onsubmit="return confirm('Delete this scan's single JSON file and its database records?');">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Delete Scan</button>
        </form>
    </div>
</div>
@endsection

@section('content')
<div class="content flex-column-fluid" id="kt_content">
    @if(session('message'))
        <div class="alert alert-success mb-5">{{ session('message') }}</div>
    @endif
    <div class="row g-5 mb-7">
        <div class="col-md-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted">Status</div><div class="fs-3 fw-bold">{{ ucfirst($run->status) }}</div></div></div></div>
        <div class="col-md-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted">Pairs processed</div><div class="fs-3 fw-bold">{{ $run->processed_assets }} / {{ $run->total_assets }}</div></div></div></div>
        <div class="col-md-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted">Accepted</div><div class="fs-3 fw-bold text-success">{{ $run->accepted_assets }}</div></div></div></div>
        <div class="col-md-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted">Rejected / Errors</div><div class="fs-3 fw-bold text-danger">{{ $run->rejected_assets }}</div></div></div></div>
    </div>
    @if($run->error_message)
        <div class="alert alert-danger">{{ $run->error_message }}</div>
    @endif
    <div class="card">
        <div class="card-header"><h3 class="card-title">Analysis</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle table-row-dashed gy-4">
                <thead>
                    <tr><th>Pair</th><th>State</th><th>Signal</th><th>Entry</th><th>Stop Loss</th><th>Target 1</th><th>Target 2</th><th>Opportunity</th><th>Risk</th><th>Confidence</th><th>Reason</th></tr>
                </thead>
                <tbody>
                @forelse($results as $result)
                    <tr>
                        <td class="fw-bold">{{ $result->asset?->symbol ?? 'Unknown' }}</td>
                        <td><span class="badge badge-light-{{ $result->state === 'accepted' ? 'success' : ($result->state === 'error' ? 'danger' : 'warning') }}">{{ ucfirst($result->state) }}</span></td>
                        <td>{{ str($result->signal_type)->replace('_', ' ')->title() }}</td>
                        <td>{{ $result->entry_price ?? '—' }}</td>
                        <td>{{ $result->stop_loss ?? '—' }}</td>
                        <td>{{ $result->target_1 ?? '—' }}</td>
                        <td>{{ $result->target_2 ?? '—' }}</td>
                        <td>{{ $result->opportunity_score ?? '—' }}</td>
                        <td>{{ $result->risk_score ?? '—' }}</td>
                        <td>{{ $result->confidence_score ?? '—' }}</td>
                        <td style="min-width: 260px; white-space: normal;">{{ $result->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-8">No results recorded for this scan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="alert alert-warning mt-5 mb-0">
        Signals are analytical output, not a promise of profit. Bearish signals are informational only; this module does not place orders and does not short Spot assets.
    </div>
</div>
@endsection
