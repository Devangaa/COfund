<script setup>
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDateTime } from '@/utils/formatDate'
import StatusBadge from '@/components/common/StatusBadge.vue'

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  user: {
    type: Object,
    default: null,
  },
  stats: {
    type: Object,
    default: () => ({}),
  },
  isActionLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'toggle-suspend'])

function handleClose() {
  emit('update:visible', false)
}
</script>

<template>
  <div
    v-if="visible && user"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white text-xl font-bold shadow-md">
          {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
        </div>
        <div>
          <h3 class="text-lg font-bold text-slate-900">{{ user.name }}</h3>
          <p class="text-xs text-slate-500">{{ user.email }}</p>
          <div class="flex items-center gap-2 mt-1.5">
            <StatusBadge type="role" :value="user.role" size="sm" :show-icon="false" />
            <span
              v-if="user.is_suspended"
              class="text-[10px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-bold"
            >
              Ditangguhkan
            </span>
            <span
              v-else
              class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold"
            >
              Aktif
            </span>
          </div>
        </div>
      </div>

      <!-- Detail list -->
      <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-2.5 text-xs mb-6">
        <div class="flex justify-between text-slate-500">
          <span>Saldo Akun</span>
          <span class="font-bold text-slate-900">{{ formatCurrency(user.balance) }}</span>
        </div>
        <div class="flex justify-between text-slate-500">
          <span>Status Email</span>
          <span v-if="user.email_verified_at" class="font-semibold text-emerald-600">
            Terverifikasi ({{ formatDateTime(user.email_verified_at) }})
          </span>
          <span v-else class="font-semibold text-amber-600">Belum Verifikasi</span>
        </div>
        <div v-if="stats.total_campaigns_created !== undefined" class="flex justify-between text-slate-500">
          <span>Kampanye Dibuat</span>
          <span class="font-bold text-slate-900">{{ stats.total_campaigns_created }}</span>
        </div>
        <div v-if="stats.total_backings !== undefined" class="flex justify-between text-slate-500">
          <span>Jumlah Backing</span>
          <span class="font-bold text-slate-900">{{ stats.total_backings }}</span>
        </div>
        <div v-if="stats.total_amount_backed !== undefined" class="flex justify-between text-slate-500">
          <span>Total Didukung</span>
          <span class="font-bold text-slate-900">{{ formatCurrency(stats.total_amount_backed) }}</span>
        </div>
      </div>

      <!-- Suspend / Unsuspend Action -->
      <div class="flex gap-3">
        <button
          type="button"
          @click="handleClose"
          class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
        >
          Tutup
        </button>
        <button
          v-if="user.is_suspended"
          type="button"
          @click="$emit('toggle-suspend', user)"
          :disabled="isActionLoading"
          class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition flex items-center justify-center gap-2 shadow-sm"
        >
          <i v-if="isActionLoading" class="pi pi-spin pi-spinner text-xs"></i>
          <span>Aktifkan Akun (Unsuspend)</span>
        </button>
        <button
          v-else
          type="button"
          @click="$emit('toggle-suspend', user)"
          :disabled="isActionLoading"
          class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition flex items-center justify-center gap-2 shadow-sm"
        >
          <i v-if="isActionLoading" class="pi pi-spin pi-spinner text-xs"></i>
          <span>Tangguhkan Akun (Suspend)</span>
        </button>
      </div>
    </div>
  </div>
</template>
