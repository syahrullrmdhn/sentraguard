<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ServiceCommand;
use App\Services\CommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    public function __construct(
        protected CommandService $commands
    ) {}

    /**
     * GET /api/commands — global command queue for the dashboard SPA.
     */
    public function indexForUser(Request $request): JsonResponse
    {
        $commands = ServiceCommand::with(['server', 'user'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('service_name', 'like', "%{$request->search}%")
                    ->orWhereHas('server', fn ($s) => $s->where('name', 'like', "%{$request->search}%"));
            }))
            ->latest('requested_at')
            ->paginate(20);

        $counts = ServiceCommand::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return response()->json([
            'commands' => $commands,
            'counts' => $counts,
        ]);
    }

    /**
     * GET /api/agent/commands/poll
     * Agent polls for the next pending command (atomic pickup).
     */
    public function poll(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        // Treat a poll as an implicit heartbeat too.
        $agent->heartbeat();

        $command = $this->commands->pickNextForServer($agent->server);

        if (! $command) {
            return response()->json(['has_command' => false]);
        }

        return response()->json([
            'has_command' => true,
            'command' => [
                'id' => $command->id,
                'action' => $command->action,
                'service_name' => $command->service_name,
                'timeout_seconds' => $command->timeout_seconds,
                'payload' => $command->payload ? json_decode($command->payload, true) : null,
            ],
        ]);
    }

    /**
     * POST /api/agent/commands/{command}/result
     * Agent submits the result of an executed command.
     */
    public function result(Request $request, ServiceCommand $command): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        // Ensure the command belongs to the authenticated agent's server.
        if ($command->server_id !== $agent->server_id) {
            return response()->json(['message' => 'Command does not belong to this agent.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:success,failed,timeout,rejected'],
            'exit_code' => ['nullable', 'integer'],
            'stdout' => ['nullable', 'string'],
            'stderr' => ['nullable', 'string'],
            'service_status' => ['nullable', 'string', 'max:50'],
            'startup_type' => ['nullable', 'string', 'max:50'],
            'finished_at' => ['nullable', 'date'],
        ]);

        $command = $this->commands->applyResult($command, $data);

        return response()->json([
            'success' => true,
            'command_id' => $command->id,
            'status' => $command->status,
        ]);
    }
}
