<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoScanResult extends Model
{
    protected $fillable = [
        'crypto_scan_run_id', 'crypto_asset_id', 'state', 'signal_type',
        'entry_price', 'stop_loss', 'target_1', 'target_2', 'opportunity_score',
        'risk_score', 'confidence_score', 'reason', 'result_json_path',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(CryptoScanRun::class, 'crypto_scan_run_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id');
    }
}
