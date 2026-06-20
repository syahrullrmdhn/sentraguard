<?php

namespace App\Livewire;

use App\Models\ServiceCommand;
use Livewire\Component;
use Livewire\WithPagination;

class CommandQueue extends Component
{
    use WithPagination;

    public string $status = '';
    public string $search = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $commands = ServiceCommand::with(['server', 'user'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where('service_name', 'like', "%{$this->search}%")
                ->orWhereHas('server', fn ($s) => $s->where('name', 'like', "%{$this->search}%")))
            ->latest('requested_at')
            ->paginate(20);

        $counts = ServiceCommand::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('livewire.command-queue', compact('commands', 'counts'));
    }
}
