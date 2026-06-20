<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Server;
use App\Models\ServiceCommand;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard overview.
     */
    public function index(): View
    {
        $stats = [
            'total_servers' => Server::count(),
            'online_agents' => Agent::where('status', 'online')->count(),
            'offline_agents' => Agent::whereIn('status', ['offline', 'inactive'])->count(),
            'pending_commands' => ServiceCommand::where('status', 'pending')->count(),
            'failed_commands_today' => ServiceCommand::whereIn('status', ['failed', 'timeout', 'rejected'])
                ->whereDate('finished_at', today())
                ->count(),
            'service_events_today' => AuditLog::where('action', 'service.state_change')
                ->whereDate('created_at', today())
                ->count(),
        ];

        $recentCommands = ServiceCommand::with(['server', 'user'])
            ->latest('requested_at')
            ->limit(10)
            ->get();

        $offlineAgents = Agent::with('server')
            ->whereIn('status', ['offline', 'inactive'])
            ->latest('last_heartbeat_at')
            ->limit(5)
            ->get();

        $recentEvents = AuditLog::with('server')
            ->where('action', 'service.state_change')
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentLogs = AuditLog::with(['user', 'server'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'stats',
            'recentCommands',
            'offlineAgents',
            'recentEvents',
            'recentLogs'
        ));
    }
}
