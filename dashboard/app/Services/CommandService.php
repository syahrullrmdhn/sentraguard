<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServiceCommand;
use Illuminate\Support\Facades\DB;

class CommandService
{
    /** Actions that are valid for the agent to execute. */
    public const ALLOWED_ACTIONS = [
        'start_service',
        'stop_service',
        'restart_service',
        'enable_service',
        'disable_service',
        'get_service_status',
        'sync_services',
        'update', // Agent self-update command
        'firewall_add_rule',
        'firewall_enable_rule',
        'firewall_disable_rule',
        'firewall_delete_rule',
        'firewall_enable_all',
        'firewall_disable_all',
        'firewall_sync',
    ];

    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * Queue a new command for a server after validating the action and allowlist.
     *
     * @throws \InvalidArgumentException
     */
    public function queue(
        Server $server,
        string $serviceName,
        string $action,
        ?int $userId = null,
        ?int $timeoutSeconds = null
    ): ServiceCommand {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new \InvalidArgumentException("Invalid action: {$action}");
        }

        // Allowlist enforcement for service-targeting actions.
        // sync_services is a global action and does not target a specific service.
        if ($action !== 'sync_services') {
            $service = $server->services()
                ->where('service_name', $serviceName)
                ->first();

            if (! $service || ! $service->is_allowed) {
                throw new \InvalidArgumentException(
                    "Service '{$serviceName}' is not in the allowlist for this server."
                );
            }
        }

        $command = ServiceCommand::create([
            'server_id' => $server->id,
            'user_id' => $userId,
            'service_name' => $serviceName,
            'action' => $action,
            'status' => 'pending',
            'timeout_seconds' => $timeoutSeconds ?? config('agent.command_timeout', 60),
            'requested_at' => now(),
        ]);

        $this->audit->userAction(
            action: 'command.queue',
            resourceType: 'command',
            resourceId: (string) $command->id,
            description: "Queued {$action} for {$serviceName}",
            serverId: $server->id,
            metadata: ['action' => $action, 'service_name' => $serviceName],
        );

        return $command;
    }

    /**
     * Queue a raw command without service targeting (e.g., 'update', 'sync_services').
     * Skips allowlist checks since these are server-level actions, not service actions.
     *
     * @throws \InvalidArgumentException
     */
    public function queueRaw(
        Server $server,
        string $command,
        ?int $userId = null,
        ?int $timeoutSeconds = null
    ): ServiceCommand {
        if (! in_array($command, self::ALLOWED_ACTIONS, true)) {
            throw new \InvalidArgumentException("Invalid command: {$command}");
        }

        $cmd = ServiceCommand::create([
            'server_id' => $server->id,
            'user_id' => $userId,
            'service_name' => '', // Empty for non-service commands
            'action' => $command,
            'status' => 'pending',
            'timeout_seconds' => $timeoutSeconds ?? config('agent.command_timeout', 60),
            'requested_at' => now(),
        ]);

        $this->audit->userAction(
            action: 'command.queue',
            resourceType: 'command',
            resourceId: (string) $cmd->id,
            description: "Queued {$command}",
            serverId: $server->id,
            metadata: ['action' => $command],
        );

        return $cmd;
    }

    /**
     * Atomically pick up the next pending command for a server.
     * Uses a transaction + lockForUpdate to prevent duplicate pickup by
     * concurrent agent polls. Returns null when no pending command exists.
     */
    public function pickNextForServer(Server $server): ?ServiceCommand
    {
        return DB::transaction(function () use ($server) {
            $command = ServiceCommand::where('server_id', $server->id)
                ->where('status', 'pending')
                ->orderBy('requested_at')
                ->lockForUpdate()
                ->first();

            if (! $command) {
                return null;
            }

            $command->markPicked();

            return $command->fresh();
        });
    }

    /**
     * Apply a result submitted by the agent.
     *
     * @param  array<string, mixed>  $result
     */
    public function applyResult(ServiceCommand $command, array $result): ServiceCommand
    {
        $status = $result['status'] ?? 'failed';

        if ($status === 'success') {
            $command->markSuccess($result);
        } else {
            $command->markFailed($result);
        }

        // Keep the cached service row in sync when the agent reports new state.
        if (! empty($result['service_status']) && $command->action !== 'sync_services') {
            $command->server->services()
                ->where('service_name', $command->service_name)
                ->update([
                    'status' => $result['service_status'],
                    'startup_type' => $result['startup_type'] ?? DB::raw('startup_type'),
                ]);
        }

        $this->audit->agentAction(
            agentUid: $command->server->agent?->agent_uid ?? 'unknown',
            action: 'command.result',
            serverId: $command->server_id,
            description: "Command {$command->id} reported {$status}",
            metadata: [
                'command_id' => $command->id,
                'exit_code' => $result['exit_code'] ?? null,
                'status' => $status,
            ],
            result: $status === 'success' ? 'success' : 'failed',
        );

        return $command->fresh();
    }

    /**
     * Mark commands that have exceeded their timeout window.
     * Intended to be called by a scheduler. Returns affected count.
     */
    public function timeoutStaleCommands(): int
    {
        $stale = ServiceCommand::whereIn('status', ['picked', 'running'])
            ->get()
            ->filter(function (ServiceCommand $cmd) {
                $reference = $cmd->picked_at ?? $cmd->requested_at;
                return $reference && $reference->addSeconds($cmd->timeout_seconds)->isPast();
            });

        foreach ($stale as $cmd) {
            $cmd->update(['status' => 'timeout', 'finished_at' => now()]);
        }

        return $stale->count();
    }
}
