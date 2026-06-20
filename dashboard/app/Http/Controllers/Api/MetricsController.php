<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ServerMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * POST /api/agent/metrics
     * Agent pushes a resource usage snapshot.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'cpu_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'ram_used_mb' => ['required', 'integer', 'min:0'],
            'ram_total_mb' => ['required', 'integer', 'min:0'],
            'disk_used_gb' => ['required', 'numeric', 'min:0'],
            'disk_total_gb' => ['required', 'numeric', 'min:0'],
            'collected_at' => ['nullable', 'date'],
        ]);

        $metric = ServerMetric::create([
            'server_id' => $agent->server_id,
            'cpu_percent' => $data['cpu_percent'],
            'ram_used_mb' => $data['ram_used_mb'],
            'ram_total_mb' => $data['ram_total_mb'],
            'disk_used_gb' => $data['disk_used_gb'],
            'disk_total_gb' => $data['disk_total_gb'],
            'collected_at' => $data['collected_at'] ?? now(),
        ]);

        // Treat metrics post as an implicit heartbeat.
        $agent->heartbeat();

        return response()->json([
            'success' => true,
            'metric_id' => $metric->id,
        ], 201);
    }
}
