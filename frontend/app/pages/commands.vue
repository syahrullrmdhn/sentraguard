<script setup lang="ts">
const api = useApi()
const status = ref('')
const search = ref('')

const { data, pending, refresh } = await useAsyncData('commands', () =>
  api.get<any>('/api/commands', { status: status.value, search: search.value })
)
watch([status, search], () => refresh())

const counts = computed(() => data.value?.counts ?? {})
function timeAgo(d: string) {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return `${diff}d lalu`
  if (diff < 3600) return `${Math.floor(diff / 60)}m lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)}j lalu`
  return `${Math.floor(diff / 86400)}h lalu`
}
</script>

<template>
  <div>
    <h1 class="text-2xl swiss-display text-ink">Command Queue</h1>

    <div class="mt-5 flex flex-wrap gap-2">
      <span v-for="(c, st) in counts" :key="st" class="inline-flex items-center gap-1.5 border-2 border-ink bg-white px-3 py-1 text-xs font-bold uppercase">
        {{ st }}: {{ c }}
      </span>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <input v-model="search" placeholder="Cari service / server..." class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none w-64" />
      <select v-model="status" class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none">
        <option value="">Semua Status</option>
        <option v-for="s in ['pending','picked','running','success','failed','timeout','rejected','cancelled']" :key="s" :value="s">{{ s }}</option>
      </select>
    </div>

    <div v-if="pending" class="mt-6 text-sm text-ink-soft">Memuat...</div>
    <div v-else class="mt-5 brutal-lg bg-white overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b-2 border-ink text-left swiss-label">
            <th class="px-5 py-3">Server</th><th class="px-5 py-3">Aksi</th><th class="px-5 py-3">Service</th>
            <th class="px-5 py-3">Status</th><th class="px-5 py-3">Oleh</th><th class="px-5 py-3">Waktu</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/10">
          <tr v-for="cmd in data?.commands?.data ?? []" :key="cmd.id" class="hover:bg-paper">
            <td class="px-5 py-3 font-bold">{{ cmd.server?.name ?? '—' }}</td>
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
          </tr>
          <tr v-if="!(data?.commands?.data ?? []).length"><td colspan="6" class="px-5 py-8 text-center text-ink-soft">Belum ada command.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
