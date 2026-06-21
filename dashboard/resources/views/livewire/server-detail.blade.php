<div wire:poll.10000ms="loadMetrics">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('servers.index') }}" class="text-xs font-bold uppercase tracking-wide text-ink-soft hover:text-accent">&larr; Kembali ke daftar</a>
            <h2 class="mt-1 text-2xl swiss-display text-ink">{{ $server->name }}</h2>
        </div>
        @php
            // Jika belum pernah ada agent atau belum pernah heartbeat → waiting connection
            if (!$server->agent || !$server->agent->last_heartbeat_at) {
                $st = 'waiting connection';
                $color = 'bg-neutral text-white';
            } else {
                $st = $server->agent->status;
                $color = $st === 'online' ? 'bg-ok text-white' : ($st === 'revoked' ? 'bg-danger text-white' : 'bg-neutral text-white');
            }
        @endphp
        <span class="inline-flex w-fit items-center gap-1.5 border-2 border-ink px-3 py-1 text-xs font-bold uppercase {{ $color }}">
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

            {{-- Update Agent Button --}}
            @php
                $latestVersion = config('agent.latest_version', '1.0.6');
                $currentVersion = $server->agent->agent_version ?? '0.0.0';
                $updateAvailable = version_compare($currentVersion, $latestVersion, '<');
            @endphp
            @if ($updateAvailable && $server->agent && $server->agent->status === 'online')
                <div class="mt-6 neu p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-ink">🔄 Update Tersedia</p>
                            <p class="mt-1 text-xs text-ink-soft">
                                Agent versi <span class="font-semibold">{{ $currentVersion }}</span> → 
                                <span class="font-semibold text-accent">{{ $latestVersion }}</span>
                            </p>
                            <p class="mt-2 text-xs text-ink-soft">
                                Update akan download binary terbaru, stop service, replace file, dan restart otomatis. 
                                Proses memakan ~60 detik. Server tetap bisa diakses setelah restart.
                            </p>
                        </div>
                        <button wire:click="updateAgent" 
                                wire:loading.attr="disabled"
                                class="shrink-0 border-2 border-ink bg-accent px-4 py-2 text-xs font-bold uppercase tracking-wide text-ink transition hover:bg-accent-2 disabled:opacity-50 brutal">
                            <span wire:loading.remove wire:target="updateAgent">Update Sekarang</span>
                            <span wire:loading wire:target="updateAgent">Queueing...</span>
                        </button>
                    </div>
                </div>
            @endif
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

    {{-- Chart.js for metrics timeline --}}
    @if ($tab === 'metrics')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            (function() {
                const canvas = document.getElementById('metrics-chart');
                if (!canvas || window.metricsChart) return; // Prevent duplicate init on Livewire updates

                const history = JSON.parse(canvas.dataset.history || '[]');
                if (!history.length) {
                    const ctx = canvas.getContext('2d');
                    ctx.font = '14px Space Grotesk, sans-serif';
                    ctx.fillStyle = '#6b7280';
                    ctx.textAlign = 'center';
                    ctx.fillText('Belum ada data metrics', canvas.width / 2, canvas.height / 2);
                    return;
                }

                const labels = history.map(m => new Date(m.collected_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}));
                const cpu = history.map(m => m.cpu_percent);
                const ram = history.map(m => m.ram_total_mb ? (m.ram_used_mb / m.ram_total_mb * 100).toFixed(1) : 0);
                const disk = history.map(m => m.disk_total_gb ? (m.disk_used_gb / m.disk_total_gb * 100).toFixed(1) : 0);

                window.metricsChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'CPU %',
                                data: cpu,
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'RAM %',
                                data: ram,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'Disk %',
                                data: disk,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                tension: 0.3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {family: 'Space Grotesk', size: 12},
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + parseFloat(context.parsed.y).toFixed(1) + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) { return value + '%'; },
                                    font: {family: 'Space Mono', size: 11}
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 0,
                                    autoSkipPadding: 20,
                                    font: {family: 'Space Mono', size: 10}
                                }
                            }
                        }
                    }
                });

                // Update chart on Livewire wire:poll refresh
                document.addEventListener('livewire:update', function() {
                    if (window.metricsChart) {
                        window.metricsChart.destroy();
                        window.metricsChart = null;
                    }
                });
            })();
        </script>
    @endif
</div>
