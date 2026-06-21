<script setup lang="ts">
const route = useRoute()
const api = useApi()
const id = route.params.id as string

const tab = ref<'overview' | 'metrics' | 'services' | 'firewall' | 'commands'>('overview')

// Server detail
const { data: detail, pending, refresh } = await useAsyncData(`server-${id}`, () =>
  api.get<any>(`/api/servers/${id}`)
)
const server = computed(() => detail.value?.server)

// Lazy-loaded per tab
const metrics = ref<any>(null)
const servicesData = ref<any>(null)
const firewallRules = ref<any[]>([])
const commands = ref<any[]>([])

// Metrics: range selector
const metricsRange = ref('1h')
const rangeOptions = [
  { value: '30m', label: '30 Menit' },
  { value: '1h', label: '1 Jam' },
  { value: '3h', label: '3 Jam' },
  { value: '24h', label: '24 Jam' },
]

const loadMetrics = async () => {
  metrics.value = await api.get<any>(`/api/servers/${id}/metrics?range=${metricsRange.value}`)
}

watch(metricsRange, () => loadMetrics())

// Services: pagination + search
const servicesSearch = ref('')
const servicesPage = ref(1)

const loadServices = async () => {
  const params = new URLSearchParams()
  if (servicesSearch.value) params.set('search', servicesSearch.value)
  params.set('page', String(servicesPage.value))
  servicesData.value = await api.get<any>(`/api/servers/${id}/services?${params}`)
}

watch([servicesSearch, servicesPage], () => loadServices())

const services = computed(() => servicesData.value?.data || [])
const servicesMeta = computed(() => ({
  current_page: servicesData.value?.current_page || 1,
  last_page: servicesData.value?.last_page || 1,
  total: servicesData.value?.total || 0,
}))

const loadCommands = async () => {
  const r = await api.get<any>(`/api/servers/${id}/commands`)
  commands.value = r.commands
}

const loadFirewall = async () => {
  const r = await api.get<any>(`/api/servers/${id}/firewall`)
  firewallRules.value = r.rules
}

watch(tab, (t) => {
  if (t === 'metrics' && !metrics.value) loadMetrics()
  if (t === 'services' && !servicesData.value) loadServices()
  if (t === 'firewall' && !firewallRules.value.length) loadFirewall()
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

// Chart data
const chartData = computed(() => {
  if (!metrics.value?.history) return null
  
  const history = metrics.value.history || []
  const labels = history.map((m: any) => new Date(m.recorded_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }))
  const cpuData = history.map((m: any) => m.cpu_percent ?? 0)
  const ramData = history.map((m: any) => {
    const total = m.ram_total_mb || 1
    return Math.round((m.ram_used_mb / total) * 100)
  })
  const diskData = history.map((m: any) => {
    const total = m.disk_total_gb || 1
    return Math.round((m.disk_used_gb / total) * 100)
  })
  const netSentData = history.map((m: any) => m.network_sent_mbps ?? 0)
  const netRecvData = history.map((m: any) => m.network_recv_mbps ?? 0)
  
  return {
    labels,
    datasets: [
      {
        label: 'CPU %',
        data: cpuData,
        borderColor: '#2d3dff',
        backgroundColor: 'rgba(45, 61, 255, 0.1)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.3,
        fill: true,
        yAxisID: 'y',
      },
      {
        label: 'RAM %',
        data: ramData,
        borderColor: '#e8ff47',
        backgroundColor: 'rgba(232, 255, 71, 0.1)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.3,
        fill: true,
        yAxisID: 'y',
      },
      {
        label: 'Disk %',
        data: diskData,
        borderColor: '#ff6b6b',
        backgroundColor: 'rgba(255, 107, 107, 0.1)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.3,
        fill: true,
        yAxisID: 'y',
      },
      {
        label: 'Network ↑ (Mbps)',
        data: netSentData,
        borderColor: '#00d4aa',
        backgroundColor: 'rgba(0, 212, 170, 0.1)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.3,
        fill: false,
        yAxisID: 'y1',
      },
      {
        label: 'Network ↓ (Mbps)',
        data: netRecvData,
        borderColor: '#ff9500',
        backgroundColor: 'rgba(255, 149, 0, 0.1)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.3,
        fill: false,
        yAxisID: 'y1',
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index' as const, intersect: false },
  plugins: {
    legend: { 
      display: true, 
      position: 'top' as const, 
      labels: { font: { family: 'Space Grotesk', weight: '600' }, boxWidth: 12 } 
    },
    tooltip: { mode: 'index' as const, intersect: false },
  },
  scales: {
    x: { 
      grid: { display: false },
      ticks: { font: { family: 'Space Mono', size: 10 } },
    },
    y: { 
      type: 'linear' as const,
      position: 'left' as const,
      min: 0, 
      max: 100,
      grid: { color: 'rgba(0,0,0,0.05)' },
      ticks: { 
        font: { family: 'Space Mono', size: 11 }, 
        callback: (v: any) => v + '%' 
      },
      title: {
        display: true,
        text: 'CPU / RAM / Disk (%)',
        font: { family: 'Space Grotesk', size: 11, weight: '600' },
      },
    },
    y1: {
      type: 'linear' as const,
      position: 'right' as const,
      min: 0,
      grid: { display: false },
      ticks: { 
        font: { family: 'Space Mono', size: 11 },
        callback: (v: any) => v + ' Mbps',
      },
      title: {
        display: true,
        text: 'Network (Mbps)',
        font: { family: 'Space Grotesk', size: 11, weight: '600' },
      },
    },
  },
}

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

// ---- Firewall actions ----
const showAddRule = ref(false)
const newRule = ref({ rule_name: '', direction: 'inbound', protocol: 'tcp', port: '', action: 'allow', description: '' })

const toggleFirewallRule = async (rule: any) => {
  await api.patch(`/api/servers/${id}/firewall/${rule.id}/toggle`)
  await loadFirewall()
}

const deleteFirewallRule = async (rule: any) => {
  if (!confirm(`Hapus rule "${rule.rule_name}"?`)) return
  await api.delete(`/api/servers/${id}/firewall/${rule.id}`)
  await loadFirewall()
}

const createFirewallRule = async () => {
  try {
    await api.post(`/api/servers/${id}/firewall`, newRule.value)
    showAddRule.value = false
    newRule.value = { rule_name: '', direction: 'inbound', protocol: 'tcp', port: '', action: 'allow', description: '' }
    await loadFirewall()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal buat rule.')
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
      <button v-for="(label, key) in { overview: 'Overview', metrics: 'Metrics', services: 'Services', firewall: 'Firewall', commands: 'Command History' }" :key="key"
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
          <!-- Range selector -->
          <div class="mb-4 flex items-center gap-3">
            <label class="swiss-label text-sm">Range:</label>
            <select v-model="metricsRange" class="border-2 border-ink bg-white px-3 py-1.5 text-sm font-bold uppercase tracking-wide text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent">
              <option v-for="opt in rangeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

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
          
          <!-- Chart history -->
          <div v-if="chartData" class="mt-6 brutal-lg bg-white p-6">
            <h3 class="swiss-label mb-4">History Metrics</h3>
            <div style="height: 300px;">
              <LineChart :data="chartData" :options="chartOptions" />
            </div>
          </div>
          
          <p class="mt-4 text-xs text-ink-soft">{{ metrics.history?.length ?? 0 }} titik data · auto-refresh 10 detik</p>
        </div>
      </div>

      <!-- SERVICES -->
      <div v-else-if="tab === 'services'">
        <!-- Search bar -->
        <div class="mb-4 flex items-center gap-3">
          <input 
            v-model="servicesSearch" 
            type="text" 
            placeholder="Cari service (nama atau display name)..." 
            class="flex-1 border-2 border-ink bg-white px-4 py-2 text-sm font-medium text-ink placeholder-ink-soft brutal-sm focus:outline-none focus:ring-2 focus:ring-accent"
          />
        </div>

        <div v-if="!servicesData" class="text-sm text-ink-soft">Memuat services...</div>
        <div v-else-if="!services.length" class="text-sm text-ink-soft">Tidak ada service ditemukan.</div>
        <div v-else>
          <div class="brutal-lg bg-white">
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
                      <button @click="toggleAllow(svc)" class="border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase transition hover:opacity-80" :class="svc.is_allowed ? 'bg-ok text-white' : 'bg-white text-ink'">
                        {{ svc.is_allowed ? 'Ya' : 'Tidak' }}
                      </button>
                    </td>
                    <td class="px-5 py-3 text-right">
                      <div class="inline-flex gap-1">
                        <button @click="runAction(svc, 'restart_service')" :disabled="!svc.is_allowed" class="border-2 border-ink bg-white px-2 py-0.5 text-xs font-bold uppercase transition hover:bg-accent-2 disabled:opacity-40 disabled:cursor-not-allowed">Restart</button>
                        <button @click="runAction(svc, svc.status === 'Running' ? 'stop_service' : 'start_service')" :disabled="!svc.is_allowed" class="border-2 border-ink bg-white px-2 py-0.5 text-xs font-bold uppercase transition hover:bg-accent-2 disabled:opacity-40 disabled:cursor-not-allowed">
                          {{ svc.status === 'Running' ? 'Stop' : 'Start' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="servicesMeta.last_page > 1" class="mt-4 flex items-center justify-between">
            <p class="text-xs text-ink-soft">{{ servicesMeta.total }} services total</p>
            <div class="flex gap-1">
              <button 
                @click="servicesPage = servicesMeta.current_page - 1" 
                :disabled="servicesMeta.current_page === 1"
                class="border-2 border-ink bg-white px-3 py-1 text-xs font-bold uppercase text-ink transition hover:bg-accent-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                ← Prev
              </button>
              <span class="flex items-center border-2 border-ink bg-paper px-3 py-1 text-xs font-bold text-ink">
                {{ servicesMeta.current_page }} / {{ servicesMeta.last_page }}
              </span>
              <button 
                @click="servicesPage = servicesMeta.current_page + 1" 
                :disabled="servicesMeta.current_page === servicesMeta.last_page"
                class="border-2 border-ink bg-white px-3 py-1 text-xs font-bold uppercase text-ink transition hover:bg-accent-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next →
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- FIREWALL -->
      <div v-else-if="tab === 'firewall'">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-bold uppercase text-ink-soft">Firewall Rules</h3>
          <button @click="showAddRule = true" class="border-2 border-ink bg-accent-2 px-3 py-1.5 text-xs font-bold uppercase text-ink brutal-sm hover:opacity-80">
            + Tambah Rule
          </button>
        </div>

        <div v-if="!firewallRules.length" class="text-sm text-ink-soft">Belum ada firewall rule.</div>
        <div v-else class="brutal-lg bg-white overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b-2 border-ink text-left swiss-label">
                <th class="px-5 py-3">Rule Name</th>
                <th class="px-5 py-3">Direction</th>
                <th class="px-5 py-3">Protocol</th>
                <th class="px-5 py-3">Port</th>
                <th class="px-5 py-3">Action</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr v-for="rule in firewallRules" :key="rule.id" class="hover:bg-paper">
                <td class="px-5 py-3">
                  <p class="font-bold text-ink">{{ rule.rule_name }}</p>
                  <p v-if="rule.description" class="text-xs text-ink-soft">{{ rule.description }}</p>
                </td>
                <td class="px-5 py-3 text-xs uppercase">{{ rule.direction }}</td>
                <td class="px-5 py-3 text-xs uppercase">{{ rule.protocol }}</td>
                <td class="px-5 py-3 text-xs font-mono">{{ rule.port || 'any' }}</td>
                <td class="px-5 py-3">
                  <span class="inline-flex border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="rule.action === 'allow' ? 'bg-ok text-white' : 'bg-danger text-white'">
                    {{ rule.action }}
                  </span>
                </td>
                <td class="px-5 py-3">
                  <button @click="toggleFirewallRule(rule)" class="border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase transition hover:opacity-80" :class="rule.is_enabled ? 'bg-ok text-white' : 'bg-neutral text-white'">
                    {{ rule.is_enabled ? 'Enabled' : 'Disabled' }}
                  </button>
                </td>
                <td class="px-5 py-3 text-right">
                  <button @click="deleteFirewallRule(rule)" class="border-2 border-ink bg-white px-2 py-0.5 text-xs font-bold uppercase text-danger hover:bg-danger hover:text-white transition">
                    Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
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

      <!-- Tambah Rule modal -->
      <div v-if="showAddRule" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-xl border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">Tambah Firewall Rule</h3>
            <button @click="showAddRule = false" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <div class="p-5">
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Rule Name *</label>
                <input v-model="newRule.rule_name" type="text" placeholder="e.g., Allow HTTP" class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Direction *</label>
                  <select v-model="newRule.direction" class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent">
                    <option value="inbound">Inbound</option>
                    <option value="outbound">Outbound</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Protocol *</label>
                  <select v-model="newRule.protocol" class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent">
                    <option value="tcp">TCP</option>
                    <option value="udp">UDP</option>
                    <option value="any">Any</option>
                  </select>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Port</label>
                  <input v-model="newRule.port" type="text" placeholder="e.g., 80, 443, 3000-3010" class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent" />
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Action *</label>
                  <select v-model="newRule.action" class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent">
                    <option value="allow">Allow</option>
                    <option value="block">Block</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Description</label>
                <textarea v-model="newRule.description" rows="2" placeholder="Optional description..." class="w-full border-2 border-ink bg-white px-3 py-2 text-sm text-ink brutal-sm focus:outline-none focus:ring-2 focus:ring-accent"></textarea>
              </div>
            </div>
          </div>
          <div class="border-t-2 border-ink bg-paper px-5 py-3 flex justify-end gap-2">
            <button @click="showAddRule = false" class="border-2 border-ink bg-white px-4 py-2 text-sm font-bold uppercase brutal-sm hover:bg-paper">Batal</button>
            <button @click="createFirewallRule" class="border-2 border-ink bg-accent-2 px-4 py-2 text-sm font-bold uppercase text-ink brutal-sm brutal-press hover:bg-accent hover:text-white">Buat Rule</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
