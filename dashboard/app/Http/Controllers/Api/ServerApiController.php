<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerService;
use App\Services\AgentTokenService;
use App\Services\AuditService;
use App\Services\CommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerApiController extends Controller
{
    /**
     * GET /api/servers
     */
    public function index(Request $request): JsonResponse
    {
        $servers = Server::with('agent')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('hostname', 'like', "%{$request->search}%"))
            ->when($request->environment, fn ($q) => $q->where('environment', $request->environment))
            ->orderBy('name')
            ->paginate(15);

        $servers->getCollection()->transform(fn ($s) => $this->serverPayload($s));

        return response()->json($servers);
    }

    /**
     * GET /api/servers/{server}
     */
    public function show(Server $server): JsonResponse
    {
        $server->load('agent');
        $latest = $server->latestMetric();

        return response()->json([
            'server' => $this->serverPayload($server, detail: true),
            'latest_metric' => $latest,
        ]);
    }

    /**
     * POST /api/servers — create server + return one-time token.
     */
    public function store(Request $request, AgentTokenService $tokens, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'environment' => ['required', 'in:production,staging,development,testing'],
        ]);

        $server = Server::create([
            'name' => $data['name'],
            'environment' => $data['environment'],
            'status' => 'active',
        ]);

        $token = $tokens->generateRegistrationToken($server);

        $audit->userAction(
            action: 'server.create',
            resourceType: 'server',
            resourceId: (string) $server->id,
            description: "Created server {$server->name}",
            serverId: $server->id,
        );

        return response()->json([
            'server' => $this->serverPayload($server),
            'token' => $token,
        ], 201);
    }

    /**
     * DELETE /api/servers/{server} — hard delete.
     */
    public function destroy(Server $server, AuditService $audit): JsonResponse
    {
        $name = $server->name;

        $audit->userAction(
            action: 'server.delete',
            resourceType: 'server',
            resourceId: (string) $server->id,
            description: "Deleted server {$name}",
        );

        $server->forceDelete();

        return response()->json(['message' => "Server {$name} berhasil dihapus."]);
    }

    /**
     * GET /api/servers/{server}/metrics?range=30m|1h|3h|24h (default: 1h)
     */
    public function metrics(Request $request, Server $server): JsonResponse
    {
        $range = $request->input('range', '1h');
        $since = match ($range) {
            '30m' => now()->subMinutes(30),
            '1h' => now()->subHour(),
            '3h' => now()->subHours(3),
            '24h' => now()->subHours(24),
            default => now()->subHour(),
        };

        $history = $server->metrics()
            ->where('collected_at', '>=', $since)
            ->orderBy('collected_at')
            ->get(['cpu_percent', 'ram_used_mb', 'ram_total_mb', 'disk_used_gb', 'disk_total_gb', 'network_sent_mbps', 'network_recv_mbps', 'collected_at']);

        return response()->json([
            'history' => $history,
            'latest' => $server->latestMetric(),
            'range' => $range,
        ]);
    }

    /**
     * GET /api/servers/{server}/services?search=...&page=1
     */
    public function services(Request $request, Server $server): JsonResponse
    {
        $query = $server->services();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('service_name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('service_name')->paginate(20);

        return response()->json($services);
    }

    /**
     * GET /api/servers/{server}/commands
     */
    public function commands(Server $server): JsonResponse
    {
        $commands = $server->commands()->with('user')->latest('requested_at')->limit(50)->get();

        return response()->json(['commands' => $commands]);
    }

    /**
     * GET /api/servers/{server}/update-script — generate fresh token + PowerShell script.
     */
    public function updateScript(Server $server, AgentTokenService $tokens): JsonResponse
    {
        $latestVersion = config('agent.latest_version', '1.0.6');
        $downloadUrl = config('agent.download_url', url('/download/agent'));
        $serverUrl = config('app.url');
        $token = $tokens->generateRegistrationToken($server);
        $generatedAt = now()->format('Y-m-d H:i:s');

        $script = <<<POWERSHELL
        # ========================================
        # SentraGuard Agent v{$latestVersion} Update Script
        # Server: {$server->name}
        # Generated: {$generatedAt}
        # ========================================

        Write-Host "`n[1/7] Stop & uninstall agent lama..." -ForegroundColor Yellow
        & "\$env:TEMP\sentraguard-agent.exe" stop 2>\$null
        Start-Sleep -Seconds 3
        & "\$env:TEMP\sentraguard-agent.exe" uninstall 2>\$null

        Write-Host "`n[2/7] Clean up old files..." -ForegroundColor Yellow
        Remove-Item "C:\ProgramData\SentraGuard Agent" -Recurse -Force -ErrorAction SilentlyContinue
        Remove-Item "\$env:TEMP\sentraguard-agent.exe" -ErrorAction SilentlyContinue

        Write-Host "`n[3/7] Download v{$latestVersion}..." -ForegroundColor Yellow
        Invoke-RestMethod "{$downloadUrl}?v=\$(Get-Random)" -OutFile \$env:TEMP\sentraguard-agent.exe

        Write-Host "`n[4/7] Verify version..." -ForegroundColor Yellow
        & "\$env:TEMP\sentraguard-agent.exe" version

        Write-Host "`n[5/7] Installing with fresh token..." -ForegroundColor Yellow
        & "\$env:TEMP\sentraguard-agent.exe" install --server {$serverUrl} --token {$token}

        Write-Host "`n[6/7] Wait for agent startup (30s)..." -ForegroundColor Yellow
        Start-Sleep -Seconds 30

        Write-Host "`n[7/7] Check log..." -ForegroundColor Yellow
        Get-Content "C:\ProgramData\SentraGuard Agent\logs\agent.log" -Tail 15

        Write-Host "`nSELESAI! Refresh dashboard untuk verify v{$latestVersion}." -ForegroundColor Green
        POWERSHELL;

        return response()->json([
            'script' => $script,
            'token' => $token,
            'latest_version' => $latestVersion,
        ]);
    }

    /**
     * POST /api/servers/{server}/update-agent — queue 'update' command.
     */
    public function updateAgent(Request $request, Server $server, CommandService $commands, AuditService $audit): JsonResponse
    {
        try {
            $commands->queueRaw(
                server: $server,
                command: 'update',
                userId: $request->user()->id,
            );

            $audit->userAction(
                action: 'agent.update_triggered',
                resourceType: 'server',
                resourceId: (string) $server->id,
                description: "Triggered agent self-update for {$server->name}",
                serverId: $server->id,
            );

            return response()->json(['message' => 'Update agent berhasil di-queue.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal queue update: '.$e->getMessage()], 422);
        }
    }

    /**
     * POST /api/servers/{server}/services/{service}/toggle-allow
     */
    public function toggleAllow(Server $server, ServerService $service, AuditService $audit): JsonResponse
    {
        abort_unless($service->server_id === $server->id, 404);

        $service->update(['is_allowed' => ! $service->is_allowed]);

        $audit->userAction(
            action: 'allowlist.toggle',
            resourceType: 'service',
            resourceId: (string) $service->id,
            description: ($service->is_allowed ? 'Allowed' : 'Disallowed')." service {$service->service_name}",
            serverId: $server->id,
        );

        return response()->json(['service' => $service->fresh()]);
    }

    /**
     * POST /api/servers/{server}/services/{service}/action
     */
    public function serviceAction(Request $request, Server $server, ServerService $service, CommandService $commands): JsonResponse
    {
        abort_unless($service->server_id === $server->id, 404);

        $action = $request->validate(['action' => ['required', 'string']])['action'];

        try {
            $commands->queue(
                server: $server,
                serviceName: $service->service_name,
                action: $action,
                userId: $request->user()->id,
            );

            return response()->json(['message' => "Command {$action} untuk {$service->service_name} berhasil di-queue."]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Shape a server for the SPA.
     */
    protected function serverPayload(Server $server, bool $detail = false): array
    {
        $agent = $server->agent;
        $latestVersion = config('agent.latest_version', '1.0.6');
        $currentVersion = $agent->agent_version ?? null;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'hostname' => $server->hostname,
            'environment' => $server->environment,
            'public_ip' => $server->public_ip,
            'private_ip' => $server->private_ip,
            'os_name' => $server->os_name,
            'os_version' => $server->os_version,
            'status' => $server->status,
            'agent' => $agent ? [
                'agent_uid' => $agent->agent_uid,
                'agent_version' => $agent->agent_version,
                'status' => $agent->status,
                'last_heartbeat_at' => $agent->last_heartbeat_at,
                'registered_at' => $agent->registered_at,
            ] : null,
            'connection_status' => (! $agent || ! $agent->last_heartbeat_at) ? 'waiting connection' : $agent->status,
            'update_available' => $currentVersion ? version_compare($currentVersion, $latestVersion, '<') : false,
            'latest_version' => $latestVersion,
        ];
    }
}
