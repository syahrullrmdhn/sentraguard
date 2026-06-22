import { ref } from 'vue'

/**
 * Brutal custom confirm/alert — pengganti native confirm() / alert() JS.
 * Semua modal pakai desain brutal SentraGuard (backdrop blur, border-2, shadow-brutal).
 */
export const useModal = () => {
  // Confirm state
  const confirmShow = ref(false)
  const confirmMessage = ref('')
  let confirmResolve: ((val: boolean) => void) | null = null

  const openConfirm = (msg: string): Promise<boolean> => {
    return new Promise((resolve) => {
      confirmMessage.value = msg
      confirmShow.value = true
      confirmResolve = resolve
    })
  }

  const onConfirmOK = () => {
    confirmShow.value = false
    confirmResolve?.(true)
    confirmResolve = null
  }

  const onConfirmCancel = () => {
    confirmShow.value = false
    confirmResolve?.(false)
    confirmResolve = null
  }

  // Alert state
  const alertShow = ref(false)
  const alertMessage = ref('')

  const openAlert = (msg: string) => {
    alertMessage.value = msg
    alertShow.value = true
  }

  const onAlertClose = () => {
    alertShow.value = false
  }

  return {
    // Confirm
    confirmShow,
    confirmMessage,
    openConfirm,
    onConfirmOK,
    onConfirmCancel,
    // Alert
    alertShow,
    alertMessage,
    openAlert,
    onAlertClose,
  }
}
