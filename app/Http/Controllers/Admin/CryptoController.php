<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CryptoAsset;
use App\Models\CryptoScanRun;
use App\Models\CryptoWatchlist;
use App\Services\Crypto\CryptoJsonStorageService;
use App\Services\Crypto\CryptoScanService;
use App\Services\Crypto\SwingEligibilityJsonStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CryptoController extends Controller
{
    public function index(SwingEligibilityJsonStorage $swing)
    {
        $snapshot = $swing->readAssets();
        $symbols = collect($snapshot['eligible_assets'] ?? [])->pluck('symbol')->filter()->values();
        $assets = CryptoAsset::whereIn('symbol', $symbols)->where('status', 'TRADING')->orderBy('symbol')->get();
        return view('admin.crypto.index', ['watchlists' => CryptoWatchlist::withCount('assets')->latest()->get(), 'assets' => $assets, 'runs' => CryptoScanRun::with('watchlist')->latest()->limit(30)->get(), 'assetCount' => $assets->count(), 'swingVolumes' => collect($snapshot['eligible_assets'] ?? [])->keyBy('symbol')->map(fn($row) => (float) ($row['volume_24h_usdt'] ?? 0)), 'swingProgress' => $swing->readProgress()]);
    }
    public function syncAssets()
    {
        return redirect()->route('admin.crypto.index')->with('message', 'Use the Swing Filter sync button.')->with('status', 'info');
    }
    public function storeWatchlist(Request $request, SwingEligibilityJsonStorage $swing)
    {
        $symbols = collect($swing->readAssets()['eligible_assets'] ?? [])->pluck('symbol')->filter()->values();
        $eligibleIds = CryptoAsset::whereIn('symbol', $symbols)->where('status', 'TRADING')->pluck('id')->all();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'asset_ids' => ['required', 'array', 'min:1'], 'asset_ids.*' => ['integer', 'distinct', Rule::in($eligibleIds)]]);
        $watchlist = CryptoWatchlist::create(['name' => $data['name'], 'user_id' => auth('admin')->id()]);
        $pivot = [];
        foreach (array_values($data['asset_ids']) as $position => $id)
            $pivot[$id] = ['position' => $position];
        $watchlist->assets()->sync($pivot);
        return redirect()->route('admin.crypto.index')->with('message', 'Crypto watchlist created.')->with('status', 'success');
    }
    public function run(int $id, CryptoScanService $service)
    {
        try {
            $run = $service->run($id);
            return redirect()->route('admin.crypto.show', $run->uuid)->with('message', 'Crypto Spot scan completed.')->with('status', 'success');
        } catch (Throwable $e) {
            report($e);
            return back()->with('message', 'Crypto scan failed: ' . $e->getMessage())->with('status', 'error');
        }
    }
    public function show(string $uuid, CryptoJsonStorageService $storage)
    {
        $run = CryptoScanRun::with(['watchlist', 'results.asset'])->where('uuid', $uuid)->firstOrFail();
        $json = $run->results_path ? $storage->read($run->results_path) : null;
        $all = $run->results()->with('asset')->orderByRaw("CASE state WHEN 'accepted' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")->orderByDesc('opportunity_score')->get();
        $accepted = $all->where('state', 'accepted')->values();
        return view('admin.crypto.show', ['run' => $run, 'results' => $accepted->isNotEmpty() ? $accepted : $all, 'hasConfirmedEntry' => $accepted->isNotEmpty(), 'jsonResults' => $json['results'] ?? []]);
    }
    public function destroy(string $uuid, CryptoJsonStorageService $storage)
    {
        $run = CryptoScanRun::where('uuid', $uuid)->firstOrFail();
        $path = $run->results_path ?: $run->metadata_path;
        if ($path)
            $storage->deleteScanFile($path);
        $run->delete();
        return redirect()->route('admin.crypto.index')->with('message', 'Scan JSON file and database records deleted.')->with('status', 'success');
    }
}
