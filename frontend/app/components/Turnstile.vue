<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'

const props = defineProps<{
  siteKey: string
  theme?: 'light' | 'dark' | 'auto'
  size?: 'normal' | 'compact'
}>()

const emit = defineEmits<{
  verified: [token: string]
  error: [error: string]
  expired: []
}>()

const widgetId = ref<string | null>(null)
const containerRef = ref<HTMLDivElement>()

const loadTurnstile = () => {
  if (typeof window === 'undefined') return
  
  if ((window as any).turnstile) {
    renderWidget()
    return
  }

  const script = document.createElement('script')
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'
  script.async = true
  script.defer = true
  script.onload = () => renderWidget()
  document.head.appendChild(script)
}

const renderWidget = () => {
  if (!containerRef.value || !(window as any).turnstile) return

  widgetId.value = (window as any).turnstile.render(containerRef.value, {
    sitekey: props.siteKey,
    theme: props.theme || 'auto',
    size: props.size || 'normal',
    callback: (token: string) => emit('verified', token),
    'error-callback': () => emit('error', 'Verification failed'),
    'expired-callback': () => emit('expired'),
  })
}

const reset = () => {
  if (widgetId.value && (window as any).turnstile) {
    (window as any).turnstile.reset(widgetId.value)
  }
}

defineExpose({ reset })

onMounted(() => {
  loadTurnstile()
})
</script>

<template>
  <div ref="containerRef"></div>
</template>
