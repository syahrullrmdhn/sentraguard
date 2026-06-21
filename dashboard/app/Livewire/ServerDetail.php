<?php

namespace App\Livewire;

use App\Models\Server;
use App\Services\AuditService;
use App\Services\CommandService;
use Livewire\Attributes\On;
use Livewire\Component;

class ServerDetail extends Component
{
    public Server $server;
    public string $tab = 'overview';

    public array $history = [];

    public function mount(Server $server): void
    {
        $this->server = $server;
        $this->loadMetrics();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function loadMetrics(): void
    {
        $this->history = $this->server->metrics()
            ->where('collected_at', '>=', now()->subHour())
            ->orderBy('collected_at')
            ->get(['cpu_percent', 'ram_used_mb', 'ram_total_mb', 'disk_used_gb', 'disk_total_gb', 'collected_at'])
            ->toArray();
    }

    /**
     * Toggle the allowlist flag for a service.
     */
    public function toggleAllow(int $serviceId, AuditService $audit): void
    {
        $service = $this->server->services()->findOrFail($serviceId);
        $service->update(['is_allowed' => ! $service->is_allowed]);

        $audit->userAction(
            action: 'allowlist.toggle',
            resourceType: 'service',
            resourceId: (string) $service->id,
            description: ($service->is_allowed ? 'Allowed' : 'Disallowed')." service {$service->service_name}",
            serverId: $this->server->id,
        );
    }

    /**
     * Queue a service action command.
     */
    public function runAction(int $serviceId, string $action, CommandService $commands): void
    {
        $service = $this->server->services()->findOrFail($serviceId);

        try {
            $commands->queue(
                server: $this->server,
                serviceName: $service->service_name,
                action: $action,
                userId: auth()->id(),
            );
            session()->flash('cmd_ok', "Command {$action} untuk {$service->service_name} berhasil di-queue.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('cmd_err', $e->getMessage());
        }
    }

    /**
     * Trigger agent self-update command.
     */
    public function updateAgent(CommandService $commands, AuditService $audit): void
    {
        try {
            $commands->queueRaw(
                server: $this->server,
                command: 'update',
                userId: auth()->id(),
            );

            $audit->userAction(
                action: 'agent.update_triggered',
                resourceType: 'server',
                resourceId: (string) $this->server->id,
                description: "Triggered agent self-update from v{$this->server->agent->agent_version} to latest",
                serverId: $this->server->id,
            );

            session()->flash('cmd_ok', 'Update agent berhasil di-queue. Tunggu ~60 detik, agent akan restart otomatis.');
        } catch (\Exception $e) {
            session()->flash('cmd_err', 'Gagal queue update: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $this->server->load('agent');
        $services = $this->server->services()->orderBy('service_name')->get();
        $commands = $this->server->commands()->with('user')->latest('requested_at')->limit(20)->get();
        $latest = $this->server->latestMetric();

        return view('livewire.server-detail', compact('services', 'commands', 'latest'));
    }
}
