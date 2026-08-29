<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { useAuth } from '@/composables/useAuth'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const { handleLogout } = useAuth()

const isSidebarOpen = ref(true)
const isProfileMenuOpen = ref(false)

function toggleSidebar() {
  isSidebarOpen.value = !isSidebarOpen.value
}

function toggleProfileMenu() {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
}

function closeProfileMenu() {
  isProfileMenuOpen.value = false
}
</script>

<template>
  <div class="min-h-screen flex bg-slate-50 text-slate-800 antialiased font-sans">
    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 bg-white border-r border-slate-200 shadow-sm transition-[width] duration-300 ease-in-out flex flex-col justify-between overflow-visible',
        isSidebarOpen ? 'w-64' : 'w-20',
      ]"
    >
      <div class="overflow-hidden">
        <!-- Brand Header (Fixed icon on left, fading text on right) -->
        <div class="h-20 flex items-center px-5 border-b border-slate-100">
          <RouterLink to="/admin/dashboard" class="flex items-center">
            <!-- Fixed Icon -->
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-sky-500 flex-shrink-0 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-blue-500/20">
              <i class="pi pi-bolt"></i>
            </div>
            <!-- Collapsible Text with Smooth Fade Transition -->
            <div
              :class="[
                'flex flex-col transition-all duration-300 ease-in-out overflow-hidden whitespace-nowrap',
                isSidebarOpen ? 'opacity-100 max-w-[180px] ml-3' : 'opacity-0 max-w-0 ml-0 pointer-events-none',
              ]"
            >
              <span class="text-lg font-black tracking-tight text-slate-900 leading-tight">
                Co<span class="text-blue-600">Admin</span>
              </span>
              <span class="text-[9px] uppercase tracking-widest text-slate-400 font-bold -mt-0.5">
                Platform Control
              </span>
            </div>
          </RouterLink>
        </div>

        <!-- Navigation Items (Fixed icons on left, fading text on right) -->
        <nav class="px-3 py-2 space-y-1.5 mt-2">
          <!-- Statistik & Ringkasan -->
          <RouterLink
            to="/admin/dashboard"
            class="h-12 flex items-center px-2 rounded-2xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
            active-class="!bg-blue-600 !text-white shadow-md shadow-blue-600/20"
            title="Statistik & Ringkasan"
          >
            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center">
              <i class="pi pi-chart-bar text-lg"></i>
            </div>
            <span
              :class="[
                'transition-all duration-300 ease-in-out overflow-hidden whitespace-nowrap',
                isSidebarOpen ? 'opacity-100 max-w-[160px] ml-2' : 'opacity-0 max-w-0 ml-0 pointer-events-none',
              ]"
            >
              Statistik & Ringkasan
            </span>
          </RouterLink>

          <!-- Manajemen Kampanye -->
          <RouterLink
            to="/admin/campaigns"
            class="h-12 flex items-center px-2 rounded-2xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
            active-class="!bg-blue-600 !text-white shadow-md shadow-blue-600/20"
            title="Manajemen Kampanye"
          >
            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center">
              <i class="pi pi-check-square text-lg"></i>
            </div>
            <span
              :class="[
                'transition-all duration-300 ease-in-out overflow-hidden whitespace-nowrap',
                isSidebarOpen ? 'opacity-100 max-w-[160px] ml-2' : 'opacity-0 max-w-0 ml-0 pointer-events-none',
              ]"
            >
              Manajemen Kampanye
            </span>
          </RouterLink>

          <!-- Manajemen User -->
          <RouterLink
            to="/admin/users"
            class="h-12 flex items-center px-2 rounded-2xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
            active-class="!bg-blue-600 !text-white shadow-md shadow-blue-600/20"
            title="Manajemen User"
          >
            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center">
              <i class="pi pi-users text-lg"></i>
            </div>
            <span
              :class="[
                'transition-all duration-300 ease-in-out overflow-hidden whitespace-nowrap',
                isSidebarOpen ? 'opacity-100 max-w-[160px] ml-2' : 'opacity-0 max-w-0 ml-0 pointer-events-none',
              ]"
            >
              Manajemen User
            </span>
          </RouterLink>
        </nav>
      </div>

      <!-- Bottom User Profile Area with Dropdown Menu -->
      <div class="p-3 border-t border-slate-100 bg-slate-50/50 relative" v-click-outside="closeProfileMenu">
        <!-- Profile Trigger Button -->
        <button
          type="button"
          @click="toggleProfileMenu"
          class="w-full h-12 flex items-center px-2 rounded-2xl hover:bg-slate-100/90 transition-all focus:outline-none text-left"
          title="Menu Administrator"
        >
          <!-- Fixed Avatar -->
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex-shrink-0 flex items-center justify-center font-bold text-xs shadow-xs">
            {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'A' }}
          </div>

          <!-- Collapsible Name & Role -->
          <div
            :class="[
              'flex-1 transition-all duration-300 ease-in-out overflow-hidden whitespace-nowrap min-w-0',
              isSidebarOpen ? 'opacity-100 max-w-[140px] ml-2' : 'opacity-0 max-w-0 ml-0 pointer-events-none',
            ]"
          >
            <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ authStore.user?.name }}</p>
            <p class="text-[10px] text-blue-600 font-bold uppercase leading-none mt-0.5">Administrator</p>
          </div>

          <!-- Chevron when expanded -->
          <i
            v-if="isSidebarOpen"
            class="pi pi-chevron-up text-[10px] text-slate-400 ml-1 transition-transform duration-200"
            :class="isProfileMenuOpen ? 'rotate-180 text-blue-600' : ''"
          ></i>
        </button>

        <!-- Profile Dropdown Popup (Logout & Notifications) -->
        <div
          v-if="isProfileMenuOpen"
          :class="[
            'absolute bottom-full mb-2 z-50 rounded-2xl bg-white border border-slate-200 shadow-xl p-2 animate-in fade-in zoom-in-95 duration-150',
            isSidebarOpen ? 'left-3 right-3' : 'left-3 w-56',
          ]"
        >
          <!-- Header Info -->
          <div class="px-3 py-2.5 border-b border-slate-100 mb-1">
            <p class="text-xs font-bold text-slate-900 truncate">{{ authStore.user?.name }}</p>
            <p class="text-[11px] text-slate-400 truncate">{{ authStore.user?.email }}</p>
            <span class="inline-block text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 mt-1.5 border border-blue-100">
              Admin Platform
            </span>
          </div>

          <div class="space-y-0.5">
            <!-- Pusat Notifikasi -->
            <RouterLink
              to="/admin/notifications"
              @click="closeProfileMenu"
              class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold text-slate-700 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition"
            >
              <div class="flex items-center gap-2.5">
                <i class="pi pi-bell text-slate-400 group-hover:text-blue-600"></i>
                <span>Pusat Notifikasi</span>
              </div>
              <span
                v-if="notificationStore.unreadCount > 0"
                class="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-black"
              >
                {{ notificationStore.unreadCount }}
              </span>
            </RouterLink>

            <!-- Logout Button -->
            <button
              type="button"
              @click="
                () => {
                  closeProfileMenu()
                  handleLogout()
                }
              "
              class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl transition text-left"
            >
              <i class="pi pi-sign-out text-rose-500"></i>
              <span>Keluar (Logout)</span>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div
      :class="[
        'flex-1 flex flex-col transition-[margin] duration-300 ease-in-out min-h-screen bg-slate-50',
        isSidebarOpen ? 'ml-64' : 'ml-20',
      ]"
    >
      <!-- Top Header Bar (No Administrator Mode Badge) -->
      <header class="h-20 bg-white/90 backdrop-blur-md border-b border-slate-200/80 px-6 flex items-center justify-between sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-4">
          <button
            @click="toggleSidebar"
            class="text-slate-500 hover:text-slate-900 p-2.5 rounded-xl hover:bg-slate-100 transition focus:outline-none border border-slate-200/70"
            title="Toggle Sidebar"
          >
            <i :class="isSidebarOpen ? 'pi pi-align-left' : 'pi pi-align-right'" class="text-sm"></i>
          </button>
          <div>
            <h2 class="text-sm font-extrabold text-slate-900 leading-tight">
              CoFund Master Control Center
            </h2>
            <p class="text-[11px] text-slate-500 hidden sm:block">Panel pengawasan operasional dan persetujuan kampanye</p>
          </div>
        </div>
      </header>

      <main class="p-6 lg:p-8 flex-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
