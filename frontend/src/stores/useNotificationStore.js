import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
  const initialNotifications = [
    {
      id: 1,
      type: 'system',
      title: 'Selamat Datang di CoFund Platform',
      message: 'Akun Anda telah berhasil dibuat. Mulai jelajahi berbagai kampanye inovatif atau upgrade ke Creator untuk menggalang dana.',
      link: '/campaigns',
      is_read: false,
      created_at: new Date(Date.now() - 1000 * 60 * 30).toISOString(), // 30 mins ago
    },
    {
      id: 2,
      type: 'transaction',
      title: 'Virtual Escrow & Saldo Dompet Aktif',
      message: 'Sistem dompet virtual dan jaminan escrow 100% refund Anda telah siap digunakan untuk berdonasi dengan aman.',
      link: '/wallet',
      is_read: false,
      created_at: new Date(Date.now() - 1000 * 60 * 60 * 3).toISOString(), // 3 hours ago
    },
    {
      id: 3,
      type: 'campaign',
      title: 'Kabar Proyek & Milestone Baru',
      message: 'Inisiator proyek telah mempublikasikan update terbaru mengenai progres kampanye yang sedang berjalan.',
      link: '/campaigns',
      is_read: true,
      created_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(), // 1 day ago
    },
  ]

  const stored = (() => {
    try {
      const data = localStorage.getItem('cofund_notifications')
      return data ? JSON.parse(data) : initialNotifications
    } catch {
      return initialNotifications
    }
  })()

  const notifications = ref(stored)

  const unreadCount = computed(() => {
    return notifications.value.filter((n) => !n.is_read).length
  })

  function saveToStorage() {
    localStorage.setItem('cofund_notifications', JSON.stringify(notifications.value))
  }

  function addNotification(item) {
    const newNotif = {
      id: Date.now(),
      type: item.type || 'system',
      title: item.title || 'Notifikasi Baru',
      message: item.message || '',
      link: item.link || '',
      is_read: false,
      created_at: new Date().toISOString(),
      ...item,
    }
    notifications.value.unshift(newNotif)
    saveToStorage()
  }

  function markAsRead(id) {
    const item = notifications.value.find((n) => n.id === id)
    if (item) {
      item.is_read = true
      saveToStorage()
    }
  }

  function markAllAsRead() {
    notifications.value.forEach((n) => {
      n.is_read = true
    })
    saveToStorage()
  }

  function deleteNotification(id) {
    notifications.value = notifications.value.filter((n) => n.id !== id)
    saveToStorage()
  }

  function clearAll() {
    notifications.value = []
    saveToStorage()
  }

  return {
    notifications,
    unreadCount,
    addNotification,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    clearAll,
  }
})
