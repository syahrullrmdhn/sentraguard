<script setup lang="ts">
const api = useApi()
const search = ref('')
const result = ref('')

const { data, pending, refresh } = await useAsyncData('audit', () =>
  api.get<any>('/api/audit', { search: search.value, result: result.value })
)
watch([search, result], () => refresh())

function fmt(d: string) {
  return new Date(d).toLocaleString('id-ID')
}
</script>

<template>
  <div>
    <h1 class="text-2xl swiss-display text-ink">Audit Logs</h1>

    <div class="mt-5 flex flex-wrap gap-3">
      <input v-model="search" placeholder="Cari action / deskripsi..." class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none w-64" />
      <select v-model="result" class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none">
        <option value="">Semua Hasil</option>
        <option value="success">Success</option>
        <option value="failed">Failed</option>
      </select>
    </div>

    <div v-if="pending" class="mt-6 text-sm text-ink-soft">Memuat...</div>
    <div v-else class="mt-5 brutal-lg bg-white overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b-2 border-ink text-left swiss-label">
            <th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Deskripsi</th>
            <th class="px-5 py-3">Aktor</th><th class="px-5 py-3">Hasil</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/10">
          <tr v-for="log in data?.data ?? []" :key="log.id" class="hover:bg-paper">
            <td class="px-5 py-3 text-xs text-ink-soft whitespace-nowrap">{{ fmt(log.created_at) }}</td>
            <td class="px-5 py-3 font-mono text-xs">{{ log.action }}</td>
            <td class="px-5 py-3">{{ log.description }}</td>
            <td class="px-5 py-3 text-ink-soft">{{ log.user?.name ?? log.actor_identifier ?? 'system' }}</td>
            <td class="px-5 py-3">
              <span class="inline-flex border border-ink px-2 py-0.5 text-xs font-bold uppercase" :class="log.result === 'success' ? 'bg-ok text-white' : log.result === 'failed' ? 'bg-danger text-white' : 'bg-white text-ink'">{{ log.result ?? '—' }}</span>
            </td>
          </tr>
          <tr v-if="!(data?.data ?? []).length"><td colspan="5" class="px-5 py-8 text-center text-ink-soft">Belum ada log.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
