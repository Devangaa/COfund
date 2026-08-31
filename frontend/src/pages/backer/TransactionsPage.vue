<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { transactionService } from '@/services/transactionService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate, formatDateTime } from '@/utils/formatDate'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import Pagination from '@/components/common/Pagination.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'

const transactions = ref([])
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  perPage: 15,
})
const isLoading = ref(true)

// Filters
const filterType = ref('')
const filterStatus = ref('')
const filterSort = ref('latest')
const filterStartDate = ref('')
const filterEndDate = ref('')

// Modal state
const selectedTransaction = ref(null)
const isDetailModalOpen = ref(false)

const typeOptions = [
  { label: 'Semua Tipe', value: '' },
  { label: 'Deposit Saldo', value: 'deposit' },
  { label: 'Penarikan Saldo', value: 'withdrawal' },
  { label: 'Dukungan Kampanye', value: 'payment' },
  { label: 'Pengembalian Dana', value: 'refund' },
  { label: 'Pencairan Dana', value: 'disbursement' },
  { label: 'Biaya Layanan Platform', value: 'platform_fee' },
]

const statusOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Berhasil', value: 'success' },
  { label: 'Menunggu Pembayaran', value: 'pending' },
  { label: 'Gagal', value: 'failed' },
]

const sortOptions = [
  { label: 'Terbaru Dahulu', value: 'latest' },
  { label: 'Terlama Dahulu', value: 'oldest' },
]

async function loadTransactions(page = 1) {
  isLoading.value = true
  try {
    const params = {
      page,
      per_page: pagination.value.perPage,
      sort: filterSort.value,
    }
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterStartDate.value) params.start_date = filterStartDate.value
    if (filterEndDate.value) params.end_date = filterEndDate.value

    const res = await transactionService.getAll(params)
    transactions.value = res.data?.data || []
    if (res.data?.meta?.pagination) {
      pagination.value = {
        currentPage: res.data.meta.pagination.current_page,
        lastPage: res.data.meta.pagination.last_page,
        total: res.data.meta.pagination.total,
        perPage: res.data.meta.pagination.per_page,
      }
    }
  } catch {
    transactions.value = []
  } finally {
    isLoading.value = false
  }
}

function resetFilters() {
  filterType.value = ''
  filterStatus.value = ''
  filterSort.value = 'latest'
  filterStartDate.value = ''
  filterEndDate.value = ''
  loadTransactions(1)
}

function openDetail(tx) {
  selectedTransaction.value = tx
  isDetailModalOpen.value = true
}

function closeDetail() {
  selectedTransaction.value = null
  isDetailModalOpen.value = false
}

function getTypeBadge(type) {
  switch (type) {
    case 'deposit':
      return { label: 'Deposit Saldo', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', icon: 'pi pi-arrow-down-left text-emerald-600', isPositive: true }
    case 'withdrawal':
      return { label: 'Penarikan Dana', bg: 'bg-rose-50 text-rose-700 border-rose-200', icon: 'pi pi-arrow-up-right text-rose-600', isPositive: false }
    case 'payment':
      return { label: 'Dukungan Proyek', bg: 'bg-purple-50 text-purple-700 border-purple-200', icon: 'pi pi-heart-fill text-purple-600', isPositive: false }
    case 'refund':
      return { label: 'Refund Pengembalian', bg: 'bg-teal-50 text-teal-700 border-teal-200', icon: 'pi pi-replay text-teal-600', isPositive: true }
    case 'disbursement':
      return { label: 'Pencairan Kampanye', bg: 'bg-blue-50 text-blue-700 border-blue-200', icon: 'pi pi-dollar text-blue-600', isPositive: true }
    case 'platform_fee':
      return { label: 'Biaya Platform', bg: 'bg-slate-100 text-slate-700 border-slate-200', icon: 'pi pi-tag text-slate-600', isPositive: false }
    default:
      return { label: type || 'Transaksi', bg: 'bg-slate-50 text-slate-700 border-slate-200', icon: 'pi pi-receipt text-slate-600', isPositive: null }
  }
}

onMounted(() => {
  loadTransactions(1)
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
          <RouterLink to="/wallet" class="hover:text-blue-600 font-medium">Dompet Saldo</RouterLink>
          <i class="pi pi-chevron-right text-[10px]"></i>
          <span class="font-bold text-slate-800">Riwayat Mutasi</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          Laporan Riwayat Transaksi
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Rekap mutasi transaksi deposit, penarikan saldo, dukungan kampanye, serta pengembalian dana escrow.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <RouterLink
          to="/wallet"
          class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-lg shadow-blue-600/20 transition flex-shrink-0"
        >
          <i class="pi pi-wallet text-xs"></i>
          <span>Buka Dompet Saldo</span>
        </RouterLink>
      </div>
    </div>

    <!-- Filter Control Card -->
    <div class="p-6 bg-white rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-700 uppercase tracking-wider">
          <i class="pi pi-filter text-blue-600"></i>
          <span>Filter & Pencarian Mutasi</span>
        </div>
        <button
          type="button"
          @click="resetFilters"
          class="text-xs text-blue-600 hover:text-blue-800 font-semibold transition"
        >
          Reset Filter
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <!-- Filter Type -->
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Tipe Transaksi</label>
          <CustomSelect
            v-model="filterType"
            :options="typeOptions"
            placeholder="Semua Tipe"
            size="sm"
            @change="loadTransactions(1)"
          />
        </div>

        <!-- Filter Status -->
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Status</label>
          <CustomSelect
            v-model="filterStatus"
            :options="statusOptions"
            placeholder="Semua Status"
            size="sm"
            @change="loadTransactions(1)"
          />
        </div>

        <!-- Filter Start Date -->
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Dari Tanggal</label>
          <input
            type="date"
            v-model="filterStartDate"
            @change="loadTransactions(1)"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
          />
        </div>

        <!-- Filter End Date -->
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Sampai Tanggal</label>
          <input
            type="date"
            v-model="filterEndDate"
            @change="loadTransactions(1)"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
          />
        </div>
      </div>
    </div>

    <!-- Transactions Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-bold text-slate-900">Daftar Transaksi</h3>
          <p class="text-xs text-slate-500">Menampilkan {{ transactions.length }} dari {{ pagination.total }} transaksi.</p>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-xs text-slate-500 font-medium whitespace-nowrap">Urutan:</span>
          <div class="w-48">
            <CustomSelect
              v-model="filterSort"
              :options="sortOptions"
              placeholder="Pilih Urutan"
              size="sm"
              @change="loadTransactions(1)"
            />
          </div>
        </div>
      </div>

      <SkeletonLoader v-if="isLoading" type="table" :count="6" />

      <div
        v-else-if="transactions.length === 0"
        class="p-12 text-center text-slate-400 text-xs"
      >
        <i class="pi pi-receipt text-3xl mb-2 block text-slate-300"></i>
        Tidak ada data transaksi yang sesuai dengan filter yang dipilih.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50">
              <th class="py-3.5 px-4">Tipe & Referensi</th>
              <th class="py-3.5 px-4">Nominal</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4">Waktu Transaksi</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="tx in transactions"
              :key="tx.id"
              class="hover:bg-slate-50/80 transition"
            >
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div
                    :class="[
                      'w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 border',
                      getTypeBadge(tx.type).bg,
                    ]"
                  >
                    <i :class="getTypeBadge(tx.type).icon"></i>
                  </div>
                  <div>
                    <div class="font-bold text-slate-900">
                      {{ getTypeBadge(tx.type).label }}
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                      Ref: {{ tx.reference || `#TX-${tx.id}` }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="py-4 px-4">
                <span
                  :class="[
                    'font-black text-sm',
                    getTypeBadge(tx.type).isPositive === true
                      ? 'text-emerald-600'
                      : getTypeBadge(tx.type).isPositive === false
                      ? 'text-slate-900'
                      : 'text-slate-800',
                  ]"
                >
                  {{ getTypeBadge(tx.type).isPositive ? '+' : getTypeBadge(tx.type).isPositive === false ? '-' : '' }}
                  {{ formatCurrency(tx.amount) }}
                </span>
              </td>

              <td class="py-4 px-4">
                <StatusBadge type="transaction" :value="tx.status" size="sm" />
              </td>

              <td class="py-4 px-4 text-slate-500">
                <div>{{ formatDate(tx.created_at) }}</div>
                <div class="text-[10px] text-slate-400">{{ formatDateTime(tx.created_at).split(' ')[1] || '' }}</div>
              </td>

              <td class="py-4 px-4 text-right">
                <button
                  type="button"
                  @click="openDetail(tx)"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition"
                >
                  Detail
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <Pagination
        v-if="!isLoading && transactions.length > 0"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :total="pagination.total"
        :per-page="pagination.perPage"
        @page-change="loadTransactions"
      />
    </div>

    <!-- Transaction Detail Modal -->
    <div
      v-if="isDetailModalOpen && selectedTransaction"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-in fade-in duration-200"
    >
      <div
        class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200 space-y-6"
      >
        <!-- Close button -->
        <button
          @click="closeDetail"
          class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
        >
          <i class="pi pi-times text-xs"></i>
        </button>

        <div>
          <div class="flex items-center gap-2 mb-2">
            <span
              :class="[
                'px-2.5 py-1 rounded-lg text-xs font-bold border',
                getTypeBadge(selectedTransaction.type).bg,
              ]"
            >
              <i :class="[getTypeBadge(selectedTransaction.type).icon, 'mr-1']"></i>
              {{ getTypeBadge(selectedTransaction.type).label }}
            </span>
            <StatusBadge type="transaction" :value="selectedTransaction.status" size="sm" />
          </div>
          <h3 class="text-2xl font-black text-slate-900 tracking-tight">
            {{ formatCurrency(selectedTransaction.amount) }}
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">
            {{ formatDateTime(selectedTransaction.created_at) }}
          </p>
        </div>

        <div class="space-y-3 p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs divide-y divide-slate-200/60">
          <div class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">ID Transaksi:</span>
            <span class="font-mono font-bold text-slate-800">#{{ selectedTransaction.id }}</span>
          </div>
          <div class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">Kode Referensi:</span>
            <span class="font-mono font-bold text-slate-800">{{ selectedTransaction.reference || '-' }}</span>
          </div>
          <div class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">Tipe Mutasi:</span>
            <span class="font-bold text-slate-800 capitalize">{{ selectedTransaction.type }}</span>
          </div>
          <div v-if="selectedTransaction.backing_id" class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">ID Backing:</span>
            <span class="font-bold text-slate-800">#{{ selectedTransaction.backing_id }}</span>
          </div>
          <div v-if="selectedTransaction.campaign_id" class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">ID Kampanye:</span>
            <span class="font-bold text-slate-800">#{{ selectedTransaction.campaign_id }}</span>
          </div>
          <div class="flex justify-between py-1.5">
            <span class="text-slate-500 font-medium">Status Escrow:</span>
            <span class="font-bold text-emerald-600">Terlindungi Escrow</span>
          </div>
        </div>

        <button
          type="button"
          @click="closeDetail"
          class="w-full py-3 px-4 rounded-2xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
        >
          Tutup
        </button>
      </div>
    </div>
  </div>
</template>
