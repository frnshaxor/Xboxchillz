<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DashboardApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\WatchController;
use App\Models\Video;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return view('catalog', ['videos' => Video::query()->where('status', 'ready')->with('category')->latest()->get()]);
})->name('catalog.index');

Route::post('/access', [AccessController::class, 'store'])->middleware('throttle:10,1')->name('access.store');
Route::delete('/access', [AccessController::class, 'destroy'])->name('access.destroy');
Route::get('/watch/{video}', [WatchController::class, 'show'])->name('watch.show');
Route::get('/media/{video}/{asset}', [MediaController::class, 'show'])
    ->where('asset', '(?:master|360p|720p)\\.m3u8|(?:360p|720p)_\\d{3}\\.ts|source\\.mp4|poster\\.jpg')
    ->name('media.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:8,1')->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/categories', [DashboardController::class, 'storeCategory'])->name('categories.store');
    Route::post('/tokens', [DashboardController::class, 'storeToken'])->name('tokens.store');
    Route::patch('/tokens/{accessToken}/toggle', [DashboardController::class, 'toggleToken'])->name('tokens.toggle');
    Route::delete('/tokens/{accessToken}', [DashboardController::class, 'destroyToken'])->name('tokens.destroy');

    Route::prefix('api')->as('api.')->group(function (): void {
        Route::get('/overview', [DashboardApiController::class, 'overview'])->name('overview');
        Route::get('/analytics', [DashboardApiController::class, 'analytics'])->name('analytics');
        Route::get('/tokens', [DashboardApiController::class, 'tokens'])->name('tokens');
        Route::post('/tokens', [DashboardApiController::class, 'createToken'])->name('tokens.create');
        Route::patch('/tokens/{accessToken}', [DashboardApiController::class, 'updateToken'])->name('tokens.update');
        Route::patch('/tokens/{accessToken}/toggle', [DashboardApiController::class, 'toggleToken'])->name('tokens.toggle');
        Route::delete('/tokens/{accessToken}', [DashboardApiController::class, 'deleteToken'])->name('tokens.delete');
        Route::patch('/settings', [DashboardApiController::class, 'updateSettings'])->name('settings.update');
    });
});

// Sandbox-only probe. Remove before the production cutover.
Route::get('/migration-readiness', function () {
    $tables = ['users', 'categories', 'videos', 'access_tokens', 'payment_orders', 'settings', 'analytics_events', 'activity_logs', 'jobs'];

    return response()->json([
        'application' => config('app.name'),
        'environment' => app()->environment(),
        'database' => config('database.default'),
        'ready' => collect($tables)->every(fn (string $table) => Schema::hasTable($table)),
        'tables' => collect($tables)->mapWithKeys(fn (string $table) => [$table => Schema::hasTable($table)]),
        'note' => 'Sandbox only: no production data or media is read by this application.',
    ]);
})->name('sandbox.readiness');
