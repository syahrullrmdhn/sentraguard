<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · SentraGuard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">
<div class="min-h-full lg:flex">
    {{-- Sidebar (Light clean background) --}}
    <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 glass-dark z-20">
        <div class="flex items-center gap-3 h-16 px-6 border-b border-ink/10">
            <div class="flex h-10 w-10 items-center justify-center bg-accent-2 text-ink brutal" style="box-shadow: 3px 3px 0 0 rgba(0,0,0,0.15);">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
            </div>
            <div>
                <span class="block text-base font-bold tracking-tight leading-none text-ink">SENTRAGUARD</span>
                <span class="swiss-label text-ink-soft">AgentOps Console</span>
            </div>
        </div>
        <nav class="flex-1 px-3 py-5 space-y-2 overflow-y-auto">
            @php
                $nav = [
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M2.25 12 11.2 3.05a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
                    ['route' => 'servers.index', 'label' => 'Servers', 'icon' => 'M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z'],
                    ['route' => 'commands.index', 'label' => 'Commands', 'icon' => 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z'],
                    ['route' => 'audit.index', 'label' => 'Audit Logs', 'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
                ];
            @endphp
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold transition
                          {{ $active
                                ? 'bg-accent-2 text-ink brutal brutal-press'
                                : 'text-ink-soft hover:text-ink hover:bg-paper border-2 border-transparent' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-ink/10 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center bg-accent text-white brutal text-sm font-bold" style="box-shadow: 3px 3px 0 0 rgba(0,0,0,0.15);">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name ?? 'User' }}</p>
                    <p class="truncate swiss-label text-ink-soft">{{ auth()->user()->role->display_name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="lg:pl-64 flex-1">
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between glass px-4 lg:px-8">
            <div class="flex items-center gap-3">
                <span class="hidden sm:block h-3 w-3 bg-accent-2 border-2 border-ink"></span>
                <h1 class="text-lg swiss-display text-ink">@yield('title', 'Dashboard')</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-white px-3 py-2 text-sm font-semibold text-ink brutal brutal-hover brutal-press">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Keluar
                </button>
            </form>
        </header>
        <main class="px-4 pb-4 pt-6 lg:px-8 lg:pb-8 lg:pt-8">
            @yield('content')
        </main>
    </div>
</div>
@livewireScripts
@stack('scripts')
</body>
</html>
