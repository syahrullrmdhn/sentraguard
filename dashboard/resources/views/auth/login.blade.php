<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · SentraGuard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    {{-- Brutalist decorative blocks --}}
    <div class="pointer-events-none fixed left-10 top-16 hidden h-24 w-24 bg-accent-2 border-2 border-ink lg:block" style="box-shadow: 6px 6px 0 0 #111;"></div>
    <div class="pointer-events-none fixed bottom-16 right-16 hidden h-16 w-16 bg-accent lg:block" style="box-shadow: 6px 6px 0 0 #111; border: 2px solid #111;"></div>

    <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center">
            <div class="flex h-16 w-16 items-center justify-center bg-accent-2 text-ink brutal-lg">
                <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
            </div>
            <h1 class="mt-5 text-2xl swiss-display text-ink">SENTRAGUARD</h1>
            <p class="mt-1 swiss-label">Windows Service Control &amp; Monitoring</p>
        </div>

        <div class="bg-white p-8 brutal-lg">
            @if ($errors->any())
                <div class="mb-5 bg-danger/10 px-4 py-3 text-sm font-medium text-ink border-2 border-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="swiss-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="mt-2 block w-full neu-inset px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                           placeholder="admin@sentraguard.local">
                </div>
                <div>
                    <label for="password" class="swiss-label">Password</label>
                    <input id="password" name="password" type="password" required
                           class="mt-2 block w-full neu-inset px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                           placeholder="••••••••">
                </div>
                <div class="flex items-center">
                    <input id="remember" name="remember" type="checkbox"
                           class="h-4 w-4 border-2 border-ink text-accent focus:ring-accent/30">
                    <label for="remember" class="ml-2 text-sm font-medium text-ink-soft">Ingat saya</label>
                </div>
                <button type="submit"
                        class="w-full bg-accent px-4 py-3 text-sm font-bold uppercase tracking-wide text-white brutal brutal-hover brutal-press">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
