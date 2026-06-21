<script setup lang="ts">
const route = useRoute()
const api = useApi()
const id = route.params.id as string

const tab = ref<'overview' | 'metrics' | 'services' | 'commands'>('overview')

// Server detail
const { data: detail, pending, refresh } = await useAsyncData(`server-${id}`, () =>
  api.get<any>(`/api/servers/${id}`)
)
const server = computed(() => detail.value?.server)

// Lazy-loaded per tab
const metrics = ref<any>(null)
const services = ref<any[]>([])
const commands = ref<any[]>([])

const loadMetrics = async () => {
  metrics.value = await api.get<any>(`/api/servers/${id}/metrics`)
}
const loadServices = async () => {
  const r = await api.get<any>(`/api/servers/${id}/services`)
  services.value = r.services
}
const loadCommands = async () => {
  const r = await api.get<any>(`/api/servers/${id}/commands`)
  commands.value = r.commands
}

watch(tab, (t) => {
  if (t === 'metrics' && !metrics.value) loadMetrics()
  if (t === 'services' && !services.value.length) loadServices()
  if (t === 'commands' && !commands.value.length) loadCommands()
})

// Auto-refresh overview/metrics every 10s
let timer: any = null
onMounted(() => {
  timer = setInterval(async () => {
    await refresh()
    if (tab.value === 'metrics') await loadMetrics()
  }, 10000)
})
onUnmounted(() => clearInterval(timer))

const statusBadge = computed(() => {
  const st = server.value?.connection_status
  if (st === 'online') return 'bg-ok text-white'
  if (st === 'revoked') return 'bg-danger text-white'
  return 'bg-neutral text-white'
})

const infoCards = computed(() => {
  const s = server.value
  if (!s) return []
  return [
    ['Hostname', s.hostname ?? '—'],
    ['OS', [s.os_name, s.os_version].filter(Boolean).join(' ') || '—'],
    ['Environment', s.environment],
    ['Private IP', s.private_ip ?? '—'],
    ['Public IP', s.public_ip ?? 'waiting / not connected'],
    ['Agent Version', s.agent?.agent_version ?? '—'],
    ['Last Heartbeat', s.agent?.last_heartbeat_at ? timeAgo(s.agent.last_heartbeat_at) : 'belum pernah'],
    ['Registered', s.agent?.registered_at ? new Date(s.agent.registered_at).toLocaleString('id-ID') : '—'],
  ]
})

function timeAgo(d: string) {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return `${diff} detik lalu`
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return `${Math.floor(diff / 86400)} hari lalu`
}

// ---- Update agent modal ----
const showUpdate = ref(false)
const updateScript = ref('')
const scriptLoading = ref(false)
const copyLabel = ref('Copy Script')

const openUpdate = async () => {
  showUpdate.value = true
  scriptLoading.value = true
  try {
    const r = await api.get<any>(`/api/servers/${id}/update-script`)
    updateScript.value = r.script
  } finally {
    scriptLoading.value = false
  }
}
const copyScript = () => {
  navigator.clipboard.writeText(updateScript.value)
  copyLabel.value = 'Copied!'
  setTimeout(() => (copyLabel.value = 'Copy Script'), 2000)
}

// ---- Service actions ----
const toggleAllow = async (svc: any) => {
  await api.post(`/api/servers/${id}/services/${svc.id}/toggle-allow`)
  await loadServices()
}
const runAction = async (svc: any, action: string) => {
  try {
    await api.post(`/api/servers/${id}/services/${svc.id}/action`, { action })
    await loadCommands()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal queue command.')
  }
}

const pct = (used: number, total: number) => (total ? Math.min(Math.round((used / total) * 1000) / 10, 100) : 0)
const barColor = (p: number) => (p > 85 ? 'bg-danger' : p > 60 ? 'bg-accent-2' : 'bg-ok')
</script>

<template>
  <div v-if="pending" class="text-sm text-ink-soft">Memuat...</div>

  <div v-else-if="server">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <NuxtLink to="/servers" class="text-xs font-bold uppercase tracking-wide text-ink-soft hover:text-accent">&larr; Kembali ke daftar</NuxtLink>
        <h1 class="mt-1 text-2xl swiss-display text-ink">{{ server.name }}</h1>
      </div>
      <span class="inline-flex w-fit items-center gap-1.5 border-2 border-ink px-3 py-1 text-xs font-bold uppercase" :class="statusBadge">
        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>{{ server.connection_status }}
      </span>
    </div>

    <!-- Tabs -->
    <div class="mt-6 flex flex-wrap gap-2">
      <button v-for="(label, key) in { overview: 'Overview', metrics: 'Metrics', services: 'Services', commands: 'Command History' }" :key="key"
        @click="tab = key as any"
        class="border-2 border-ink px-4 py-2 text-xs font-bold uppercase tracking-wide transition"
        :class="tab === key ? 'bg-accent-2 text-ink brutal-sm brutal-press' : 'bg-white text-ink-soft hover:bg-paper'">
        {{ label }}
      </button>
    </div>

    <div class="mt-6">
      <!-- OVERVIEW -->
      <div v-if="tab === 'overview'">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="[label, value] in infoCards" :key="label" class="brutal bg-white p-4">
            <p class="swiss-label">{{ label }}</p>
            <p class="mt-1.5 text-sm font-semibold text-ink break-words">{{ value }}</p>
          </div>
        </div>

        <!-- Update available -->
        <div v-if="server.update_available" class="mt-6 border-2 border-accent bg-accent/5 p-6 shadow-brutal">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
              <h3 class="text-base font-bold uppercase tracking-wide text-ink">Update Tersedia</h3>
              <p class="mt-2 text-sm text-ink">
                <span class="border border-ink bg-white px-2 py-0.5 text-xs font-mono">v{{ server.agent?.agent_version }}</span>
                <span class="mx-1">→</span>
                <span class="border-2 border-accent bg-accent px-2 py-0.5 text-xs font-mono font-bold text-white">v{{ server.latest_version }}</span>
              </p>
              <p class="mt-3 text-xs text-ink-soft">Copy script PowerShell, jalanin di Windows sebagai Administrator. Update berikutnya bisa langsung dari dashboard.</p>
            </div>
            <button @click="openUpdate" class="shrink-0 border-2 border-ink bg-accent-2 px-6 py-3 text-sm font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white brutal brutal-press">
              Copy Script Install
            </button>
          </div>
        </div>
      </div>

      <!-- METRICS -->
      <div v-else-if="tab === 'metrics'">
        <div v-if="!metrics" class="text-sm text-ink-soft">Memuat metrics...</div>
        <div v-else>
          <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="g in [
              ['CPU', metrics.latest?.cpu_percent ?? 0],
              ['RAM', pct(metrics.latest?.ram_used_mb, metrics.latest?.ram_total_mb)],
              ['Disk', pct(metrics.latest?.disk_used_gb, metrics.latest?.disk_total_gb)],
            ]" :key="g[0]" class="brutal bg-white p-5">
              <div class="flex items-center justify-between">
                <p class="swiss-label">{{ g[0] }}</p>
                <p class="text-xl swiss-display font-mono">{{ g[1] }}%</p>
              </div>
              <div class="mt-3 h-3 w-full overflow-hidden border-2 border-ink bg-paper">
                <div class="h-full" :class="barColor(Number(g[1]))" :style="{ width: Math.min(Number(g[1]), 100) + '%' }"></div>
              </div>
            </div>
          </div>
          <p class="mt-4 text-xs text-ink-soft">{{ metrics.history?.length ?? 0 }} titik data · auto-refresh 10 detik</p>
        </div>
      </div>

      <!-- SERVICES -->
      <div v-else-if="tab === 'services'">
        <div v-if="!services.length" class="text-sm text-ink-soft">Memuat services...</div>
        <div v-else class="brutal-lg bg-white">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b-2 border-ink text-left swiss-label">
                  <th class="px-5 py-3">Service</th>
                  <th class="px-5 py-3">Status</th>
                  <th class="px-5 py-3">Startup</th>
                  <th class="px-5 py-3">Allowed</th>
                  <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-ink/10">
                <tr v-for="svc in services" :key="svc.id" class="hover:bg-paper">
                  <td class="px-5 py-3">
                    <p class="font-bold font-mono text-ink">{{ svc.service_name }}</p>
                    <p class="text-xs text-ink-soft">{{ svc.display_name }}</p>
                  </td>
                  <td class="px-5 py-3">
                    <span class="inline-flex border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="svc.status === 'Running' ? 'bg-ok text-white' : 'bg-neutral text-white'">{{ svc.status }}</span>
                  </td>
                  <td class="px-5 py-3 text-xs">{{ svc.startup_type ?? '—' }}</td>
                  <td class="px-5 py-3">
                    <button @click="toggleAllow(svc)" class="border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="svc.is_allowed ? 'bg-ok text-white' : 'bg-white text-ink'">
                      {{ svc.is_allowed ? 'Ya' : 'Tidak' }}
                    </button>
                  </td>
                  <td class="px-5 py-3 text-right">
                    <div class="inline-flex gap-1">
                      <button @click="runAction(svc, 'restart_service')" :disabled="!svc.is_allowed" class="border-2 border-ink bg-white px-2 py-0.5 text-xs font-bold uppercase hover:bg-accent-2 disabled:opacity-40">Restart</button>
                      <button @click="runAction(svc, svc.status === 'Running' ? 'stop_service' : 'start_service')" :disabled="!svc.is_allowed" class="border-2 border-ink bg-white px-2 py-0.5 text-xs font-bold uppercase hover:bg-accent-2 disabled:opacity-40">
                        {{ svc.status === 'Running' ? 'Stop' : 'Start' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- COMMANDS -->
      <div v-else-if="tab === 'commands'">
        <div v-if="!commands.length" class="text-sm text-ink-soft">Belum ada command.</div>
        <div v-else class="brutal-lg bg-white overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b-2 border-ink text-left swiss-label">
                <th class="px-5 py-3">Aksi</th>
                <th class="px-5 py-3">Service</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Oleh</th>
                <th class="px-5 py-3">Waktu</th>
                <th class="px-5 py-3">Exit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr v-for="cmd in commands" :key="cmd.id" class="hover:bg-paper">
                <td class="px-5 py-3 font-mono text-xs">{{ cmd.action }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ cmd.service_name || '—' }}</td>
                <td class="px-5 py-3">
                  <span class="inline-flex border border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="{
                    'bg-ok text-white': cmd.status === 'success',
                    'bg-danger text-white': ['failed','timeout','rejected'].includes(cmd.status),
                    'bg-accent-2 text-ink': cmd.status === 'pending',
                  }">{{ cmd.status }}</span>
                </td>
                <td class="px-5 py-3 text-ink-soft">{{ cmd.user?.name ?? 'system' }}</td>
                <td class="px-5 py-3 text-xs text-ink-soft">{{ cmd.requested_at ? timeAgo(cmd.requested_at) : '—' }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ cmd.exit_code ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Update script modal -->
    <Teleport to="body">
      <div v-if="showUpdate" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-3xl border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">Script Install Agent v{{ server.latest_version }}</h3>
            <button @click="showUpdate = false" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <div class="p-5 max-h-[70vh] overflow-y-auto">
            <div class="mb-4 border-l-4 border-accent bg-accent/10 pl-3 py-2 text-sm">
              <strong>Cara pakai:</strong> Copy script → buka PowerShell as Administrator → paste → Enter. Tunggu ~60 detik.
            </div>
            <div v-if="scriptLoading" class="text-sm text-ink-soft">Generating script...</div>
            <div v-else class="relative">
              <pre class="border-2 border-ink bg-ink p-4 text-xs font-mono text-paper overflow-x-auto" style="max-height: 400px;">{{ updateScript }}</pre>
              <button @click="copyScript" class="absolute top-2 right-2 border-2 border-ink bg-accent-2 px-3 py-1.5 text-xs font-bold uppercase text-ink hover:bg-accent hover:text-white brutal-sm">{{ copyLabel }}</button>
            </div>
          </div>
          <div class="border-t-2 border-ink bg-paper px-5 py-3 flex justify-end">
            <button @click="showUpdate = false" class="border-2 border-ink bg-white px-4 py-2 text-sm font-bold uppercase brutal-sm brutal-press">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
