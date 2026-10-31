<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Validator;

class SettingsController extends Controller
{

    public function edit()
    {
        $settings = Setting::get();
        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {

        $items = $request->input('data', []);
        
        foreach ($items as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            }
        }

        $logoSetting = Setting::where('key', 'logo')->first();
        if($logoSetting && $request->hasFile('logo') && $request->file('logo')->isValid()){
            $logoSetting->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        $logoDarkSetting = Setting::where('key', 'logoDark')->first();
        if($logoDarkSetting && $request->hasFile('logoDark') && $request->file('logoDark')->isValid()){
            $logoDarkSetting->addMediaFromRequest('logoDark')->toMediaCollection('logoDark');
        }

        $favSetting = Setting::where('key', 'fav')->first();
        if($favSetting && $request->hasFile('fav') && $request->file('fav')->isValid()){
            $favSetting->addMediaFromRequest('fav')->toMediaCollection('fav'); 
        }

        $breadcrumbSetting = Setting::where('key', 'breadcrumb')->first();
        if($breadcrumbSetting && $request->hasFile('breadcrumb') && $request->file('breadcrumb')->isValid()){
            $breadcrumbSetting->addMediaFromRequest('breadcrumb')->toMediaCollection('breadcrumb');
        }

        return redirect(route('admin.settings.edit'))->with('message', 'Edited successfully')->with('status', 'success');
    }

}
