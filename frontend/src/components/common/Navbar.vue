<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency } from '@/utils/formatCurrency'
import StatusBadge from './StatusBadge.vue'
import UpgradeToCreatorModal from './UpgradeToCreatorModal.vue'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const { handleLogout } = useAuth()
const router = useRouter()
const route = useRoute()

const isUserMenuOpen = ref(false)
const isMobileMenuOpen = ref(false)
const isUpgradeModalOpen = ref(false)
const isScrolled = ref(false)

function toggleUserMenu() {
  isUserMenuOpen.value = !isUserMenuOpen.value
}

function closeUserMenu() {
  isUserMenuOpen.value = false
}

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

function closeMobileMenu() {
  isMobileMenuOpen.value = false
}

function handleScroll() {
  isScrolled.value = window.scrollY > 20
}

watch(isMobileMenuOpen, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

watch(
  () => route.fullPath,
  () => {
    isMobileMenuOpen.value = false
    isUserMenuOpen.value = false
  }
)

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  document.body.style.overflow = ''
})
</script>

<template>
  <header
    :class="[
      'sticky top-0 z-40 w-full transition-all duration-300',
      isScrolled
        ? 'bg-slate-900/95 backdrop-blur-md shadow-lg border-b border-slate-800'
        : 'bg-slate-900 border-b border-slate-800/80',
    ]"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-20">
        <!-- Brand Logo -->
        <div class="flex items-center gap-8">
          <RouterLink to="/" class="flex items-center gap-3 group">
            <div
              class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 via-blue-500 to-sky-400 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-blue-500/20 group-hover:scale-105 transition transform"
            >
              <i class="pi pi-bolt text-lg"></i>
            </div>
            <div class="flex flex-col">
              <span class="text-xl font-black tracking-tight text-white flex items-center gap-1">
                Co<span class="text-sky-400">Fund</span>
              </span>
              <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold -mt-1">
                Crowdfunding FinTech
              </span>
            </div>
          </RouterLink>

          <!-- Desktop Navigation Links -->
          <nav class="hidden md:flex items-center gap-1">
            <RouterLink
              to="/campaigns"
              class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800/60 rounded-xl transition"
              active-class="text-sky-400 bg-slate-800 font-semibold"
            >
              Jelajahi Kampanye
            </RouterLink>
            <RouterLink
              v-if="authStore.isCreator"
              to="/creator/dashboard"
              class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800/60 rounded-xl transition"
              active-class="text-sky-400 bg-slate-800 font-semibold"
            >
              Dashboard Creator
            </RouterLink>
            <RouterLink
              v-else-if="authStore.isAdmin"
              to="/admin/dashboard"
              class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800/60 rounded-xl transition"
              active-class="text-sky-400 bg-slate-800 font-semibold"
            >
              Admin Panel
            </RouterLink>
            <RouterLink
              v-else-if="authStore.isAuthenticated"
              to="/dashboard"
              class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800/60 rounded-xl transition"
              active-class="text-sky-400 bg-slate-800 font-semibold"
            >
              Dashboard Saya
            </RouterLink>
          </nav>
        </div>

        <!-- Right Action Buttons (Desktop) -->
        <div class="hidden md:flex items-center gap-3">
          <!-- CTA Create Campaign -->
          <RouterLink
            v-if="authStore.isCreator"
            to="/creator/campaigns/create"
            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm shadow-blue-500/20"
          >
            <i class="pi pi-plus text-xs"></i>
            <span>Buat Kampanye</span>
          </RouterLink>

          <!-- Guest State -->
          <template v-if="!authStore.isAuthenticated">
            <RouterLink
              to="/login"
              class="px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white transition"
            >
              Masuk
            </RouterLink>
            <RouterLink
              to="/register"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-slate-900 bg-white hover:bg-slate-100 rounded-xl transition shadow-sm"
            >
              <span>Daftar</span>
              <i class="pi pi-arrow-right text-xs"></i>
            </RouterLink>
          </template>

          <!-- Logged In State -->
          <template v-else>
            <!-- Notification Bell Button -->
            <RouterLink
              to="/notifications"
              class="relative p-2.5 rounded-xl bg-slate-800/90 border border-slate-700/70 text-slate-300 hover:text-white hover:border-slate-600 transition flex items-center justify-center"
              title="Pusat Notifikasi"
            >
              <i class="pi pi-bell text-sm"></i>
              <span
                v-if="notificationStore.unreadCount > 0"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-blue-600 text-white text-[10px] font-black flex items-center justify-center ring-2 ring-slate-900 animate-pulse"
              >
                {{ notificationStore.unreadCount > 9 ? '9+' : notificationStore.unreadCount }}
              </span>
            </RouterLink>

            <!-- Wallet quick pill -->
            <RouterLink
              to="/wallet"
              class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/90 border border-slate-700/70 text-slate-200 hover:border-slate-600 transition"
            >
              <div class="w-6 h-6 rounded-lg bg-blue-500/20 text-sky-400 flex items-center justify-center">
                <i class="pi pi-wallet text-xs"></i>
              </div>
              <div class="flex flex-col text-left">
                <span class="text-[10px] text-slate-400 leading-none">Saldo</span>
                <span class="text-xs font-bold text-white leading-tight">
                  {{ formatCurrency(authStore.balance, true) }}
                </span>
              </div>
            </RouterLink>

            <!-- User Menu Dropdown -->
            <div class="relative" v-click-outside="closeUserMenu">
              <button
                @click.stop="toggleUserMenu"
                class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-slate-800 transition border border-transparent focus:outline-none"
              >
                <div
                  class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white font-bold text-sm shadow-sm"
                >
                  {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                </div>
                <div class="text-left hidden lg:block">
                  <div class="text-xs font-bold text-white leading-tight">
                    {{ authStore.user?.name }}
                  </div>
                  <div class="text-[11px] text-slate-400">
                    {{ authStore.role === 'backer' ? 'Donatur' : (authStore.role === 'creator' ? 'Kreator' : 'Admin') }}
                  </div>
                </div>
                <i class="pi pi-chevron-down text-slate-400 text-xs ml-1"></i>
              </button>

              <!-- Dropdown Content -->
              <div
                v-if="isUserMenuOpen"
                class="absolute right-0 mt-2 w-64 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-2 z-50 animate-in fade-in zoom-in-95 duration-150"
              >
                <div class="px-3 py-2.5 border-b border-slate-800 mb-1">
                  <p class="text-xs font-bold text-white truncate">{{ authStore.user?.name }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ authStore.user?.email }}</p>
                  <div class="mt-2 flex items-center gap-1.5">
                    <StatusBadge type="role" :value="authStore.role" size="sm" :show-icon="false" />
                    <span
                      v-if="authStore.isEmailVerified"
                      class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 border border-emerald-800"
                    >
                      Terverifikasi
                    </span>
                    <span
                      v-else
                      class="text-[10px] px-2 py-0.5 rounded-full bg-amber-950 text-amber-400 border border-amber-800"
                    >
                      Belum Verifikasi
                    </span>
                  </div>
                </div>

                <div class="space-y-0.5">
                  <RouterLink
                    to="/notifications"
                    @click="closeUserMenu"
                    class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 rounded-xl transition"
                  >
                    <div class="flex items-center gap-2.5">
                      <i class="pi pi-bell text-sky-400"></i>
                      <span>Notifikasi</span>
                    </div>
                    <span
                      v-if="notificationStore.unreadCount > 0"
                      class="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-bold"
                    >
                      {{ notificationStore.unreadCount }}
                    </span>
                  </RouterLink>

                  <RouterLink
                    to="/transactions"
                    @click="closeUserMenu"
                    class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 rounded-xl transition"
                  >
                    <i class="pi pi-receipt text-emerald-400"></i>
                    <span>Riwayat Transaksi</span>
                  </RouterLink>

                  <!-- Upgrade to Creator button for Backer in dropdown -->
                  <button
                    v-if="authStore.isBacker"
                    type="button"
                    @click="
                      () => {
                        closeUserMenu()
                        isUpgradeModalOpen = true
                      }
                    "
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-amber-300 hover:text-amber-200 hover:bg-amber-950/40 rounded-xl transition text-left"
                  >
                    <i class="pi pi-bolt text-amber-400"></i>
                    <span>Upgrade Jadi Kreator</span>
                  </button>
                </div>

                <div class="border-t border-slate-800 mt-1 pt-1">
                  <button
                    @click="
                      () => {
                        closeUserMenu()
                        handleLogout()
                      }
                    "
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-950/40 rounded-xl transition text-left"
                  >
                    <i class="pi pi-sign-out"></i>
                    <span>Keluar (Logout)</span>
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Mobile Header Actions (Notification & Hamburger) -->
        <div class="flex items-center gap-2 md:hidden">
          <!-- Quick Notification Bell for logged in user on mobile top bar -->
          <RouterLink
            v-if="authStore.isAuthenticated"
            to="/notifications"
            class="relative p-2.5 rounded-xl bg-slate-800 text-slate-300 hover:text-white transition flex items-center justify-center"
            title="Notifikasi"
          >
            <i class="pi pi-bell text-sm"></i>
            <span
              v-if="notificationStore.unreadCount > 0"
              class="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-0.5 rounded-full bg-blue-600 text-white text-[9px] font-black flex items-center justify-center ring-2 ring-slate-900"
            >
              {{ notificationStore.unreadCount > 9 ? '9+' : notificationStore.unreadCount }}
            </span>
          </RouterLink>

          <!-- Mobile Hamburger Toggle Button -->
          <button
            type="button"
            @click="toggleMobileMenu"
            class="p-2.5 rounded-xl text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700/80 focus:outline-none transition flex items-center justify-center border border-slate-700/60"
            aria-label="Toggle navigation menu"
          >
            <i :class="isMobileMenuOpen ? 'pi pi-times' : 'pi pi-bars'" class="text-sm"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Upgrade to Creator Modal -->
    <UpgradeToCreatorModal v-model:visible="isUpgradeModalOpen" />
  </header>

  <!-- Mobile Nav Overlay & Drawer (Teleported to body so it never clips or disappears on scroll) -->
  <Teleport to="body">
    <Transition name="mobile-menu">
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 top-20 z-50 md:hidden flex flex-col pointer-events-auto"
      >
        <!-- Backdrop Blur Overlay (Click to Close) -->
        <div
          class="mobile-backdrop absolute inset-0 bg-slate-950/80 backdrop-blur-md"
          @click="closeMobileMenu"
        ></div>

        <!-- Drawer Content Panel -->
        <div
          class="mobile-panel relative z-10 bg-slate-900 border-b border-slate-800 px-4 pt-3 pb-8 space-y-4 max-h-[calc(100vh-5rem)] overflow-y-auto shadow-2xl overscroll-contain"
        >
          <!-- 1. Logged in User Profile & Wallet Mini Card -->
          <div v-if="authStore.isAuthenticated" class="p-4 rounded-2xl bg-slate-800/90 border border-slate-700/70 space-y-3 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white font-bold text-base shadow-md">
                {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-white truncate">{{ authStore.user?.name }}</div>
                <div class="text-xs text-slate-400 truncate">{{ authStore.user?.email }}</div>
                <div class="flex items-center gap-2 mt-1">
                  <StatusBadge type="role" :value="authStore.role" size="sm" :show-icon="false" />
                </div>
              </div>
            </div>

            <!-- Quick Wallet Balance Row -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-700/60 text-xs">
              <div class="flex items-center gap-2 text-slate-300">
                <i class="pi pi-wallet text-sky-400 text-sm"></i>
                <span>Saldo Dompet:</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="font-extrabold text-white">{{ formatCurrency(authStore.balance) }}</span>
                <RouterLink
                  to="/wallet"
                  @click="closeMobileMenu"
                  class="text-[11px] font-bold text-sky-400 hover:text-sky-300 bg-sky-500/10 px-2 py-0.5 rounded-lg border border-sky-500/20"
                >
                  Kelola
                </RouterLink>
              </div>
            </div>
          </div>

          <!-- 2. Primary Navigation Links (Clean Tiles) -->
          <div class="space-y-1">
            <!-- Jelajahi Kampanye -->
            <RouterLink
              to="/campaigns"
              @click="closeMobileMenu"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-800 transition"
              active-class="!bg-blue-600 !text-white font-bold"
            >
              <i class="pi pi-compass text-base text-sky-400"></i>
              <span>Jelajahi Kampanye</span>
            </RouterLink>

            <!-- Dashboard Link -->
            <RouterLink
              v-if="authStore.isCreator"
              to="/creator/dashboard"
              @click="closeMobileMenu"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-800 transition"
              active-class="!bg-blue-600 !text-white font-bold"
            >
              <i class="pi pi-chart-bar text-base text-blue-400"></i>
              <span>Dashboard Kreator</span>
            </RouterLink>
            <RouterLink
              v-else-if="authStore.isAuthenticated && !authStore.isAdmin"
              to="/dashboard"
              @click="closeMobileMenu"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-800 transition"
              active-class="!bg-blue-600 !text-white font-bold"
            >
              <i class="pi pi-home text-base text-blue-400"></i>
              <span>Dashboard Donatur</span>
            </RouterLink>

            <!-- Create Campaign CTA for Creator -->
            <RouterLink
              v-if="authStore.isCreator"
              to="/creator/campaigns/create"
              @click="closeMobileMenu"
              class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold text-white bg-blue-600/90 hover:bg-blue-600 shadow-md shadow-blue-600/20 transition"
            >
              <div class="flex items-center gap-3">
                <i class="pi pi-plus-circle text-base"></i>
                <span>Buat Kampanye Baru</span>
              </div>
              <i class="pi pi-arrow-right text-xs"></i>
            </RouterLink>

            <!-- Notifications Link (Mobile) -->
            <RouterLink
              v-if="authStore.isAuthenticated"
              to="/notifications"
              @click="closeMobileMenu"
              class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-800 transition"
              active-class="!bg-blue-600 !text-white font-bold"
            >
              <div class="flex items-center gap-3">
                <i class="pi pi-bell text-base text-amber-400"></i>
                <span>Pusat Notifikasi</span>
              </div>
              <span
                v-if="notificationStore.unreadCount > 0"
                class="px-2 py-0.5 rounded-full bg-blue-600 text-white text-xs font-black"
              >
                {{ notificationStore.unreadCount }} Baru
              </span>
            </RouterLink>

            <!-- Riwayat Transaksi -->
            <RouterLink
              v-if="authStore.isAuthenticated"
              to="/transactions"
              @click="closeMobileMenu"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-800 transition"
              active-class="!bg-blue-600 !text-white font-bold"
            >
              <i class="pi pi-receipt text-base text-emerald-400"></i>
              <span>Riwayat Transaksi</span>
            </RouterLink>
          </div>

          <!-- 3. Upgrade to Creator Card (for Backer) -->
          <div v-if="authStore.isBacker" class="p-4 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/20 to-yellow-500/10 border border-amber-500/30 space-y-2">
            <div class="flex items-center gap-2 text-amber-300 text-xs font-bold">
              <i class="pi pi-bolt text-amber-400"></i>
              <span>Ingin Galang Dana Proyek?</span>
            </div>
            <p class="text-[11px] text-amber-200/80 leading-relaxed">
              Tingkatkan akun menjadi Kreator untuk mulai membuat kampanye penggalangan dana Anda sendiri.
            </p>
            <button
              type="button"
              @click="
                () => {
                  closeMobileMenu()
                  isUpgradeModalOpen = true
                }
              "
              class="w-full py-2.5 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-sm transition flex items-center justify-center gap-1.5"
            >
              <span>Upgrade Jadi Kreator</span>
              <i class="pi pi-arrow-right text-[10px]"></i>
            </button>
          </div>

          <!-- 4. Guest Login / Register Buttons -->
          <div v-if="!authStore.isAuthenticated" class="pt-2 grid grid-cols-2 gap-2">
            <RouterLink
              to="/login"
              @click="closeMobileMenu"
              class="text-center py-2.5 px-4 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 transition"
            >
              Masuk
            </RouterLink>
            <RouterLink
              to="/register"
              @click="closeMobileMenu"
              class="text-center py-2.5 px-4 text-xs font-extrabold text-slate-900 bg-white hover:bg-slate-100 rounded-xl transition shadow-sm"
            >
              Daftar Akun
            </RouterLink>
          </div>

          <!-- 5. Logout Button (Mobile) -->
          <div v-if="authStore.isAuthenticated" class="pt-2 border-t border-slate-800">
            <button
              type="button"
              @click="
                () => {
                  closeMobileMenu()
                  handleLogout()
                }
              "
              class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold text-rose-400 hover:text-white bg-rose-500/10 hover:bg-rose-600 rounded-xl border border-rose-500/20 transition"
            >
              <i class="pi pi-sign-out text-sm"></i>
              <span>Keluar dari Akun</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Smooth Mobile Overlay & Panel Slide Animations */
.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.mobile-menu-enter-active .mobile-panel,
.mobile-menu-leave-active .mobile-panel {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
}

.mobile-menu-enter-from .mobile-panel,
.mobile-menu-leave-to .mobile-panel {
  transform: translateY(-16px);
  opacity: 0;
}
</style>
