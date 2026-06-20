<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'cpu_percent',
        'ram_used_mb',
        'ram_total_mb',
        'disk_used_gb',
        'disk_total_gb',
        'collected_at',
    ];

    protected $casts = [
        'cpu_percent' => 'decimal:2',
        'ram_used_mb' => 'integer',
        'ram_total_mb' => 'integer',
        'disk_used_gb' => 'decimal:2',
        'disk_total_gb' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    /**
     * Get the server that owns the metric
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get RAM usage percentage
     */
    public function getRamPercentAttribute(): float
    {
        if ($this->ram_total_mb == 0) {
            return 0;
        }
        return round(($this->ram_used_mb / $this->ram_total_mb) * 100, 2);
    }

    /**
     * Get Disk usage percentage
     */
    public function getDiskPercentAttribute(): float
    {
        if ($this->disk_total_gb == 0) {
            return 0;
        }
        return round(($this->disk_used_gb / $this->disk_total_gb) * 100, 2);
    }
}
