<div wire:poll.8000ms>
    {{-- Filter chips --}}
    @php
        $filters = ['' => 'Semua', 'pending' => 'Pending', 'picked' => 'Picked', 'running' => 'Running', 'success' => 'Success', 'failed' => 'Failed', 'timeout' => 'Timeout', 'rejected' => 'Rejected'];
        $statusTone = [
            'pending' => 'bg-accent-2 text-ink',
            'picked' => 'bg-accent text-white',
            'running' => 'bg-accent text-white',
            'success' => 'bg-ok text-white',
            'failed' => 'bg-danger text-white',
            'timeout' => 'bg-danger text-white',
            'rejected' => 'bg-danger text-white',
            'cancelled' => 'bg-paper text-ink-soft',
        ];
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
            @foreach ($filters as $key => $label)
                <button wire:click="$set('status', '{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide border-2 border-ink transition
                               {{ $status === $key ? 'bg-ink text-white brutal brutal-press' : 'bg-white text-ink-soft hover:bg-paper' }}">
                    {{ $label }}
                    @if (isset($counts[$key]) && $key)
                        <span class="ml-1 opacity-70">{{ $counts[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari service / server..."
               class="w-full max-w-xs neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
    </div>

    {{-- Table --}}
    <div class="mt-5 bg-white brutal-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink text-left swiss-label">
                        <th class="px-6 py-3.5">Server</th>
                        <th class="px-6 py-3.5">Service</th>
                        <th class="px-6 py-3.5">Aksi</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Oleh</th>
                        <th class="px-6 py-3.5">Diminta</th>
                        <th class="px-6 py-3.5">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/8">
                    @forelse ($commands as $cmd)
                        <tr wire:key="cmd-{{ $cmd->id }}" class="hover:bg-paper-soft">
                            <td class="px-6 py-3 font-bold text-ink">{{ $cmd->server->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-ink-soft" style="font-family: var(--font-mono);">{{ $cmd->service_name }}</td>
                            <td class="px-6 py-3 text-ink-soft">{{ str_replace('_', ' ', $cmd->action) }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex border-2 border-ink px-2.5 py-0.5 text-xs font-bold uppercase {{ $statusTone[$cmd->status] ?? 'bg-paper text-ink' }}">
                                    {{ $cmd->status }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-ink-soft">{{ $cmd->user->name ?? 'system' }}</td>
                            <td class="px-6 py-3 text-ink-soft/60">{{ $cmd->requested_at?->diffForHumans() }}</td>
                            <td class="px-6 py-3 text-ink-soft/60">{{ $cmd->finished_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-ink-soft/50">Tidak ada command sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $commands->links() }}</div>
</div>
