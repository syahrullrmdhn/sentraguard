<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_uid',
        'server_id',
        'machine_id',
        'agent_version',
        'runtime_token_hash',
        'status',
        'last_heartbeat_at',
        'registered_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'registered_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the server that owns the agent
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if agent is online
     */
    public function isOnline(): bool
    {
        if ($this->status === 'revoked') {
            return false;
        }

        $threshold = config('agent.offline_threshold_seconds', 120);
        return $this->last_heartbeat_at && 
               $this->last_heartbeat_at->diffInSeconds(now()) < $threshold;
    }

    /**
     * Update heartbeat and status
     */
    public function heartbeat(): void
    {
        $this->update([
            'last_heartbeat_at' => now(),
            'status' => 'online',
        ]);
    }

    /**
     * Revoke agent access
     */
    public function revoke(): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
