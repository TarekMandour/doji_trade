<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingTranslation extends Model
{

    protected $guarded = ['id', 'created_at', 'updated_at'];
    public function setting()
    {
        return $this->belongsTo(Setting::class);
    }
}
