@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $cards = [
        ['label' => 'Total Server', 'value' => $stats['total_servers'], 'accent' => 'bg-white'],
        ['label' => 'Agent Online', 'value' => $stats['online_agents'], 'accent' => 'bg-ok/15'],
        ['label' => 'Agent Offline', 'value' => $stats['offline_agents'], 'accent' => 'bg-danger/12'],
        ['label' => 'Command Pending', 'value' => $stats['pending_commands'], 'accent' => 'bg-accent-2/40'],
        ['label' => 'Command Gagal (hari ini)', 'value' => $stats['failed_commands_today'], 'accent' => 'bg-danger/12'],
        ['label' => 'Perubahan Service (hari ini)', 'value' => $stats['service_events_today'], 'accent' => 'bg-white'],
    ];
    $statusTone = [
        'pending' => 'bg-accent-2 text-ink',
        'picked' => 'bg-accent text-white',
        'running' => 'bg-accent text-white',
        'success' => 'bg-ok text-white',
        'failed' => 'bg-danger text-white',
        'timeout' => 'bg-danger text-white',
        'rejected' => 'bg-danger text-white',
        'cancelled' => 'bg-neutral text-white',
    ];
@endphp

{{-- Stat tiles: Neumorphism + Swiss numerals --}}
<div class="grid grid-cols-2 gap-5 px-1 py-2 lg:grid-cols-3 xl:grid-cols-6">
    @foreach ($cards as $card)
        <div class="neu p-5">
            <p class="swiss-label">{{ $card['label'] }}</p>
            <p class="mt-3 text-4xl swiss-display text-ink" style="font-family: var(--font-mono);">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
    {{-- Recent commands: brutalist panel --}}
    <div class="lg:col-span-2 bg-white brutal-lg">
        <div class="flex items-center justify-between border-b-2 border-ink px-6 py-4">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Command Terbaru</h2>
            <span class="h-3 w-3 bg-accent border-2 border-ink"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink/10 text-left swiss-label">
                        <th class="px-6 py-3">Server</th>
                        <th class="px-6 py-3">Service</th>
                        <th class="px-6 py-3">Aksi</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/8">
                    @forelse ($recentCommands as $cmd)
                        <tr class="hover:bg-paper-soft transition">
                            <td class="px-6 py-3 font-semibold text-ink">{{ $cmd->server->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-ink-soft" style="font-family: var(--font-mono);">{{ $cmd->service_name }}</td>
                            <td class="px-6 py-3 text-ink-soft">{{ str_replace('_', ' ', $cmd->action) }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex border-2 border-ink px-2.5 py-0.5 text-xs font-bold {{ $statusTone[$cmd->status] ?? 'bg-paper text-ink' }}">
                                    {{ strtoupper($cmd->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-ink-soft/60">{{ $cmd->requested_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-ink-soft/50">Belum ada command.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Side column --}}
    <div class="space-y-6">
        <div class="bg-white brutal-lg">
            <div class="border-b-2 border-ink px-6 py-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Agent Offline</h2>
            </div>
            <ul class="divide-y divide-ink/8">
                @forelse ($offlineAgents as $agent)
                    <li class="flex items-center justify-between px-6 py-3">
                        <span class="text-sm font-semibold text-ink">{{ $agent->server->name ?? '—' }}</span>
                        <span class="text-xs text-ink-soft/60">{{ $agent->last_heartbeat_at?->diffForHumans() ?? 'belum pernah' }}</span>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-ink-soft/50">Semua agent online ✅</li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white brutal-lg">
            <div class="border-b-2 border-ink px-6 py-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Perubahan Service Terbaru</h2>
            </div>
            <ul class="divide-y divide-ink/8">
                @forelse ($recentEvents as $event)
                    <li class="px-6 py-3">
                        <p class="text-sm text-ink">{{ $event->description }}</p>
                        <p class="text-xs text-ink-soft/60">{{ $event->created_at?->diffForHumans() }}</p>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-ink-soft/50">Tidak ada perubahan service.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
