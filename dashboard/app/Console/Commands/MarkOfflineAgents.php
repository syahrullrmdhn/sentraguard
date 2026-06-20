<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;

class MarkOfflineAgents extends Command
{
    protected $signature = 'agents:mark-offline';

    protected $description = 'Mark agents as offline when their last heartbeat exceeds the offline threshold';

    public function handle(): int
    {
        $threshold = (int) config('agent.offline_threshold_seconds', 120);
        $cutoff = now()->subSeconds($threshold);

        $count = Agent::where('status', 'online')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_heartbeat_at')
                  ->orWhere('last_heartbeat_at', '<', $cutoff);
            })
            ->update(['status' => 'offline']);

        $this->info("Marked {$count} agent(s) as offline.");

        return self::SUCCESS;
    }
}
