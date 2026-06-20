<div wire:poll.10000ms="loadMetrics">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('servers.index') }}" class="text-xs font-bold uppercase tracking-wide text-ink-soft hover:text-accent">&larr; Kembali ke daftar</a>
            <h2 class="mt-1 text-2xl swiss-display text-ink">{{ $server->name }}</h2>
        </div>
        @php $st = $server->agent->status ?? 'inactive'; @endphp
        <span class="inline-flex w-fit items-center gap-1.5 border-2 border-ink px-3 py-1 text-xs font-bold uppercase
            {{ $st === 'online' ? 'bg-ok text-white' : ($st === 'revoked' ? 'bg-danger text-white' : 'bg-neutral text-white') }}">
            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
            {{ $st }}
        </span>
    </div>

    @if (session('cmd_ok'))
        <div class="mt-4 border-2 border-ok bg-ok/10 px-4 py-3 text-sm font-medium text-ink">{{ session('cmd_ok') }}</div>
    @endif
    @if (session('cmd_err'))
        <div class="mt-4 border-2 border-danger bg-danger/10 px-4 py-3 text-sm font-medium text-ink">{{ session('cmd_err') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @foreach (['overview' => 'Overview', 'metrics' => 'Metrics', 'services' => 'Services', 'commands' => 'Command History'] as $key => $label)
            <button wire:click="setTab('{{ $key }}')"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wide transition border-2 border-ink
                           {{ $tab === $key ? 'bg-accent-2 text-ink brutal brutal-press' : 'bg-white text-ink-soft hover:bg-paper' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-6">
        {{-- Overview --}}
        @if ($tab === 'overview')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $info = [
                        'Hostname' => $server->hostname ?? '—',
                        'OS' => trim(($server->os_name ?? '').' '.($server->os_version ?? '')) ?: '—',
                        'Environment' => $server->environment,
                        'Private IP' => $server->private_ip ?? '—',
                        'Public IP' => $server->public_ip ?? '—',
                        'Agent Version' => $server->agent->agent_version ?? '—',
                        'Last Heartbeat' => $server->agent?->last_heartbeat_at?->diffForHumans() ?? 'belum pernah',
                        'Registered' => $server->agent?->registered_at?->format('d M Y H:i') ?? '—',
                    ];
                @endphp
                @foreach ($info as $label => $value)
                    <div class="neu p-4">
                        <p class="swiss-label">{{ $label }}</p>
                        <p class="mt-1.5 text-sm font-semibold text-ink">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Metrics --}}
        @if ($tab === 'metrics')
            <div class="grid gap-4 sm:grid-cols-3">
                @php
                    $cpu = $latest->cpu_percent ?? 0;
                    $ramPct = $latest && $latest->ram_total_mb ? round($latest->ram_used_mb / $latest->ram_total_mb * 100, 1) : 0;
                    $diskPct = $latest && $latest->disk_total_gb ? round($latest->disk_used_gb / $latest->disk_total_gb * 100, 1) : 0;
                    $gauges = [
                        ['CPU', $cpu.'%', $cpu],
                        ['RAM', $ramPct.'%', $ramPct],
                        ['Disk', $diskPct.'%', $diskPct],
                    ];
                @endphp
                @foreach ($gauges as [$label, $display, $pct])
                    <div class="neu p-5">
                        <div class="flex items-center justify-between">
                            <p class="swiss-label">{{ $label }}</p>
                            <p class="text-xl swiss-display text-ink" style="font-family: var(--font-mono);">{{ $display }}</p>
                        </div>
                        <div class="mt-3 h-3 w-full overflow-hidden border-2 border-ink bg-paper">
                            <div class="h-full {{ $pct > 85 ? 'bg-danger' : ($pct > 60 ? 'bg-accent-2' : 'bg-ok') }}"
                                 {!! 'style="width: ' . min($pct, 100) . '%;"' !!}></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 bg-white brutal-lg p-5">
                <p class="text-sm font-bold uppercase tracking-wide text-ink">Riwayat 1 jam terakhir</p>
                <canvas id="metrics-chart" class="mt-3" height="100" data-history='@json($history)'></canvas>
                <p class="mt-2 text-xs text-ink-soft/60">{{ count($history) }} titik data · auto-refresh tiap 10 detik</p>
            </div>
        @endif

        {{-- Services --}}
        @if ($tab === 'services')
            <div class="bg-white brutal-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-ink text-left swiss-label">
                                <th class="px-6 py-3">Service</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Startup</th>
                                <th class="px-6 py-3">Allowed</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/8">
                            @forelse ($services as $svc)
                                <tr wire:key="svc-{{ $svc->id }}" class="hover:bg-paper-soft">
                                    <td class="px-6 py-3">
                                        <p class="font-bold text-ink" style="font-family: var(--font-mono);">{{ $svc->service_name }}</p>
                                        <p class="text-xs text-ink-soft/70">{{ $svc->display_name }}</p>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex border-2 border-ink px-2.5 py-0.5 text-xs font-bold uppercase
                                            {{ $svc->status === 'Running' ? 'bg-ok text-white' : 'bg-neutral text-white' }}">
                                            {{ $svc->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-ink-soft">{{ $svc->startup_type }}</td>
                                    <td class="px-6 py-3">
                                        <button wire:click="toggleAllow({{ $svc->id }})"
                                                class="relative inline-flex h-6 w-11 items-center border-2 border-ink transition {{ $svc->is_allowed ? 'bg-ok' : 'bg-paper' }}">
                                            <span class="inline-block h-4 w-4 transform border-2 border-ink bg-white transition {{ $svc->is_allowed ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        @if ($svc->is_allowed)
                                            <div class="inline-flex gap-1.5">
                                                <button wire:click="runAction({{ $svc->id }}, 'start_service')" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase text-ok hover:bg-ok hover:text-white transition">Start</button>
                                                <button wire:click="runAction({{ $svc->id }}, 'stop_service')" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase text-danger hover:bg-danger hover:text-white transition">Stop</button>
                                                <button wire:click="runAction({{ $svc->id }}, 'restart_service')" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase text-accent hover:bg-accent hover:text-white transition">Restart</button>
                                            </div>
                                        @else
                                            <span class="text-xs text-ink-soft/40">terkunci</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-12 text-center text-ink-soft/50">Belum ada service. Tunggu agent melakukan sync.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Command History --}}
        @if ($tab === 'commands')
            <div class="bg-white brutal-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-ink text-left swiss-label">
                                <th class="px-6 py-3">Aksi</th>
                                <th class="px-6 py-3">Service</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Oleh</th>
                                <th class="px-6 py-3">Waktu</th>
                                <th class="px-6 py-3">Exit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/8">
                            @forelse ($commands as $cmd)
                                <tr class="hover:bg-paper-soft">
                                    <td class="px-6 py-3 text-ink">{{ str_replace('_', ' ', $cmd->action) }}</td>
                                    <td class="px-6 py-3 text-ink-soft" style="font-family: var(--font-mono);">{{ $cmd->service_name }}</td>
                                    <td class="px-6 py-3"><span class="text-xs font-bold uppercase text-ink-soft">{{ $cmd->status }}</span></td>
                                    <td class="px-6 py-3 text-ink-soft">{{ $cmd->user->name ?? 'system' }}</td>
                                    <td class="px-6 py-3 text-ink-soft/60">{{ $cmd->requested_at?->diffForHumans() }}</td>
                                    <td class="px-6 py-3 text-ink-soft/60" style="font-family: var(--font-mono);">{{ $cmd->exit_code ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-12 text-center text-ink-soft/50">Belum ada command.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
