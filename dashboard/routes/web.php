<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // All other authenticated routes handled by Nuxt SPA
});

// Public download endpoint (no auth — Cloudflare Tunnel style)
Route::get('/download/agent', [App\Http\Controllers\DownloadController::class, 'agent'])->name('download.agent');
