<script setup>
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { formatDateTime } from '@/utils/formatDate'

const router = useRouter()
const notificationStore = useNotificationStore()

const activeTab = ref('all') // 'all' | 'unread' | 'transaction' | 'campaign' | 'system'

const tabs = computed(() => [
  { id: 'all', label: 'Semua', count: notificationStore.notifications.length, icon: 'pi pi-list' },
  { id: 'unread', label: 'Belum Dibaca', count: notificationStore.unreadCount, icon: 'pi pi-envelope' },
  { id: 'transaction', label: 'Transaksi & Saldo', count: notificationStore.notifications.filter(n => n.type === 'transaction').length, icon: 'pi pi-wallet' },
  { id: 'campaign', label: 'Kampanye & Update', count: notificationStore.notifications.filter(n => n.type === 'campaign').length, icon: 'pi pi-bolt' },
  { id: 'system', label: 'Sistem', count: notificationStore.notifications.filter(n => n.type === 'system').length, icon: 'pi pi-info-circle' },
])

const filteredNotifications = computed(() => {
  if (activeTab.value === 'unread') {
    return notificationStore.notifications.filter((n) => !n.is_read)
  }
  if (activeTab.value !== 'all') {
    return notificationStore.notifications.filter((n) => n.type === activeTab.value)
  }
  return notificationStore.notifications
})

function getNotificationStyle(type) {
  switch (type) {
    case 'transaction':
      return {
        bg: 'bg-emerald-50 text-emerald-600 border-emerald-200',
        icon: 'pi pi-wallet',
        badge: 'Transaksi',
      }
    case 'campaign':
      return {
        bg: 'bg-blue-50 text-blue-600 border-blue-200',
        icon: 'pi pi-bolt',
        badge: 'Kampanye',
      }
    case 'security':
      return {
        bg: 'bg-rose-50 text-rose-600 border-rose-200',
        icon: 'pi pi-shield',
        badge: 'Keamanan',
      }
    case 'system':
    default:
      return {
        bg: 'bg-purple-50 text-purple-600 border-purple-200',
        icon: 'pi pi-bell',
        badge: 'Info Sistem',
      }
  }
}

function handleNotificationClick(notif) {
  notificationStore.markAsRead(notif.id)
  if (notif.link) {
    router.push(notif.link)
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
          <RouterLink to="/dashboard" class="hover:text-blue-600 font-medium">Dashboard</RouterLink>
          <i class="pi pi-chevron-right text-[10px]"></i>
          <span class="font-bold text-slate-800">Pemberitahuan</span>
        </div>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Pusat Notifikasi
          </h1>
          <span
            v-if="notificationStore.unreadCount > 0"
            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-600 text-white shadow-sm"
          >
            {{ notificationStore.unreadCount }} Baru
          </span>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Pantau pemberitahuan mutasi saldo, dukungan donatur, dan update berkala proyek Anda.
        </p>
      </div>

      <!-- Bulk Actions -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-if="notificationStore.unreadCount > 0"
          @click="notificationStore.markAllAsRead()"
          class="px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs transition flex items-center gap-1.5 shadow-sm"
        >
          <i class="pi pi-check-circle text-xs"></i>
          <span>Tandai Semua Dibaca</span>
        </button>

        <button
          v-if="notificationStore.notifications.length > 0"
          @click="notificationStore.clearAll()"
          class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 font-bold text-xs transition flex items-center gap-1.5"
        >
          <i class="pi pi-trash text-xs"></i>
          <span>Bersihkan</span>
        </button>
      </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-200 scrollbar-none">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="[
          'px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-2 flex-shrink-0',
          activeTab === tab.id
            ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50 hover:text-slate-900',
        ]"
      >
        <i :class="[tab.icon, 'text-xs']"></i>
        <span>{{ tab.label }}</span>
        <span
          :class="[
            'px-2 py-0.5 rounded-full text-[10px] font-bold',
            activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600',
          ]"
        >
          {{ tab.count }}
        </span>
      </button>
    </div>

    <!-- Notification List Card -->
    <div class="space-y-3">
      <!-- Empty state -->
      <div
        v-if="filteredNotifications.length === 0"
        class="p-12 text-center bg-white rounded-3xl border border-slate-200/80 shadow-sm text-slate-400 text-xs space-y-2"
      >
        <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
          <i class="pi pi-bell-slash text-2xl"></i>
        </div>
        <p class="text-sm font-bold text-slate-700">Tidak ada notifikasi</p>
        <p class="text-xs text-slate-400">
          {{ activeTab === 'unread' ? 'Semua notifikasi telah Anda baca.' : 'Belum ada notifikasi pada kategori ini.' }}
        </p>
      </div>

      <!-- Notification Cards -->
      <div
        v-for="notif in filteredNotifications"
        :key="notif.id"
        :class="[
          'p-5 rounded-3xl border transition-all duration-200 shadow-sm relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4',
          notif.is_read
            ? 'bg-white border-slate-200/80 hover:border-slate-300'
            : 'bg-blue-50/40 border-blue-200/90 ring-1 ring-blue-500/10',
        ]"
      >
        <!-- Left indicator bar if unread -->
        <div
          v-if="!notif.is_read"
          class="absolute left-0 top-0 bottom-0 w-1.5 bg-blue-600"
        ></div>

        <!-- Notification Content -->
        <div class="flex items-start gap-4 min-w-0">
          <div
            :class="[
              'w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 border shadow-sm',
              getNotificationStyle(notif.type).bg,
            ]"
          >
            <i :class="[getNotificationStyle(notif.type).icon, 'text-base']"></i>
          </div>

          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span
                :class="[
                  'text-[10px] font-bold px-2 py-0.5 rounded-md border',
                  getNotificationStyle(notif.type).bg,
                ]"
              >
                {{ getNotificationStyle(notif.type).badge }}
              </span>
              <span class="text-[11px] text-slate-400">
                {{ formatDateTime(notif.created_at) }}
              </span>
              <span
                v-if="!notif.is_read"
                class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-blue-600 text-white"
              >
                Baru
              </span>
            </div>

            <h4 class="text-sm font-bold text-slate-900 leading-snug">
              {{ notif.title }}
            </h4>

            <p class="text-xs text-slate-600 leading-relaxed">
              {{ notif.message }}
            </p>
          </div>
        </div>

        <!-- Right Action Buttons -->
        <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-center pl-15 sm:pl-0">
          <button
            v-if="notif.link"
            @click="handleNotificationClick(notif)"
            class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition shadow-sm flex items-center gap-1.5"
          >
            <span>Buka</span>
            <i class="pi pi-arrow-right text-[10px]"></i>
          </button>

          <button
            v-if="!notif.is_read"
            @click="notificationStore.markAsRead(notif.id)"
            class="p-2 rounded-xl bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 text-xs transition"
            title="Tandai Sudah Dibaca"
          >
            <i class="pi pi-check text-xs"></i>
          </button>

          <button
            @click="notificationStore.deleteNotification(notif.id)"
            class="p-2 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 text-xs transition"
            title="Hapus Notifikasi"
          >
            <i class="pi pi-trash text-xs"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
