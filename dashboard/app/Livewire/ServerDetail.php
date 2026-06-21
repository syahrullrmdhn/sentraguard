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
    public string $updateScript = '';

    public array $history = [];

    public function mount(Server $server): void
    {
        $this->server = $server;
        $this->loadMetrics();
        $this->generateUpdateScript();
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

    /**
     * Generate PowerShell script untuk install agent versi terbaru.
     * Karena token plaintext nggak disimpan (cuma hash), kita generate token baru.
     */
    protected function generateUpdateScript(): void
    {
        $latestVersion = config('agent.latest_version', '1.0.6');
        $downloadUrl = config('agent.download_url', url('/download/agent'));
        $serverUrl = config('app.url');
        
        // Generate token baru untuk update (token lama udah di-hash, nggak bisa diambil lagi)
        $token = 'AGT_' . bin2hex(random_bytes(20));
        $this->server->update(['registration_token' => $token]);

        $this->updateScript = <<<POWERSHELL
# ========================================
# SentraGuard Agent v{$latestVersion} Update Script
# Server: {$this->server->name}
# Generated: {now()->format('Y-m-d H:i:s')}
# ========================================

Write-Host "`n🛑 Step 1: Stop & uninstall agent lama..." -ForegroundColor Yellow
& "\$env:TEMP\sentraguard-agent.exe" stop 2>\$null
Start-Sleep -Seconds 3
& "\$env:TEMP\sentraguard-agent.exe" uninstall 2>\$null

Write-Host "`n🧹 Step 2: Clean up old files..." -ForegroundColor Yellow
Remove-Item "C:\ProgramData\SentraGuard Agent" -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item "\$env:TEMP\sentraguard-agent.exe" -ErrorAction SilentlyContinue

Write-Host "`n📥 Step 3: Download v{$latestVersion}..." -ForegroundColor Yellow
Invoke-RestMethod "{$downloadUrl}?v=\$(Get-Random)" -OutFile \$env:TEMP\sentraguard-agent.exe

Write-Host "`n✅ Step 4: Verify version..." -ForegroundColor Yellow
& "\$env:TEMP\sentraguard-agent.exe" version

Write-Host "`n🚀 Step 5: Installing with fresh token..." -ForegroundColor Yellow
& "\$env:TEMP\sentraguard-agent.exe" install --server {$serverUrl} --token {$token}

Write-Host "`n⏳ Step 6: Wait for agent startup (30s)..." -ForegroundColor Yellow
Start-Sleep -Seconds 30

Write-Host "`n📊 Step 7: Check log..." -ForegroundColor Yellow
Get-Content "C:\ProgramData\SentraGuard Agent\logs\agent.log" -Tail 15

Write-Host "`n✅ SELESAI! Refresh dashboard untuk verify:" -ForegroundColor Green
Write-Host "   - Agent Version: {$latestVersion}" -ForegroundColor White
Write-Host "   - Metrics & Services: aktif" -ForegroundColor White
Write-Host "   - Public IP: terdeteksi" -ForegroundColor White
Write-Host "`n🔄 Update berikutnya bisa langsung dari dashboard (nggak perlu token lagi)." -ForegroundColor Cyan
POWERSHELL;
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
