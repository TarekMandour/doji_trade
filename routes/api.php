<?php

use App\Http\Controllers\Api\ThndrTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// استقبال توكن Thndr من امتداد المتصفح (Thndr Token Capture)
Route::post('/thndr/token', ThndrTokenController::class)
    ->middleware('throttle:6,1');
