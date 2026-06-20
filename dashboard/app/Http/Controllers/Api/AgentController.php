<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AgentTokenService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    public function __construct(
        protected AgentTokenService $tokens,
        protected AuditService $audit
    ) {}

    /**
     * POST /api/agent/register
     * Agent self-registration using a one-time registration token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'hostname' => ['required', 'string', 'max:255'],
            'machine_id' => ['nullable', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
            'private_ip' => ['nullable', 'string', 'max:64'],
            'public_ip' => ['nullable', 'string', 'max:64'],
        ]);

        $server = $this->tokens->resolveServerByToken($data['token']);

        if (! $server) {
            $this->audit->systemAction(
                action: 'agent.register',
                description: 'Registration rejected: invalid or used token',
                metadata: ['hostname' => $data['hostname']],
            );

            throw ValidationException::withMessages([
                'token' => ['Invalid or already-used registration token.'],
            ]);
        }

        // Update server inventory from registration payload.
        $server->update([
            'hostname' => $data['hostname'],
            'os_name' => $data['os_name'] ?? $server->os_name,
            'os_version' => $data['os_version'] ?? $server->os_version,
            'private_ip' => $data['private_ip'] ?? $server->private_ip,
            'public_ip' => $data['public_ip'] ?? $server->public_ip,
        ]);

        // Create or update the agent record (one agent per server).
        $agent = $server->agent ?? new Agent(['server_id' => $server->id]);
        $agent->fill([
            'agent_uid' => $agent->agent_uid ?: 'agt_'.Str::lower(Str::random(20)),
            'server_id' => $server->id,
            'machine_id' => $data['machine_id'] ?? null,
            'agent_version' => $data['agent_version'] ?? null,
            'status' => 'online',
            'registered_at' => now(),
            'last_heartbeat_at' => now(),
            'revoked_at' => null,
        ]);
        $agent->save();

        // Issue a runtime token (plaintext returned once).
        $runtimeToken = $this->tokens->generateRuntimeToken($agent);

        // Consume the one-time registration token.
        $this->tokens->markTokenUsed($server);

        $this->audit->agentAction(
            agentUid: $agent->agent_uid,
            action: 'agent.register',
            serverId: $server->id,
            description: "Agent registered for server {$server->name}",
            metadata: ['agent_version' => $agent->agent_version],
        );

        return response()->json([
            'success' => true,
            'agent_uid' => $agent->agent_uid,
            'server_id' => $server->id,
            'runtime_token' => $runtimeToken,
            'poll_interval_seconds' => (int) config('agent.default_poll_interval'),
            'heartbeat_interval_seconds' => (int) config('agent.default_heartbeat_interval'),
            'metrics_interval_seconds' => (int) config('agent.default_metrics_interval'),
            'service_monitor_interval_seconds' => (int) config('agent.default_service_monitor_interval'),
        ], 201);
    }

    /**
     * POST /api/agent/heartbeat
     * Periodic liveness signal. Auth via agent.auth middleware.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:online'],
        ]);

        if (! empty($data['agent_version'])) {
            $agent->agent_version = $data['agent_version'];
        }

        $agent->heartbeat();

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
