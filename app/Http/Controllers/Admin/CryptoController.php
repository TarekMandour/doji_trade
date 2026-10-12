<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CryptoAsset;
use App\Models\CryptoScanRun;
use App\Models\CryptoWatchlist;
use App\Services\Crypto\CryptoJsonStorageService;
use App\Services\Crypto\CryptoScanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CryptoController extends Controller
{
    public function index()
    {
        return view('admin.crypto.index', [
            'watchlists' => CryptoWatchlist::withCount('assets')->latest()->get(),
            'assets' => CryptoAsset::where('status', 'TRADING')->where('quote_asset', 'USDT')->orderBy('symbol')->limit(500)->get(),
            'runs' => CryptoScanRun::with('watchlist')->latest()->limit(30)->get(),
            'assetCount' => CryptoAsset::where('status', 'TRADING')->where('quote_asset', 'USDT')->count(),
        ]);
    }

    public function syncAssets(CryptoScanService $service)
    {
        try {
            $count = $service->syncSpotAssets();
            return back()->with('message', "Binance Spot assets synchronized: {$count} USDT pairs.")->with('status', 'success');
        } catch (Throwable $e) {
            report($e);
            return back()->with('message', 'Unable to synchronize Binance Spot assets: ' . $e->getMessage())->with('status', 'error');
        }
    }

    public function storeWatchlist(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', 'distinct', Rule::exists('crypto_assets', 'id')->where('status', 'TRADING')],
        ]);

        $watchlist = CryptoWatchlist::create([
            'name' => $data['name'],
            'user_id' => auth('admin')->id(),
        ]);

        $pivot = [];
        foreach (array_values($data['asset_ids']) as $position => $id) {
            $pivot[$id] = ['position' => $position];
        }
        $watchlist->assets()->sync($pivot);

        return redirect()->route('admin.crypto.index')->with('message', 'Crypto watchlist created.')->with('status', 'success');
    }

    public function run(int $id, CryptoScanService $service)
    {
        try {
            $run = $service->run($id);
            return redirect()->route('admin.crypto.show', $run->uuid)
                ->with('message', 'Crypto Spot scan completed.')->with('status', 'success');
        } catch (Throwable $e) {
            report($e);
            return back()->with('message', 'Crypto scan failed: ' . $e->getMessage())->with('status', 'error');
        }
    }

    public function show(string $uuid, CryptoJsonStorageService $storage)
    {
        $run = CryptoScanRun::with(['watchlist', 'results.asset'])->where('uuid', $uuid)->firstOrFail();
        $json = $run->results_path ? $storage->read($run->results_path) : null;

        return view('admin.crypto.show', [
            'run' => $run,
            'results' => $run->results()->with('asset')->orderByRaw("CASE state WHEN 'accepted' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
                ->orderByDesc('opportunity_score')->get(),
            'jsonResults' => $json['results'] ?? [],
        ]);
    }

    public function destroy(string $uuid, CryptoJsonStorageService $storage)
    {
        $run = CryptoScanRun::where('uuid', $uuid)->firstOrFail();
        $filePath = $run->results_path ?: $run->metadata_path;

        // Delete only this run's single JSON file; no shared candle cache is used or touched.
        if ($filePath) {
            $storage->deleteScanFile($filePath);
        }
        $run->delete();

        return redirect()->route('admin.crypto.index')->with('message', 'Scan JSON file and its database records deleted.')->with('status', 'success');
    }
}
