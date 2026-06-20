<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'hostname',
        'environment',
        'public_ip',
        'private_ip',
        'os_name',
        'os_version',
        'status',
        'notes',
        'registration_token_hash',
        'token_generated_at',
        'token_used',
    ];

    protected $casts = [
        'token_generated_at' => 'datetime',
        'token_used' => 'boolean',
    ];

    /**
     * Get the agent for this server
     */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * Get all services for this server
     */
    public function services(): HasMany
    {
        return $this->hasMany(ServerService::class);
    }

    /**
     * Get all commands for this server
     */
    public function commands(): HasMany
    {
        return $this->hasMany(ServiceCommand::class);
    }

    /**
     * Get all metrics for this server
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    /**
     * Get all audit logs for this server
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if server is online
     */
    public function isOnline(): bool
    {
        return $this->agent && $this->agent->status === 'online';
    }

    /**
     * Get latest metric
     */
    public function latestMetric()
    {
        return $this->metrics()->latest('collected_at')->first();
    }
}
