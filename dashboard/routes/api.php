<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\ServerApiController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AuditApiController;
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
| Dashboard API (user-authenticated via Sanctum SPA cookie)
|--------------------------------------------------------------------------
*/

// Auth (login is public; rest require session cookie)
Route::post('auth/login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthApiController::class, 'me']);
    Route::post('auth/logout', [AuthApiController::class, 'logout']);

    // Dashboard summary
    Route::get('dashboard', [DashboardApiController::class, 'index']);

    // Servers
    Route::get('servers', [ServerApiController::class, 'index']);
    Route::get('servers/{server}', [ServerApiController::class, 'show']);
    Route::delete('servers/{server}', [ServerApiController::class, 'destroy']);
    Route::post('servers', [ServerApiController::class, 'store']);
    Route::get('servers/{server}/metrics', [ServerApiController::class, 'metrics']);
    Route::get('servers/{server}/services', [ServerApiController::class, 'services']);
    Route::get('servers/{server}/commands', [ServerApiController::class, 'commands']);
    Route::get('servers/{server}/update-script', [ServerApiController::class, 'updateScript']);
    Route::post('servers/{server}/update-agent', [ServerApiController::class, 'updateAgent']);
    Route::post('servers/{server}/services/{service}/toggle-allow', [ServerApiController::class, 'toggleAllow']);
    Route::post('servers/{server}/services/{service}/action', [ServerApiController::class, 'serviceAction']);
    
    // Firewall rules
    Route::get('servers/{server}/firewall', [ServerApiController::class, 'firewallRules']);
    Route::post('servers/{server}/firewall', [ServerApiController::class, 'createFirewallRule']);
    Route::patch('servers/{server}/firewall/{rule}/toggle', [ServerApiController::class, 'toggleFirewallRule']);
    Route::post('servers/{server}/firewall/toggle', [ServerApiController::class, 'toggleFirewall']);
    Route::delete('servers/{server}/firewall/{rule}', [ServerApiController::class, 'deleteFirewallRule']);

    // Commands queue (global)
    Route::get('commands', [CommandController::class, 'indexForUser']);

    // Users CRUD
    Route::get('users', [UserApiController::class, 'index']);
    Route::post('users', [UserApiController::class, 'store']);
    Route::put('users/{user}', [UserApiController::class, 'update']);
    Route::delete('users/{user}', [UserApiController::class, 'destroy']);
    Route::get('roles', [UserApiController::class, 'roles']);

    // Audit logs
    Route::get('audit', [AuditApiController::class, 'index']);
});
