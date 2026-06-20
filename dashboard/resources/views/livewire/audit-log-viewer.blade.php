<div>
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-2">
            @foreach (['' => 'Semua', 'success' => 'Success', 'failed' => 'Failed'] as $key => $label)
                <button wire:click="$set('result', '{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide border-2 border-ink transition
                               {{ $result === $key ? 'bg-ink text-white brutal brutal-press' : 'bg-white text-ink-soft hover:bg-paper' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari aksi / deskripsi / aktor..."
               class="w-full max-w-sm neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
    </div>

    {{-- Table --}}
    <div class="mt-5 bg-white brutal-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink text-left swiss-label">
                        <th class="px-6 py-3.5">Waktu</th>
                        <th class="px-6 py-3.5">Aktor</th>
                        <th class="px-6 py-3.5">Aksi</th>
                        <th class="px-6 py-3.5">Deskripsi</th>
                        <th class="px-6 py-3.5">Server</th>
                        <th class="px-6 py-3.5">Hasil</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/8">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}" class="hover:bg-paper-soft">
                            <td class="px-6 py-3 text-ink-soft/70 whitespace-nowrap" style="font-family: var(--font-mono);">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                            <td class="px-6 py-3 font-semibold text-ink">{{ $log->user->name ?? $log->actor_identifier ?? 'system' }}</td>
                            <td class="px-6 py-3"><span class="text-xs font-bold uppercase text-accent" style="font-family: var(--font-mono);">{{ $log->action }}</span></td>
                            <td class="px-6 py-3 text-ink-soft">{{ $log->description }}</td>
                            <td class="px-6 py-3 text-ink-soft">{{ $log->server->name ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex border-2 border-ink px-2.5 py-0.5 text-xs font-bold uppercase
                                    {{ $log->result === 'success' ? 'bg-ok text-white' : ($log->result === 'failed' ? 'bg-danger text-white' : 'bg-paper text-ink-soft') }}">
                                    {{ $log->result ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-ink-soft/50">Belum ada audit log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
