<?php

namespace App\Services\Crypto;

use App\Models\CryptoAsset;
use App\Models\CryptoScanResult;
use App\Models\CryptoScanRun;

class CryptoScanPersistenceService
{
    public function saveResult(CryptoScanRun $run, CryptoAsset $asset, array $result, ?string $jsonPath = null): CryptoScanResult
    {
        return CryptoScanResult::updateOrCreate(
            ['crypto_scan_run_id' => $run->id, 'crypto_asset_id' => $asset->id],
            [
                'state' => $result['state'] ?? 'error',
                'signal_type' => $result['signal_type'] ?? null,
                'entry_price' => $result['entry_price'] ?? null,
                'stop_loss' => $result['stop_loss'] ?? null,
                'target_1' => $result['target_1'] ?? null,
                'target_2' => $result['target_2'] ?? null,
                'opportunity_score' => $result['opportunity_score'] ?? null,
                'risk_score' => $result['risk_score'] ?? null,
                'confidence_score' => $result['confidence_score'] ?? null,
                'reason' => $result['reason'] ?? null,
                'result_json_path' => $jsonPath,
            ]
        );
    }
}
