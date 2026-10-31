<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoScanRun extends Model
{
    protected $fillable = [
        'uuid', 'crypto_watchlist_id', 'status', 'settings_json', 'metadata_path',
        'results_path', 'total_assets', 'processed_assets', 'accepted_assets',
        'rejected_assets', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'settings_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(CryptoWatchlist::class, 'crypto_watchlist_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CryptoScanResult::class);
    }
}
