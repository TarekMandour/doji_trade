<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Jobs\Crypto\SyncSwingEligibleAssetsJob;
use App\Services\Crypto\SwingEligibilityJsonStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
class CryptoSwingSyncController extends Controller
{
    public function start(SwingEligibilityJsonStorage $storage): JsonResponse
    {
        $old = $storage->readProgress();
        if (in_array($old['status'] ?? null, ['queued', 'running'], true))
            return response()->json(['message' => 'A Swing sync is already running.', 'run' => $old], 202);
        $id = (string) Str::uuid();
        $run = ['id' => $id, 'status' => 'queued', 'stage' => 'Waiting for queue worker', 'progress' => 0, 'initial_assets' => 0, 'volume_qualified' => 0, 'analyzed_assets' => 0, 'eligible_assets' => 0];
        $storage->writeProgress($run);
        SyncSwingEligibleAssetsJob::dispatch($id);
        return response()->json(['message' => 'Swing sync queued.', 'run' => $run], 202);
    }
    public function status(string $id, SwingEligibilityJsonStorage $storage): JsonResponse
    {
        return response()->json(['run' => $storage->readProgress($id)]);
    }
}
