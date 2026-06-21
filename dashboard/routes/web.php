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

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Resource index pages (Livewire-powered views)
    Route::view('/servers', 'servers.index')->name('servers.index');
    Route::get('/servers/{server}', function (\App\Models\Server $server) {
        return view('servers.show', ['server' => $server]);
    })->name('servers.show');
    Route::view('/commands', 'commands.index')->name('commands.index');
    Route::view('/audit', 'audit.index')->name('audit.index');
});

// Public download endpoint (no auth — Cloudflare Tunnel style)
Route::get('/download/agent', [App\Http\Controllers\DownloadController::class, 'agent'])->name('download.agent');
