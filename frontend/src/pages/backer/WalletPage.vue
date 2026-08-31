<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useWallet } from '@/composables/useWallet'
import { formatCurrency } from '@/utils/formatCurrency'
import TransactionTable from '@/components/wallet/TransactionTable.vue'
import DepositModal from '@/components/wallet/DepositModal.vue'
import WithdrawModal from '@/components/wallet/WithdrawModal.vue'
import Pagination from '@/components/common/Pagination.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'

const authStore = useAuthStore()
const {
  walletStore,
  isDepositModalOpen,
  isWithdrawModalOpen,
  filterType,
  filterStatus,
  openDepositModal,
  closeDepositModal,
  openWithdrawModal,
  closeWithdrawModal,
  loadTransactions,
  handleDeposit,
  handleWithdraw,
} = useWallet()

const typeOptions = [
  { label: 'Semua Tipe Transaksi', value: '' },
  { label: 'Deposit Saldo', value: 'deposit' },
  { label: 'Penarikan Saldo', value: 'withdrawal' },
  { label: 'Dukungan Kampanye', value: 'payment' },
  { label: 'Pengembalian Dana', value: 'refund' },
  { label: 'Pencairan Dana', value: 'disbursement' },
  { label: 'Biaya Layanan Platform', value: 'platform_fee' },
]

onMounted(async () => {
  await authStore.fetchMe()
  await loadTransactions(1)
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="space-y-1">
      <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Digital Virtual Wallet</span>
      <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Dompet Saldo & Transaksi
      </h1>
      <p class="text-xs sm:text-sm text-slate-500">
        Kelola saldo virtual untuk mendanai kampanye atau mencairkan hasil galang dana proyek Anda.
      </p>
    </div>

    <!-- FinTech Balance Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 relative bg-gradient-to-tr from-slate-900 via-slate-900 to-blue-950 rounded-3xl p-8 sm:p-10 text-white shadow-xl overflow-hidden border border-slate-800">
        <!-- Glow circles -->
        <div class="absolute -top-12 -right-12 w-64 h-64 bg-blue-600/20 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-10 left-1/3 w-48 h-48 bg-sky-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col justify-between h-full space-y-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-400">
              <i class="pi pi-shield"></i>
              <span>Saldo Virtual Terlindungi</span>
            </div>
            <span class="text-xs font-semibold text-slate-400">
              ID User: #{{ authStore.user?.id }}
            </span>
          </div>

          <div>
            <div class="text-xs text-slate-400 font-medium mb-1">Saldo Tersedia Saat Ini</div>
            <div class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white">
              {{ formatCurrency(authStore.balance) }}
            </div>
          </div>

          <div class="flex flex-wrap gap-3 pt-2">
            <button
              @click="openDepositModal"
              class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-lg shadow-blue-600/30 transition flex items-center gap-2"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Isi Saldo (Deposit)</span>
            </button>
            <button
              @click="openWithdrawModal"
              class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs transition flex items-center gap-2"
            >
              <i class="pi pi-arrow-up-right text-xs text-rose-400"></i>
              <span>Tarik Saldo (Withdraw)</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Escrow Info -->
      <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm flex flex-col justify-between space-y-4">
        <div>
          <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
            <i class="pi pi-lock text-lg"></i>
          </div>
          <h3 class="text-base font-bold text-slate-900">Virtual Escrow Protocol</h3>
          <p class="text-xs text-slate-500 leading-relaxed mt-2">
            Setiap dana yang Anda backing ditahan dalam escrow platform. Jika kampanye dibatalkan atau tidak mencapai target, dana otomatis dikembalikan ke saldo dompet ini.
          </p>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 text-[11px] text-slate-600 space-y-1">
          <div class="flex justify-between font-medium">
            <span>Status Akun:</span>
            <span :class="authStore.isSuspended ? 'text-rose-600 font-bold' : 'text-emerald-600 font-bold'">
              {{ authStore.isSuspended ? 'Suspended' : 'Aktif' }}
            </span>
          </div>
          <div class="flex justify-between font-medium">
            <span>Verifikasi Email:</span>
            <span :class="authStore.isEmailVerified ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold'">
              {{ authStore.isEmailVerified ? 'Terverifikasi' : 'Belum' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction History Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Riwayat Mutasi Saldo</h3>
          <p class="text-xs text-slate-500">Seluruh catatan transaksi deposit, penarikan, donasi, dan refund.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap sm:flex-nowrap">
          <!-- Filter type select -->
          <div class="w-full sm:w-60">
            <CustomSelect
              v-model="filterType"
              :options="typeOptions"
              placeholder="Pilih Tipe Transaksi"
              @change="loadTransactions(1)"
            />
          </div>

          <RouterLink
            to="/transactions"
            class="px-4 py-2.5 sm:py-3 rounded-2xl bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-bold text-xs transition flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>Semua Laporan</span>
            <i class="pi pi-arrow-right text-[10px]"></i>
          </RouterLink>
        </div>
      </div>

      <SkeletonLoader v-if="walletStore.isLoading" type="table" :count="5" />

      <TransactionTable
        v-else
        :transactions="walletStore.transactions"
      />

      <!-- Pagination -->
      <Pagination
        v-if="!walletStore.isLoading && walletStore.transactions.length > 0"
        :current-page="walletStore.pagination.currentPage"
        :last-page="walletStore.pagination.lastPage"
        :total="walletStore.pagination.total"
        :per-page="walletStore.pagination.perPage"
        @page-change="loadTransactions"
      />
    </div>

    <!-- Modals -->
    <DepositModal
      v-model:visible="isDepositModalOpen"
      :is-processing="walletStore.isProcessing"
      @submit="handleDeposit"
    />

    <WithdrawModal
      v-model:visible="isWithdrawModalOpen"
      :balance="authStore.balance"
      :is-processing="walletStore.isProcessing"
      @submit="handleWithdraw"
    />
  </div>
</template>
