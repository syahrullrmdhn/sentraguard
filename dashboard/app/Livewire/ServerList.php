<?php

namespace App\Livewire;

use App\Models\Server;
use App\Services\AgentTokenService;
use App\Services\AuditService;
use Livewire\Component;
use Livewire\WithPagination;

class ServerList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $environment = '';

    // Create-server modal state
    public bool $showCreate = false;
    public string $name = '';
    public string $newEnvironment = 'production';
    public ?string $generatedToken = null;
    public ?string $generatedServerName = null;

    protected array $rules = [
        'name' => ['required', 'string', 'max:255'],
        'newEnvironment' => ['required', 'in:production,staging,development,testing'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'newEnvironment', 'generatedToken', 'generatedServerName']);
        $this->newEnvironment = 'production';
        $this->showCreate = true;
    }

    public function createServer(AgentTokenService $tokens, AuditService $audit): void
    {
        $this->validate();

        $server = Server::create([
            'name' => $this->name,
            'environment' => $this->newEnvironment,
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

        // Show the one-time token to the operator.
        $this->generatedToken = $token;
        $this->generatedServerName = $server->name;
        $this->reset(['name']);
    }

    public function delete($id, AuditService $audit): void
    {
        $server = Server::findOrFail($id);
        $name = $server->name;

        // Hard delete: the unique constraint on `name` counts soft-deleted rows,
        // so a soft delete would block re-creating a server with the same name.
        // All child tables (agents, services, metrics, commands) cascade on delete.
        // Audit is recorded WITHOUT serverId so the cascade doesn't wipe this log row.
        $audit->userAction(
            action: 'server.delete',
            resourceType: 'server',
            resourceId: (string) $server->id,
            description: "Deleted server {$name}",
        );

        $server->forceDelete();
        session()->flash('success', "Server {$name} berhasil dihapus.");
    }

    public function render()
    {
        $servers = Server::with('agent')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('hostname', 'like', "%{$this->search}%"))
            ->when($this->environment, fn ($q) => $q->where('environment', $this->environment))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.server-list', compact('servers'));
    }
}
