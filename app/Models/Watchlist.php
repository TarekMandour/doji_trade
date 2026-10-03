<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    protected $fillable = ['name', 'user_id'];

    public function stocks()
    {
        return $this->belongsToMany(Stock::class, 'watchlist_stocks')
            ->withPivot('position')
            ->orderBy('watchlist_stocks.position');
    }
}