<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class Setting extends Model implements HasMedia
{
    use  InteractsWithMedia;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function registerMediaCollections(Media $media = null): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();

        $this->addMediaCollection('logoDark')
            ->singleFile();

        $this->addMediaCollection('fav')
            ->singleFile();

        $this->addMediaCollection('breadcrumb')
            ->singleFile();
    }

    public function translations()
    {
        return $this->hasMany(SettingTranslation::class);
    }

    public function translation()
    {
        return $this->hasOne(SettingTranslation::class)
            ->where('locale', app()->getLocale());
    }

}
