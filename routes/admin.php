<?php

use App\Http\Controllers\Admin\AdminsController;
use App\Http\Controllers\Admin\AnalysisController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WatchlistController;
use App\Http\Controllers\Auth\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('setLocale')->group(function () {

    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
    Route::get('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::group(['middleware' => ['admin']], function () {

        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Thndr (token capture) ─────────────────────────────────────────────
        Route::name('thndr.')->prefix('thndr')->middleware('permission:settings edit')->group(function () {
            Route::get('/status', [DashboardController::class, 'status'])->name('status');
            Route::post('/start', [DashboardController::class, 'captureStart'])->name('start');
            Route::post('/stop', [DashboardController::class, 'captureStop'])->name('stop');
        });

        // ── Admins (employees) ────────────────────────────────────────────────
        Route::name('admins.')->prefix('admins')->middleware('permission:admins view')->group(function () {
            Route::get('/', [AdminsController::class, 'index'])->name('index');
            Route::get('/export', [AdminsController::class, 'export'])->name('export');
            Route::get('/show/{id}', [AdminsController::class, 'show'])->name('show');
            Route::post('/delete', [AdminsController::class, 'destroy'])->name('delete')->middleware('permission:admins delete');
            Route::get('/create', [AdminsController::class, 'create'])->name('create')->middleware('permission:admins create');
            Route::post('/store', [AdminsController::class, 'store'])->name('store')->middleware('permission:admins create');
            Route::get('/edit/{id}', [AdminsController::class, 'edit'])->name('edit')->middleware('permission:admins edit');
            Route::post('/update', [AdminsController::class, 'update'])->name('update')->middleware('permission:admins edit');
        });

        Route::name('settings.')->prefix('settings')->group(function () {
            Route::get('/edit', 'SettingsController@edit')->name('edit')->middleware('permission:settings edit');
            Route::post('/update', 'SettingsController@update')->name('update')->middleware('permission:settings edit');
        });

        // ── Roles ─────────────────────────────────────────────────────────────
        Route::name('roles.')->prefix('roles')->middleware('permission:roles view')->group(function () {
            Route::get('/', 'RolesController@index')->name('index');
            Route::post('/delete', 'RolesController@destroy')->name('delete')->middleware('permission:roles delete');
            Route::get('/create', 'RolesController@create')->name('create')->middleware('permission:roles create');
            Route::post('/store', 'RolesController@store')->name('store')->middleware('permission:roles create');
            Route::get('/edit/{id}', 'RolesController@edit')->name('edit')->middleware('permission:roles edit');
            Route::post('/update', 'RolesController@update')->name('update')->middleware('permission:roles edit');
        });

        // ── Users ─────────────────────────────────────────────────────────────
        Route::name('users.')->prefix('users')->middleware('permission:users view')->group(function () {
            Route::get('/', [UsersController::class, 'index'])->name('index');
            Route::get('/export', [UsersController::class, 'export'])->name('export');
            Route::get('/show/{id}', [UsersController::class, 'show'])->name('show');
            Route::post('/delete', [UsersController::class, 'destroy'])->name('delete')->middleware('permission:users delete');
            Route::get('/create', [UsersController::class, 'create'])->name('create')->middleware('permission:users create');
            Route::post('/store', [UsersController::class, 'store'])->name('store')->middleware('permission:users create');
            Route::get('/edit/{id}', [UsersController::class, 'edit'])->name('edit')->middleware('permission:users edit');
            Route::post('/update', [UsersController::class, 'update'])->name('update')->middleware('permission:users edit');
        });

        // ── Notifications ─────────────────────────────────────────────────────
        Route::name('notifications.')->prefix('notifications')->middleware('permission:notifications view')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/export', [NotificationController::class, 'export'])->name('export');
            Route::get('/show/{id}', [NotificationController::class, 'show'])->name('show');
            Route::post('/delete', [NotificationController::class, 'destroy'])->name('delete')->middleware('permission:notifications delete');
            Route::get('/create', [NotificationController::class, 'create'])->name('create')->middleware('permission:notifications create');
            Route::post('/store', [NotificationController::class, 'store'])->name('store')->middleware('permission:notifications create');
        });

        // ── Pages ─────────────────────────────────────────────────────────────
        Route::name('pages.')->prefix('pages')->middleware('permission:pages view')->group(function () {
            Route::get('/', [PageController::class, 'index'])->name('index');
            Route::get('/show/{id}', [PageController::class, 'show'])->name('show');
            Route::post('/delete', [PageController::class, 'destroy'])->name('delete')->middleware('permission:pages delete');
            Route::get('/create', [PageController::class, 'create'])->name('create')->middleware('permission:pages create');
            Route::post('/store', [PageController::class, 'store'])->name('store')->middleware('permission:pages create');
            Route::get('/edit/{id}', [PageController::class, 'edit'])->name('edit')->middleware('permission:pages edit');
            Route::post('/update', [PageController::class, 'update'])->name('update')->middleware('permission:pages edit');
        });

        // ── Stocks ────────────────────────────────────────────────────────────
        Route::name('stocks.')->prefix('stocks')->middleware('permission:stocks view')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('/show/{id}', [StockController::class, 'show'])->name('show');
            Route::post('/delete', [StockController::class, 'destroy'])->name('delete')->middleware('permission:stocks delete');
            Route::get('/create', [StockController::class, 'create'])->name('create')->middleware('permission:stocks create');
            Route::post('/store', [StockController::class, 'store'])->name('store')->middleware('permission:stocks create');
            Route::get('/edit/{id}', [StockController::class, 'edit'])->name('edit')->middleware('permission:stocks edit');
            Route::post('/update', [StockController::class, 'update'])->name('update')->middleware('permission:stocks edit');
            Route::get('/export', [StockController::class, 'export'])->name('export');
            Route::post('/import', [StockController::class, 'import'])->name('import');
        });

        // ── Watchlists ────────────────────────────────────────────────────────
        Route::name('watchlists.')->prefix('watchlists')->middleware('permission:watchlists view')->group(function () {
            Route::get('/', [WatchlistController::class, 'index'])->name('index');
            Route::get('/show/{id}', [WatchlistController::class, 'show'])->name('show');
            Route::post('/delete', [WatchlistController::class, 'destroy'])->name('delete')->middleware('permission:watchlists delete');
            Route::get('/create', [WatchlistController::class, 'create'])->name('create')->middleware('permission:watchlists create');
            Route::post('/store', [WatchlistController::class, 'store'])->name('store')->middleware('permission:watchlists create');
            Route::get('/edit/{id}', [WatchlistController::class, 'edit'])->name('edit')->middleware('permission:watchlists edit');
            Route::post('/update', [WatchlistController::class, 'update'])->name('update')->middleware('permission:watchlists edit');
        });

        // ── Analysis ──────────────────────────────────────────────────────────
        Route::name('analysis.')->prefix('analysis')->middleware('permission:analysis view')->group(function () {
            Route::get('/', [AnalysisController::class, 'index'])->name('index');
            Route::get('/show/{id}', [AnalysisController::class, 'show'])->name('show');
            Route::post('/delete', [AnalysisController::class, 'destroy'])->name('delete')->middleware('permission:analysis delete');
            Route::get('/create', [AnalysisController::class, 'create'])->name('create')->middleware('permission:analysis create');
            Route::get('/intraday', [AnalysisController::class, 'intraday'])->name('intraday')->middleware('permission:analysis create');
            Route::post('/store', [AnalysisController::class, 'store'])->name('store')->middleware('permission:analysis create');
            Route::post('/store-intraday', [AnalysisController::class, 'storeIntraday'])->name('store-intraday')->middleware('permission:analysis create');
        });

    });

});
