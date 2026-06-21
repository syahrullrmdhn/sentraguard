<script setup lang="ts">
const api = useApi()
const search = ref('')
const roleFilter = ref('')

const { data, pending, refresh } = await useAsyncData('users', () =>
  api.get<any>('/api/users', { search: search.value, role_id: roleFilter.value })
)
const { data: rolesData } = await useAsyncData('roles', () => api.get<any>('/api/roles'))
const roles = computed(() => rolesData.value?.roles ?? [])

watch([search, roleFilter], () => refresh())

const showModal = ref(false)
const editMode = ref(false)
const form = reactive({ id: 0, name: '', email: '', password: '', password_confirmation: '', role_id: '' })
const formError = ref('')
const saving = ref(false)

const openCreate = () => {
  Object.assign(form, { id: 0, name: '', email: '', password: '', password_confirmation: '', role_id: '' })
  editMode.value = false
  formError.value = ''
  showModal.value = true
}
const openEdit = (u: any) => {
  Object.assign(form, { id: u.id, name: u.name, email: u.email, password: '', password_confirmation: '', role_id: u.role_id })
  editMode.value = true
  formError.value = ''
  showModal.value = true
}
const save = async () => {
  saving.value = true
  formError.value = ''
  try {
    if (editMode.value) {
      await api.put(`/api/users/${form.id}`, form)
    } else {
      await api.post('/api/users', form)
    }
    showModal.value = false
    await refresh()
  } catch (e: any) {
    formError.value = e?.data?.message || Object.values(e?.data?.errors ?? {}).flat()[0] || 'Gagal menyimpan.'
  } finally {
    saving.value = false
  }
}
const remove = async (u: any) => {
  if (!confirm(`Hapus user "${u.name}"?`)) return
  try {
    await api.del(`/api/users/${u.id}`)
    await refresh()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menghapus.')
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl swiss-display text-ink">Users</h1>
      <button @click="openCreate" class="border-2 border-ink bg-accent-2 px-4 py-2 text-xs font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white brutal-sm brutal-press">+ Tambah User</button>
    </div>

    <div class="mt-5 flex flex-wrap gap-3">
      <input v-model="search" placeholder="Cari nama / email..." class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none w-64" />
      <select v-model="roleFilter" class="border-2 border-ink bg-white px-3 py-2 text-sm outline-none">
        <option value="">Semua Role</option>
        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.display_name }}</option>
      </select>
    </div>

    <div v-if="pending" class="mt-6 text-sm text-ink-soft">Memuat...</div>
    <div v-else class="mt-5 brutal-lg bg-white overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b-2 border-ink text-left swiss-label">
            <th class="px-5 py-3">Nama</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Role</th><th class="px-5 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/10">
          <tr v-for="u in data?.data ?? []" :key="u.id" class="hover:bg-paper">
            <td class="px-5 py-3 font-bold">{{ u.name }}</td>
            <td class="px-5 py-3 text-ink-soft">{{ u.email }}</td>
            <td class="px-5 py-3"><span class="inline-flex border border-ink bg-accent-2 px-2 py-0.5 text-xs font-bold uppercase">{{ u.role?.display_name ?? '—' }}</span></td>
            <td class="px-5 py-3 text-right">
              <div class="inline-flex gap-1">
                <button @click="openEdit(u)" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase hover:bg-accent-2 brutal-sm">Edit</button>
                <button @click="remove(u)" class="border-2 border-ink bg-white px-2.5 py-1 text-xs font-bold uppercase hover:bg-danger hover:text-white brutal-sm">Hapus</button>
              </div>
            </td>
          </tr>
          <tr v-if="!(data?.data ?? []).length"><td colspan="4" class="px-5 py-8 text-center text-ink-soft">Belum ada user.</td></tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(12px);">
        <div class="animate-slide-up w-full max-w-lg border-2 border-ink bg-white shadow-brutal-lg">
          <div class="flex items-center justify-between border-b-2 border-ink bg-accent-2 px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-ink">{{ editMode ? 'Edit User' : 'Tambah User' }}</h3>
            <button @click="showModal = false" class="text-2xl leading-none text-ink hover:text-danger">&times;</button>
          </div>
          <form @submit.prevent="save" class="p-5 space-y-4">
            <div v-if="formError" class="border-2 border-danger bg-danger/10 px-3 py-2 text-sm">{{ formError }}</div>
            <div><label class="swiss-label">Nama</label><input v-model="form.name" required class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white" /></div>
            <div><label class="swiss-label">Email</label><input v-model="form.email" type="email" required class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white" /></div>
            <div><label class="swiss-label">Password {{ editMode ? '(kosongkan jika tidak diubah)' : '' }}</label><input v-model="form.password" type="password" class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white" /></div>
            <div><label class="swiss-label">Konfirmasi Password</label><input v-model="form.password_confirmation" type="password" class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white" /></div>
            <div><label class="swiss-label">Role</label>
              <select v-model="form.role_id" required class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm outline-none focus:bg-white">
                <option value="">Pilih role</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.display_name }}</option>
              </select>
            </div>
            <button type="submit" :disabled="saving" class="w-full border-2 border-ink bg-accent-2 px-4 py-3 text-sm font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white disabled:opacity-50 brutal brutal-press">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
