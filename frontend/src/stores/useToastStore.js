import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])
  let counter = 0

  function addToast({ type = 'info', message = '', title = '', timeout = 3500 }) {
    const id = ++counter
    
    // Default Indonesian titles if not provided
    let defaultTitle = ''
    if (!title) {
      if (type === 'success') defaultTitle = 'Berhasil'
      else if (type === 'error') defaultTitle = 'Gagal / Kesalahan'
      else if (type === 'warning') defaultTitle = 'Perhatian'
      else defaultTitle = 'Informasi'
    }

    const toastItem = {
      id,
      type, // 'success' | 'error' | 'warning' | 'info'
      title: title || defaultTitle,
      message,
      timeout,
      timer: null,
    }

    if (timeout > 0) {
      toastItem.timer = setTimeout(() => {
        removeToast(id)
      }, timeout)
    }

    toasts.value.push(toastItem)
    return id
  }

  function removeToast(id) {
    const index = toasts.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      if (toasts.value[index].timer) {
        clearTimeout(toasts.value[index].timer)
      }
      toasts.value.splice(index, 1)
    }
  }

  function success(message, options = {}) {
    return addToast({ type: 'success', message, ...options })
  }

  function error(message, options = {}) {
    return addToast({ type: 'error', message, ...options })
  }

  function warning(message, options = {}) {
    return addToast({ type: 'warning', message, ...options })
  }

  function info(message, options = {}) {
    return addToast({ type: 'info', message, ...options })
  }

  function clearAll() {
    toasts.value.forEach((t) => {
      if (t.timer) clearTimeout(t.timer)
    })
    toasts.value = []
  }

  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    warning,
    info,
    clearAll,
  }
})
