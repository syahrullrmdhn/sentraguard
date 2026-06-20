<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search = '';
    public string $result = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingResult(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::with(['user', 'server'])
            ->when($this->result, fn ($q) => $q->where('result', $this->result))
            ->when($this->search, fn ($q) => $q->where('action', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
                ->orWhere('actor_identifier', 'like', "%{$this->search}%"))
            ->latest('created_at')
            ->paginate(25);

        return view('livewire.audit-log-viewer', compact('logs'));
    }
}
