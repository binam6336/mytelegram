<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// نصب از راه مرورگر (بدون نیاز به ترمینال یا php خط فرمان)
Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install/migrate', [InstallController::class, 'migrate'])->middleware('throttle:5,1')->name('install.migrate');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,10');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('telegram')->name('telegram.')->controller(TelegramController::class)->group(function () {
        Route::get('/', 'show')->name('connect');
        Route::get('/status', 'status')->name('status');
        Route::post('/credentials', 'credentials')->name('credentials');
        Route::post('/code', 'sendCode')->name('code');
        Route::post('/verify', 'verifyCode')->name('verify');
        Route::post('/password', 'verifyPassword')->name('password');
        Route::post('/toggle', 'toggle')->name('toggle');
        Route::post('/sync', 'sync')->name('sync');
        Route::post('/logout', 'logout')->name('logout');
        Route::post('/delete', 'destroy')->name('delete');
    });

    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::post('/messages/delete-all', [MessageController::class, 'destroyAll'])->name('messages.delete-all');

    Route::get('/filters', [FilterController::class, 'index'])->name('filters');
    Route::post('/filters/keywords', [FilterController::class, 'storeKeyword'])->name('keywords.store');
    Route::post('/filters/keywords/{keyword}/delete', [FilterController::class, 'destroyKeyword'])->whereNumber('keyword')->name('keywords.delete');
    Route::post('/filters/rescan', [FilterController::class, 'rescan'])->middleware('throttle:6,1')->name('filters.rescan');
    Route::post('/filters/{item}/delete', [FilterController::class, 'destroyFiltered'])->whereNumber('item')->name('filters.delete');
});
