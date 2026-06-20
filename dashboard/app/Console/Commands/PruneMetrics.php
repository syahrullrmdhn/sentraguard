<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ServerMetric;
use Illuminate\Console\Command;

class PruneMetrics extends Command
{
    protected $signature = 'metrics:prune';

    protected $description = 'Delete old server_metrics and audit_logs beyond their retention period';

    public function handle(): int
    {
        $metricsDays = (int) config('agent.metrics_retention_days', 30);
        $auditDays = (int) config('agent.audit_log_retention_days', 90);

        $metricsDeleted = ServerMetric::where('collected_at', '<', now()->subDays($metricsDays))->delete();
        $auditDeleted = AuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();

        $this->info("Pruned {$metricsDeleted} metrics older than {$metricsDays} days.");
        $this->info("Pruned {$auditDeleted} audit logs older than {$auditDays} days.");

        return self::SUCCESS;
    }
}
