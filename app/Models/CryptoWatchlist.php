<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoWatchlist extends Model
{
    protected $fillable = ['name', 'user_id'];

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(CryptoAsset::class, 'crypto_watchlist_assets')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('crypto_watchlist_assets.position');
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(CryptoScanRun::class);
    }
}
