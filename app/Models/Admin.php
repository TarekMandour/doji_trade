<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable implements HasMedia
{

    use Notifiable, HasRoles, InteractsWithMedia, HasFactory, HasApiTokens;

    protected $guard_name = 'admin';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function classrooms () {
        return $this->hasMany(ClassroomTeacher::class, 'admin_id');
    } 

    public function roleAuth () {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function notifications () {
        return $this->hasMany(Notification::class, 'admin_id');
    } 

    public function msgcount () {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function registerMediaCollections(Media $media = null): void
    {
        $this->addMediaCollection('image')
        ->singleFile();

    }
}
