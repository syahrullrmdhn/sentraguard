<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const { login } = useAuth()
const email = ref('')
const password = ref('')
const remember = ref(false)
const error = ref('')
const loading = ref(false)

const submit = async () => {
  error.value = ''
  loading.value = true
  try {
    await login(email.value, password.value, remember.value)
    await navigateTo('/')
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau password salah.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="brutal-lg bg-white p-8">
      <div class="mb-6 flex items-center gap-3">
        <div class="flex h-16 w-16 items-center justify-center bg-accent-2 text-ink brutal-lg text-2xl font-bold">S</div>
        <div>
          <h1 class="text-xl swiss-display text-ink">SentraGuard</h1>
          <p class="text-xs uppercase tracking-widest text-ink-soft">AgentOps Console</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div v-if="error" class="border-2 border-danger bg-danger/10 px-4 py-3 text-sm font-medium text-ink">
          {{ error }}
        </div>

        <div>
          <label class="swiss-label">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autofocus
            class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm text-ink outline-none focus:bg-white"
            placeholder="email@contoh.com"
          />
        </div>

        <div>
          <label class="swiss-label">Password</label>
          <input
            v-model="password"
            type="password"
            required
            class="mt-1.5 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm text-ink outline-none focus:bg-white"
            placeholder="••••••••"
          />
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
          <input v-model="remember" type="checkbox" class="h-4 w-4 border-2 border-ink" />
          Ingat saya
        </label>

        <button
          type="submit"
          :disabled="loading"
          class="w-full border-2 border-ink bg-accent-2 px-4 py-3 text-sm font-bold uppercase tracking-wide text-ink transition hover:bg-accent hover:text-white disabled:opacity-50 brutal brutal-press"
        >
          {{ loading ? 'Masuk...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </div>
</template>
