<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * POST /api/agent/services/sync
     * Agent pushes the full Windows Service list. Upserts into server_services,
     * preserving the is_allowed flag on existing rows.
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'services' => ['required', 'array'],
            'services.*.service_name' => ['required', 'string', 'max:255'],
            'services.*.display_name' => ['nullable', 'string', 'max:255'],
            'services.*.status' => ['nullable', 'string', 'max:50'],
            'services.*.startup_type' => ['nullable', 'string', 'max:50'],
        ]);

        $now = now();
        $count = 0;

        DB::transaction(function () use ($agent, $data, $now, &$count) {
            foreach ($data['services'] as $svc) {
                $agent->server->services()->updateOrCreate(
                    ['service_name' => $svc['service_name']],
                    [
                        'display_name' => $svc['display_name'] ?? $svc['service_name'],
                        'status' => $this->normalizeStatus($svc['status'] ?? 'Unknown'),
                        'startup_type' => $this->normalizeStartup($svc['startup_type'] ?? 'Unknown'),
                        'last_synced_at' => $now,
                        // is_allowed intentionally omitted so existing flag is preserved;
                        // new rows default to false via the migration.
                    ]
                );
                $count++;
            }
        });

        $agent->heartbeat();

        $this->audit->agentAction(
            agentUid: $agent->agent_uid,
            action: 'services.sync',
            serverId: $agent->server_id,
            description: "Synced {$count} services",
            metadata: ['count' => $count],
        );

        return response()->json([
            'success' => true,
            'synced' => $count,
        ]);
    }

    /**
     * POST /api/agent/service-events
     * Agent reports a proactive service state change.
     */
    public function event(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'previous_status' => ['nullable', 'string', 'max:50'],
            'current_status' => ['required', 'string', 'max:50'],
            'detected_at' => ['nullable', 'date'],
        ]);

        $current = $this->normalizeStatus($data['current_status']);

        // Update cached service state.
        $service = $agent->server->services()
            ->where('service_name', $data['service_name'])
            ->first();

        if ($service) {
            $service->update(['status' => $current]);
        }

        $this->audit->agentAction(
            agentUid: $agent->agent_uid,
            action: 'service.state_change',
            serverId: $agent->server_id,
            description: sprintf(
                'Service %s changed %s -> %s',
                $data['service_name'],
                $data['previous_status'] ?? 'unknown',
                $data['current_status']
            ),
            metadata: [
                'service_name' => $data['service_name'],
                'previous_status' => $data['previous_status'] ?? null,
                'current_status' => $data['current_status'],
                'detected_at' => $data['detected_at'] ?? $request->date('detected_at')?->toIso8601String(),
            ],
            result: $current === 'Stopped' ? 'failed' : 'success',
        );

        return response()->json(['success' => true]);
    }

    private function normalizeStatus(string $status): string
    {
        $map = [
            'running' => 'Running',
            'stopped' => 'Stopped',
            'paused' => 'Paused',
        ];

        return $map[strtolower($status)] ?? 'Unknown';
    }

    private function normalizeStartup(string $type): string
    {
        $map = [
            'automatic' => 'Automatic',
            'auto' => 'Automatic',
            'manual' => 'Manual',
            'disabled' => 'Disabled',
        ];

        return $map[strtolower($type)] ?? 'Unknown';
    }
}
