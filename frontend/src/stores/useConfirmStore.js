import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useConfirmStore = defineStore('confirm', () => {
  const isOpen = ref(false)
  const title = ref('Konfirmasi Tindakan')
  const message = ref('')
  const type = ref('warning') // 'warning' | 'danger' | 'info' | 'success'
  const confirmText = ref('Ya, Lanjutkan')
  const cancelText = ref('Batal')
  const isLoading = ref(false)
  
  let resolvePromise = null

  function showConfirm(options = {}) {
    title.value = options.title || 'Konfirmasi Tindakan'
    message.value = options.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?'
    type.value = options.type || 'warning'
    confirmText.value = options.confirmText || 'Ya, Lanjutkan'
    cancelText.value = options.cancelText || 'Batal'
    isLoading.value = false
    isOpen.value = true

    return new Promise((resolve) => {
      resolvePromise = resolve
    })
  }

  function handleConfirm() {
    isOpen.value = false
    if (resolvePromise) {
      resolvePromise(true)
      resolvePromise = null
    }
  }

  function handleCancel() {
    isOpen.value = false
    if (resolvePromise) {
      resolvePromise(false)
      resolvePromise = null
    }
  }

  return {
    isOpen,
    title,
    message,
    type,
    confirmText,
    cancelText,
    isLoading,
    showConfirm,
    handleConfirm,
    handleCancel,
  }
})
