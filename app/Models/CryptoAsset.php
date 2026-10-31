<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoAsset extends Model
{
    protected $fillable = [
        'symbol', 'base_asset', 'quote_asset', 'status',
        'base_asset_precision', 'quote_asset_precision', 'filters_json', 'last_synced_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function watchlists(): BelongsToMany
    {
        return $this->belongsToMany(CryptoWatchlist::class, 'crypto_watchlist_assets')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function scanResults(): HasMany
    {
        return $this->hasMany(CryptoScanResult::class);
    }
}
