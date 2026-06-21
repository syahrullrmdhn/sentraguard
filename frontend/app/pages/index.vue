<script setup lang="ts">
const api = useApi()

interface DashboardData {
  stats: {
    total_servers: number
    online_agents: number
    offline_agents: number
    pending_commands: number
    failed_commands_today: number
    service_events_today: number
  }
  recentCommands: any[]
  offlineAgents: any[]
  recentLogs: any[]
}

const { data, pending, refresh } = await useAsyncData('dashboard', () =>
  api.get<DashboardData>('/api/dashboard')
)

const statCards = computed(() => {
  const s = data.value?.stats
  if (!s) return []
  return [
    { label: 'Total Servers', value: s.total_servers, accent: 'bg-white' },
    { label: 'Online Agents', value: s.online_agents, accent: 'bg-ok/10' },
    { label: 'Offline Agents', value: s.offline_agents, accent: 'bg-neutral/5' },
    { label: 'Pending Commands', value: s.pending_commands, accent: 'bg-accent-2/20' },
    { label: 'Failed Today', value: s.failed_commands_today, accent: 'bg-danger/10' },
    { label: 'Service Events', value: s.service_events_today, accent: 'bg-white' },
  ]
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl swiss-display text-ink">Dashboard</h1>
      <button @click="refresh()" class="border-2 border-ink bg-white px-3 py-1.5 text-xs font-bold uppercase brutal-sm brutal-press">
        Refresh
      </button>
    </div>

    <div v-if="pending" class="mt-6 text-sm text-ink-soft">Memuat...</div>

    <div v-else class="mt-6 space-y-6">
      <!-- Stat cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="card in statCards" :key="card.label" class="brutal p-5" :class="card.accent">
          <p class="swiss-label">{{ card.label }}</p>
          <p class="mt-2 text-3xl swiss-display text-ink">{{ card.value }}</p>
        </div>
      </div>

      <!-- Recent commands -->
      <div class="brutal-lg bg-white">
        <div class="border-b-2 border-ink px-5 py-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Recent Commands</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b-2 border-ink text-left swiss-label">
                <th class="px-5 py-2.5">Server</th>
                <th class="px-5 py-2.5">Action</th>
                <th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5">By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr v-for="cmd in data?.recentCommands ?? []" :key="cmd.id">
                <td class="px-5 py-2.5 font-semibold">{{ cmd.server?.name ?? '—' }}</td>
                <td class="px-5 py-2.5 font-mono text-xs">{{ cmd.action }}</td>
                <td class="px-5 py-2.5">
                  <span class="inline-flex border border-ink px-2 py-0.5 text-xs font-bold uppercase"
                    :class="{
                      'bg-ok text-white': cmd.status === 'success',
                      'bg-danger text-white': ['failed','timeout','rejected'].includes(cmd.status),
                      'bg-accent-2 text-ink': cmd.status === 'pending',
                    }">{{ cmd.status }}</span>
                </td>
                <td class="px-5 py-2.5 text-ink-soft">{{ cmd.user?.name ?? 'system' }}</td>
              </tr>
              <tr v-if="!(data?.recentCommands ?? []).length">
                <td colspan="4" class="px-5 py-6 text-center text-ink-soft">Belum ada command.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
