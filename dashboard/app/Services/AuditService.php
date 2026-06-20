<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Record an audit log entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function log(array $data): AuditLog
    {
        return AuditLog::log($data);
    }

    /**
     * Log a user-initiated action.
     */
    public function userAction(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?string $description = null,
        ?int $serverId = null,
        array $metadata = [],
        string $result = 'success'
    ): AuditLog {
        return AuditLog::log([
            'actor_type' => 'user',
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'description' => $description,
            'server_id' => $serverId,
            'metadata' => $metadata ?: null,
            'result' => $result,
        ]);
    }

    /**
     * Log an agent-initiated action.
     */
    public function agentAction(
        string $agentUid,
        string $action,
        ?int $serverId = null,
        ?string $description = null,
        array $metadata = [],
        string $result = 'success'
    ): AuditLog {
        return AuditLog::log([
            'user_id' => null,
            'actor_type' => 'agent',
            'actor_identifier' => $agentUid,
            'action' => $action,
            'server_id' => $serverId,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'result' => $result,
        ]);
    }

    /**
     * Log a system-initiated action (scheduler, jobs).
     */
    public function systemAction(
        string $action,
        ?int $serverId = null,
        ?string $description = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::log([
            'user_id' => null,
            'actor_type' => 'system',
            'actor_identifier' => 'system',
            'action' => $action,
            'server_id' => $serverId,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
