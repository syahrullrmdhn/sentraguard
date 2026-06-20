<div>
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari server..."
                   class="w-full max-w-xs neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
            <select wire:model.live="environment"
                    class="neu-inset px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
                <option value="">Semua environment</option>
                <option value="production">Production</option>
                <option value="staging">Staging</option>
                <option value="development">Development</option>
                <option value="testing">Testing</option>
            </select>
        </div>
        <button wire:click="openCreate"
                class="inline-flex items-center gap-2 bg-accent px-4 py-2.5 text-sm font-bold uppercase tracking-wide text-white brutal brutal-hover brutal-press">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Server
        </button>
    </div>

    {{-- Table: brutalist panel --}}
    <div class="mt-5 bg-white brutal-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink text-left swiss-label">
                        <th class="px-6 py-3.5">Nama</th>
                        <th class="px-6 py-3.5">Environment</th>
                        <th class="px-6 py-3.5">IP</th>
                        <th class="px-6 py-3.5">Status Agent</th>
                        <th class="px-6 py-3.5">Heartbeat Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/8">
                    @forelse ($servers as $server)
                        <tr wire:key="server-{{ $server->id }}"
                            onclick="window.location='{{ route('servers.show', $server) }}'"
                            class="cursor-pointer hover:bg-accent-2/20 transition">
                            <td class="px-6 py-3.5 font-bold text-ink">{{ $server->name }}</td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex border-2 border-ink bg-paper px-2.5 py-0.5 text-xs font-bold uppercase text-ink">
                                    {{ $server->environment }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-ink-soft" style="font-family: var(--font-mono);">{{ $server->private_ip ?? $server->public_ip ?? '—' }}</td>
                            <td class="px-6 py-3.5">
                                @php $st = $server->agent->status ?? 'inactive'; @endphp
                                <span class="inline-flex items-center gap-1.5 border-2 border-ink px-2.5 py-0.5 text-xs font-bold uppercase
                                    {{ $st === 'online' ? 'bg-ok text-white' : ($st === 'revoked' ? 'bg-danger text-white' : 'bg-paper text-ink-soft') }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $st === 'online' ? 'bg-white' : 'bg-ink-soft' }}"></span>
                                    {{ $st }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-ink-soft/60">{{ $server->agent?->last_heartbeat_at?->diffForHumans() ?? 'belum pernah' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-ink-soft/50">Belum ada server. Tambahkan server pertama Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $servers->links() }}</div>

    {{-- Create modal: glass backdrop + brutalist card --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(17,17,17,0.45); backdrop-filter: blur(6px);" wire:click.self="$set('showCreate', false)">
            <div class="w-full max-w-md bg-white p-6 brutal-lg">
                @if ($generatedToken)
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center bg-ok text-white brutal text-sm font-bold">✓</span>
                        <h3 class="text-lg swiss-display text-ink">Server dibuat</h3>
                    </div>
                    <p class="mt-3 text-sm text-ink-soft">Salin token registrasi untuk <strong>{{ $generatedServerName }}</strong>. Token ini hanya ditampilkan sekali.</p>
                    <div class="mt-4 border-2 border-ink bg-accent-2/30 p-3">
                        <code class="block break-all text-xs text-ink" style="font-family: var(--font-mono);">{{ $generatedToken }}</code>
                    </div>
                    <p class="mt-2 text-xs text-ink-soft/70">Gunakan token ini saat instalasi agent dengan <code>--token</code>.</p>
                    <div class="mt-6 flex justify-end">
                        <button wire:click="$set('showCreate', false)" class="bg-ink px-4 py-2 text-sm font-bold uppercase tracking-wide text-white brutal brutal-hover brutal-press">Selesai</button>
                    </div>
                @else
                    <h3 class="text-lg swiss-display text-ink">Tambah Server</h3>
                    <form wire:submit="createServer" class="mt-4 space-y-4">
                        <div>
                            <label class="swiss-label">Nama Server</label>
                            <input type="text" wire:model="name" placeholder="WIN-AWS-01"
                                   class="mt-2 block w-full neu-inset px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
                            @error('name') <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="swiss-label">Environment</label>
                            <select wire:model="newEnvironment"
                                    class="mt-2 block w-full neu-inset px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
                                <option value="production">Production</option>
                                <option value="staging">Staging</option>
                                <option value="development">Development</option>
                                <option value="testing">Testing</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" wire:click="$set('showCreate', false)" class="px-4 py-2 text-sm font-semibold text-ink-soft hover:bg-paper">Batal</button>
                            <button type="submit" class="bg-accent px-4 py-2 text-sm font-bold uppercase tracking-wide text-white brutal brutal-hover brutal-press">Buat &amp; Token</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
