<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\VersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent API
|--------------------------------------------------------------------------
| All agent endpoints are prefixed with /api/agent.
| Registration is unauthenticated (uses a one-time token in the body).
| Every other endpoint requires a valid Bearer runtime token (agent.auth).
*/

Route::prefix('agent')->group(function () {
    // Public: version check endpoint (no auth required)
    Route::get('version', [VersionController::class, 'latest']);

    // Unauthenticated: one-time registration token in body.
    Route::post('register', [AgentController::class, 'register']);

    // Authenticated agent endpoints.
    Route::middleware('agent.auth')->group(function () {
        Route::post('heartbeat', [AgentController::class, 'heartbeat']);

        Route::get('commands/poll', [CommandController::class, 'poll']);
        Route::post('commands/{command}/result', [CommandController::class, 'result']);

        Route::post('services/sync', [ServiceController::class, 'sync']);
        Route::post('service-events', [ServiceController::class, 'event']);

        Route::post('metrics', [MetricsController::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| Dashboard API (user-authenticated via Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
});
