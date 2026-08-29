<script setup>
import { ref, onMounted } from 'vue'
import { useAdminStore } from '@/stores/useAdminStore'
import { adminService } from '@/services/adminService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import Pagination from '@/components/common/Pagination.vue'
import UserDetailModal from '@/components/admin/UserDetailModal.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'
import { useConfirm } from '@/composables/useConfirm'

const adminStore = useAdminStore()
const { confirm: showConfirmModal } = useConfirm()

const searchInput = ref('')
const selectedRole = ref('')
const selectedSuspended = ref('')
const selectedUser = ref(null)
const userStats = ref({})
const isDetailModalOpen = ref(false)

const roleOptions = [
  { label: 'Semua Peran', value: '' },
  { label: 'Donatur', value: 'backer' },
  { label: 'Kreator', value: 'creator' },
  { label: 'Admin', value: 'admin' },
]

const statusOptions = [
  { label: 'Semua Status Akun', value: '' },
  { label: 'Hanya Akun Aktif', value: 'false' },
  { label: 'Hanya Akun Ditangguhkan', value: 'true' },
]

let searchDebounce = null

async function loadUsers(page = 1) {
  const params = { page, per_page: 10 }
  if (searchInput.value.trim()) params.search = searchInput.value.trim()
  if (selectedRole.value) params.role = selectedRole.value
  if (selectedSuspended.value !== '') params.is_suspended = selectedSuspended.value
  await adminStore.fetchUsers(params)
}

function handleSearch() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    loadUsers(1)
  }, 400)
}

async function openUserDetail(user) {
  selectedUser.value = user
  userStats.value = {}
  isDetailModalOpen.value = true
  try {
    const res = await adminService.getUser(user.id)
    const data = res.data?.data || res.data
    selectedUser.value = data.user || data
    userStats.value = data.stats || {}
  } catch {
    // fallback to clicked item
  }
}

async function handleToggleSuspend(user) {
  if (user.is_suspended) {
    const isConfirmed = await showConfirmModal({
      title: 'Aktifkan Kembali Akun',
      message: `Apakah Anda yakin ingin mengaktifkan kembali akun pengguna ${user.name}? Pengguna ini akan dapat login dan bertransaksi normal kembali.`,
      type: 'success',
      confirmText: 'Ya, Aktifkan',
      cancelText: 'Batal',
    })

    if (isConfirmed) {
      await adminStore.unsuspendUser(user.id)
      if (selectedUser.value && selectedUser.value.id === user.id) {
        selectedUser.value.is_suspended = false
      }
    }
  } else {
    const isConfirmed = await showConfirmModal({
      title: 'Tangguhkan Akun Pengguna',
      message: `PERINGATAN: Menangguhkan akun ${user.name} akan memblokir akses login dan membekukan seluruh aktivitas transaksi pengguna ini. Lanjutkan?`,
      type: 'danger',
      confirmText: 'Ya, Tangguhkan',
      cancelText: 'Batal',
    })

    if (isConfirmed) {
      await adminStore.suspendUser(user.id)
      if (selectedUser.value && selectedUser.value.id === user.id) {
        selectedUser.value.is_suspended = true
      }
    }
  }
}

onMounted(() => {
  loadUsers(1)
})
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div>
      <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Manajemen Pengguna</span>
      <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Daftar Pengguna CoFund
      </h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">
        Kelola akun backer, kreator, serta status penangguhan (suspension) sistem.
      </p>
    </div>

    <!-- Filter & Search Controls -->
    <div class="p-6 bg-white rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="sm:col-span-6 relative">
          <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input
            type="text"
            v-model="searchInput"
            @input="handleSearch"
            placeholder="Cari berdasarkan nama lengkap atau alamat email..."
            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          />
        </div>

        <!-- Role Select -->
        <div class="sm:col-span-3">
          <CustomSelect
            v-model="selectedRole"
            :options="roleOptions"
            theme="light"
            placeholder="Semua Peran"
            @change="loadUsers(1)"
          />
        </div>

        <!-- Suspended Select -->
        <div class="sm:col-span-3">
          <CustomSelect
            v-model="selectedSuspended"
            :options="statusOptions"
            theme="light"
            placeholder="Semua Status"
            @change="loadUsers(1)"
          />
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
      <SkeletonLoader v-if="adminStore.isLoading" type="table" :count="5" />

      <div
        v-else-if="adminStore.users.length === 0"
        class="text-center py-12 text-slate-400 text-xs"
      >
        Tidak ada data pengguna yang sesuai dengan kriteria filter.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider bg-slate-50/80">
              <th class="py-3 px-4">Pengguna</th>
              <th class="py-3 px-4">Peran (Role)</th>
              <th class="py-3 px-4">Saldo Dompet</th>
              <th class="py-3 px-4">Status Email</th>
              <th class="py-3 px-4">Status Akun</th>
              <th class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="u in adminStore.users"
              :key="u.id"
              class="hover:bg-slate-50 transition"
            >
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                    {{ u.name?.charAt(0)?.toUpperCase() || 'U' }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-900">{{ u.name }}</div>
                    <div class="text-[11px] text-slate-400">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4">
                <StatusBadge type="role" :value="u.role" size="sm" :show-icon="false" />
              </td>
              <td class="py-4 px-4 font-bold text-slate-900">
                {{ formatCurrency(u.balance) }}
              </td>
              <td class="py-4 px-4">
                <span
                  v-if="u.email_verified_at"
                  class="text-[11px] font-bold text-emerald-600 flex items-center gap-1"
                >
                  <i class="pi pi-check text-[10px]"></i>
                  Terverifikasi
                </span>
                <span v-else class="text-[11px] font-bold text-amber-600">
                  Belum Verifikasi
                </span>
              </td>
              <td class="py-4 px-4">
                <span
                  v-if="u.is_suspended"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                >
                  Ditangguhkan
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  Aktif
                </span>
              </td>
              <td class="py-4 px-4 text-right space-x-2 whitespace-nowrap">
                <button
                  @click="openUserDetail(u)"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition"
                >
                  Detail
                </button>
                <button
                  v-if="u.is_suspended"
                  @click="handleToggleSuspend(u)"
                  :disabled="adminStore.isActionLoading"
                  class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white font-bold text-xs transition border border-emerald-200"
                >
                  Unsuspend
                </button>
                <button
                  v-else
                  @click="handleToggleSuspend(u)"
                  :disabled="adminStore.isActionLoading"
                  class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-bold text-xs transition border border-rose-200"
                >
                  Suspend
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-100">
        <Pagination
          v-if="!adminStore.isLoading && adminStore.users.length > 0"
          :current-page="adminStore.pagination.currentPage"
          :last-page="adminStore.pagination.lastPage"
          :total="adminStore.pagination.total"
          :per-page="adminStore.pagination.perPage"
          @page-change="loadUsers"
        />
      </div>
    </div>

    <!-- User Detail Modal -->
    <UserDetailModal
      v-model:visible="isDetailModalOpen"
      :user="selectedUser"
      :stats="userStats"
      :is-action-loading="adminStore.isActionLoading"
      @toggle-suspend="handleToggleSuspend"
    />
  </div>
</template>
