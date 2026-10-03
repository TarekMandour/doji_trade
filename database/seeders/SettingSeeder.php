<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::create(['type' => 'general','key' => 'name_ar','value' => 'نظام تجريبي']);
        Setting::create(['type' => 'general','key' => 'name_en','value' => 'Demo System']);
        Setting::create(['type' => 'general','key' => 'description_ar','value' => null]);
        Setting::create(['type' => 'general','key' => 'description_en','value' => null]);
        Setting::create(['type' => 'general','key' => 'email','value' => 'info@company.com']);
        Setting::create(['type' => 'general','key' => 'email2','value' => null]);
        Setting::create(['type' => 'general','key' => 'phone','value' => '01006287379']);
        Setting::create(['type' => 'general','key' => 'phone2','value' => null]);
        Setting::create(['type' => 'general','key' => 'whatsapp','value' => '+201006287379']);
        Setting::create(['type' => 'general','key' => 'address','value' => 'عنوان تجريبي عنوان تجريبي']);
        Setting::create(['type' => 'general','key' => 'address2','value' => null]);
        Setting::create(['type' => 'general','key' => 'location','value' => null]);
        Setting::create(['type' => 'general','key' => 'lat','value' => null]);
        Setting::create(['type' => 'general','key' => 'lng','value' => null]);
        Setting::create(['type' => 'social','key' => 'facebook','value' => null]);
        Setting::create(['type' => 'social','key' => 'twitter','value' => null]);
        Setting::create(['type' => 'social','key' => 'instagram','value' => null]);
        Setting::create(['type' => 'social','key' => 'linkedin','value' => null]);
        Setting::create(['type' => 'social','key' => 'youtube','value' => null]);
        Setting::create(['type' => 'social','key' => 'tiktok','value' => null]);
        Setting::create(['type' => 'social','key' => 'snapchat','value' => null]);
        Setting::create(['type' => 'seo','key' => 'meta_keywords_ar','value' => null]);
        Setting::create(['type' => 'seo','key' => 'meta_keywords_en','value' => null]);
        Setting::create(['type' => 'seo','key' => 'meta_description_ar','value' => null]);
        Setting::create(['type' => 'seo','key' => 'meta_description_en','value' => null]);
        $logo = Setting::create(['type' => 'media','key' => 'logo','value' => null]);
        $logoDark = Setting::create(['type' => 'media','key' => 'logoDark','value' => null]);
        $favicon = Setting::create(['type' => 'media','key' => 'fav','value' => null]);
        $breadcrumb = Setting::create(['type' => 'media','key' => 'breadcrumb','value' => null]);
        $logo->addMedia(public_path('dash/assets/media/logos/logodark.png'))
            ->preservingOriginal()
            ->toMediaCollection('logo');

        $logoDark->addMedia(public_path('dash/assets/media/logos/logo.png'))
            ->preservingOriginal()
            ->toMediaCollection('logoDark');

        $favicon->addMedia(public_path('dash/assets/media/logos/fav.png'))
            ->preservingOriginal()
            ->toMediaCollection('fav');

    }
}
