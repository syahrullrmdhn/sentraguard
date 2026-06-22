<script setup lang="ts">
const api = useApi()
const modal = useModal()

const search = ref('')
const environment = ref('')
const showCreate = ref(false)

const { data, pending, refresh } = await useAsyncData('servers', () =>
  api.get<any>('/api/servers', { search: search.value, environment: environment.value })
)

watch([search, environment], () => refresh())

// Create server
const newName = ref('')
const newEnv = ref('production')
const creating = ref(false)
const createError = ref('')
const generatedToken = ref('')
const generatedName = ref('')

const openCreate = () => {
  newName.value = ''
  newEnv.value = 'production'
  createError.value = ''
  generatedToken.value = ''
  generatedName.value = ''
  showCreate.value = true
}

const createServer = async () => {
  creating.value = true
  createError.value = ''
  try {
    const res = await api.post<any>('/api/servers', { name: newName.value, environment: newEnv.value })
    generatedToken.value = res.token
    generatedName.value = res.server.name
    await refresh()
  } catch (e: any) {
    createError.value = e?.data?.message || 'Gagal membuat server.'
  } finally {
    creating.value = false
  }
}

const copyToken = () => navigator.clipboard.writeText(generatedToken.value)

const installCommand = computed(() => {
  if (!generatedToken.value) return ''
  return `iwr -Uri https://sentraguard.mastolongin.web.id/download/agent -OutFile agent.exe; .\\agent.exe install --server https://sentraguard.mastolongin.web.id --token ${generatedToken.value}`
})

const copyInstallCommand = () => navigator.clipboard.writeText(installCommand.value)

const deleteServer = async (id: number, name: string) => {
  const ok = await modal.openConfirm(`Hapus server "${name}"? Tindakan ini permanen.`)
  if (!ok) return
  await api.del(`/api/servers/${id}`)
  await refresh()
}

const statusBadge = (s: any) => {
  const st = s.connection_status
  if (st === 'online') return 'bg-ok text-white'
  if (st === 'revoked') return 'bg-danger text-white'
  return 'bg-neutral text-white'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl swiss-display text-ink">Servers</h1>
      <button @click="openCreate" class="border-2 border-ink bg-accent-2 px-4 py-2 text-xs font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white brutal-sm brutal-press">
        + Tambah Server
      </button>
    </div>

    <!-- Filters -->
    <div class="mt-5 flex flex-wrap gap-3">
      <input v-model="search" placeholder="Cari nama / hostname..." class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none w-64" />
      <select v-model="environment" class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none">
        <option value="">Semua Environment</option>
        <option value="production">Production</option>
        <option value="staging">Staging</option>
        <option value="development">Development</option>
        <option value="testing">Testing</option>
      </select>
    </div>

    <div v-if="pending" class="mt-6 text-sm text-ink-soft">Memuat...</div>

    <!-- Table -->
    <div v-else class="mt-5 brutal-lg bg-white">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b-2 border-ink text-left swiss-label">
              <th class="px-5 py-3">Nama</th>
              <th class="px-5 py-3">Hostname</th>
              <th class="px-5 py-3">Environment</th>
              <th class="px-5 py-3">Agent</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink/10">
            <tr v-for="s in data?.data ?? []" :key="s.id" class="hover:bg-paper">
              <td class="px-5 py-3">
                <NuxtLink :to="`/servers/${s.id}`" class="font-bold text-ink hover:text-accent">{{ s.name }}</NuxtLink>
              </td>
              <td class="px-5 py-3 font-mono text-xs text-ink-soft">{{ s.hostname ?? '—' }}</td>
              <td class="px-5 py-3">{{ s.environment }}</td>
              <td class="px-5 py-3 font-mono text-xs">{{ s.agent?.agent_version ?? '—' }}</td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center gap-1.5 border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="statusBadge(s)">
                  <span class="h-1.5 w-1.5 rounded-full bg-white"></span>{{ s.connection_status }}
                </span>
              </td>
              <td class="px-5 py-3 text-right">
                <button @click="deleteServer(s.id, s.name)" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase text-ink transition hover:bg-danger hover:text-white brutal-sm">
                  Hapus
                </button>
              </td>
            </tr>
            <tr v-if="!(data?.data ?? []).length">
              <td colspan="6" class="px-5 py-8 text-center text-ink-soft">Belum ada server.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-lg border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">Tambah Server</h3>
            <button @click="showCreate = false" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <div class="p-5">
            <!-- Token result -->
            <div v-if="generatedToken">
              <div class="border-2 border-ok bg-ok/10 px-4 py-3 text-sm">
                ✅ Server <strong>{{ generatedName }}</strong> berhasil dibuat!
              </div>
              
              <div class="mt-4">
                <label class="swiss-label">Opsi 1: GUI Installer (Recommended)</label>
                <p class="mt-1.5 text-xs text-ink-soft mb-2">Download installer dengan antarmuka grafis, tinggal paste token.</p>
                <a :href="`${$config.public.apiBase}/download/SentraGuardInstaller.exe`" download class="inline-flex items-center gap-2 border-2 border-ink bg-accent px-3 py-2 text-sm font-bold uppercase text-white brutal-sm brutal-press hover:bg-accent-2 hover:text-ink">
                  <span>⬇</span> Download GUI Installer
                </a>
              </div>

              <div class="mt-4">
                <label class="swiss-label">Opsi 2: PowerShell Command (Advanced)</label>
                <div class="relative mt-2">
                  <pre class="border-2 border-ink bg-ink p-3 pr-20 text-xs font-mono text-paper overflow-x-auto">{{ installCommand }}</pre>
                  <button @click="copyInstallCommand" class="absolute top-2 right-2 border-2 border-ink bg-accent-2 px-2 py-1 text-xs font-bold uppercase brutal-sm brutal-press">Copy</button>
                </div>
                <p class="mt-1.5 text-xs text-ink-soft">Jalankan di PowerShell (Admin) di server target</p>
              </div>

              <div class="mt-4">
                <label class="swiss-label">Registration Token</label>
                <div class="relative mt-2">
                  <pre class="border-2 border-ink bg-paper p-3 pr-20 text-xs font-mono text-ink overflow-x-auto">{{ generatedToken }}</pre>
                  <button @click="copyToken" class="absolute top-2 right-2 border-2 border-ink bg-white px-2 py-1 text-xs font-bold uppercase brutal-sm brutal-press">Copy</button>
                </div>
              </div>

              <button @click="showCreate = false" class="mt-5 w-full border-2 border-ink bg-accent-2 px-4 py-3 text-sm font-bold uppercase brutal-sm brutal-press">Selesai</button>
            </div>
            <!-- Form -->
            <form v-else @submit.prevent="createServer" class="space-y-4">
              <div v-if="createError" class="border-2 border-danger bg-danger/10 px-3 py-2 text-sm">{{ createError }}</div>
              <div>
                <label class="swiss-label">Nama Server</label>
                <input v-model="newName" required class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white" placeholder="contoh: web-prod-01" />
              </div>
              <div>
                <label class="swiss-label">Environment</label>
                <select v-model="newEnv" class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white">
                  <option value="production">Production</option>
                  <option value="staging">Staging</option>
                  <option value="development">Development</option>
                  <option value="testing">Testing</option>
                </select>
              </div>
              <button type="submit" :disabled="creating" class="w-full border-2 border-ink bg-accent-2 px-4 py-3 text-sm font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white disabled:opacity-50 brutal brutal-press">
                {{ creating ? 'Membuat...' : 'Buat & Generate Token' }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Confirm Modal (brutal) -->
    <Teleport to="body">
      <div v-if="modal.confirmShow.value" class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-md border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">Konfirmasi</h3>
            <button @click="modal.onConfirmCancel()" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <div class="p-5">
            <p class="text-sm text-ink mb-6">{{ modal.confirmMessage.value }}</p>
            <div class="flex justify-end gap-2">
              <button @click="modal.onConfirmCancel()" class="border-2 border-ink bg-white px-4 py-2 text-sm font-bold uppercase text-ink hover:bg-paper brutal-sm">Batal</button>
              <button @click="modal.onConfirmOK()" class="border-2 border-ink bg-accent-2 px-4 py-2 text-sm font-bold uppercase text-ink hover:bg-accent hover:text-white brutal-sm brutal-press">Ya, Lanjutkan</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="modal.alertShow.value" class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-md border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">Info</h3>
            <button @click="modal.onAlertClose()" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <div class="p-5">
            <p class="text-sm text-ink mb-6">{{ modal.alertMessage.value }}</p>
            <div class="flex justify-end">
              <button @click="modal.onAlertClose()" class="border-2 border-ink bg-accent-2 px-6 py-2 text-sm font-bold uppercase text-ink hover:bg-accent hover:text-white brutal-sm brutal-press">OK</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
