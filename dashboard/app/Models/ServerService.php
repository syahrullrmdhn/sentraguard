<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerService extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'service_name',
        'display_name',
        'status',
        'startup_type',
        'is_allowed',
        'last_synced_at',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the server that owns the service
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if service is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'Running';
    }

    /**
     * Check if service is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'Stopped';
    }

    /**
     * Allow this service for operations
     */
    public function allow(): void
    {
        $this->update(['is_allowed' => true]);
    }

    /**
     * Disallow this service for operations
     */
    public function disallow(): void
    {
        $this->update(['is_allowed' => false]);
    }
}
