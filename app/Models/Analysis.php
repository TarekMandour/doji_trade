<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analysis extends Model
{
    protected $table = 'analysis';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'analysis_date' => 'date',
    ];

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }
}
