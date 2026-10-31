<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\ResultsController;
use Illuminate\Support\Facades\Route;


Route::prefix('{locale?}')->middleware('setLocale')->group(function () {

    Route::get('/', [ResultsController::class, 'index'])->name('results.index');

    Route::get('/results', [ResultsController::class, 'index'])->name('results.index');
    Route::get('/results/{date}/{symbol}', [ResultsController::class, 'show'])->where(['date' => '[A-Za-z0-9_-]+', 'symbol' => '[A-Za-z0-9._-]+'])->name('results.show');

});
