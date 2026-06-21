<script setup lang="ts">
const { user, logout } = useAuth()
const route = useRoute()

const nav = [
  { to: '/', label: 'Dashboard', icon: '▦' },
  { to: '/servers', label: 'Servers', icon: '▤' },
  { to: '/commands', label: 'Commands', icon: '⌘' },
  { to: '/users', label: 'Users', icon: '◉' },
  { to: '/audit', label: 'Audit Logs', icon: '☰' },
]

const isActive = (to: string) =>
  to === '/' ? route.path === '/' : route.path.startsWith(to)

const handleLogout = async () => {
  await logout()
  navigateTo('/login')
}
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="flex w-64 shrink-0 flex-col border-r-2 border-ink bg-white/95">
      <div class="flex items-center gap-2 border-b-2 border-ink px-5 py-4">
        <div class="flex h-10 w-10 items-center justify-center bg-accent-2 text-ink brutal-sm font-bold">S</div>
        <div>
          <p class="text-sm font-bold uppercase tracking-wide text-ink leading-tight">SentraGuard</p>
          <p class="text-[0.6rem] uppercase tracking-widest text-ink-soft">AgentOps Console</p>
        </div>
      </div>

      <nav class="flex-1 space-y-1 p-3">
        <NuxtLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 border-2 px-3 py-2.5 text-sm font-bold uppercase tracking-wide transition"
          :class="isActive(item.to)
            ? 'border-ink bg-accent-2 text-ink brutal-sm'
            : 'border-transparent text-ink-soft hover:border-ink hover:bg-paper'"
        >
          <span class="text-base">{{ item.icon }}</span>
          {{ item.label }}
        </NuxtLink>
      </nav>

      <div class="border-t-2 border-ink p-3">
        <div class="flex items-center gap-3 px-2 py-2">
          <div class="flex h-9 w-9 items-center justify-center bg-accent text-white brutal-sm text-sm font-bold">
            {{ user?.name?.charAt(0) ?? '?' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-ink">{{ user?.name }}</p>
            <p class="text-[0.6rem] uppercase tracking-widest text-ink-soft">{{ user?.role?.replace('_', ' ') }}</p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="mt-2 w-full border-2 border-ink bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-ink transition hover:bg-danger hover:text-white brutal-sm brutal-press"
        >
          Keluar
        </button>
      </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 overflow-x-hidden">
      <div class="mx-auto max-w-7xl p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
