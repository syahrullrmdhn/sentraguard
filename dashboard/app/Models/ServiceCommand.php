<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'user_id',
        'service_name',
        'action',
        'status',
        'timeout_seconds',
        'exit_code',
        'stdout',
        'stderr',
        'service_status_after',
        'startup_type_after',
        'requested_at',
        'picked_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'picked_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get the server that owns the command
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the user who requested the command
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if command is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if command is running
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['picked', 'running']);
    }

    /**
     * Check if command is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['success', 'failed', 'timeout', 'rejected', 'cancelled']);
    }

    /**
     * Check if command succeeded
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if command failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'timeout', 'rejected']);
    }

    /**
     * Mark command as picked by agent
     */
    public function markPicked(): void
    {
        $this->update([
            'status' => 'picked',
            'picked_at' => now(),
        ]);
    }

    /**
     * Mark command as running
     */
    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark command as success
     */
    public function markSuccess(array $result): void
    {
        $this->update([
            'status' => 'success',
            'exit_code' => $result['exit_code'] ?? 0,
            'stdout' => $result['stdout'] ?? null,
            'stderr' => $result['stderr'] ?? null,
            'service_status_after' => $result['service_status'] ?? null,
            'startup_type_after' => $result['startup_type'] ?? null,
            'finished_at' => now(),
        ]);
    }

    /**
     * Mark command as failed
     */
    public function markFailed(array $result): void
    {
        $this->update([
            'status' => 'failed',
            'exit_code' => $result['exit_code'] ?? 1,
            'stdout' => $result['stdout'] ?? null,
            'stderr' => $result['stderr'] ?? null,
            'finished_at' => now(),
        ]);
    }

    /**
     * Cancel command
     */
    public function cancel(): void
    {
        if ($this->isPending()) {
            $this->update([
                'status' => 'cancelled',
                'finished_at' => now(),
            ]);
        }
    }
}
