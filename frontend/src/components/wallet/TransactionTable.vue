<script setup>
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDateTime } from '@/utils/formatDate'
import { TRANSACTION_TYPE } from '@/utils/badgeHelper'
import StatusBadge from '@/components/common/StatusBadge.vue'

defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
  showUser: {
    type: Boolean,
    default: false,
  },
})

function getAmountClass(type) {
  if (['deposit', 'disbursement', 'refund'].includes(type)) {
    return 'text-emerald-600 font-bold'
  }
  return 'text-rose-600 font-bold'
}

function getAmountPrefix(type) {
  return TRANSACTION_TYPE[type]?.prefix || ''
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse">
      <thead>
        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50">
          <th class="py-3 px-4">Tipe & Referensi</th>
          <th v-if="showUser" class="py-3 px-4">Pengguna</th>
          <th class="py-3 px-4">Jumlah</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4 text-right">Waktu</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 text-xs">
        <tr v-if="transactions.length === 0">
          <td :colspan="showUser ? 5 : 4" class="py-8 text-center text-slate-400">
            Belum ada catatan transaksi.
          </td>
        </tr>
        <tr
          v-for="tx in transactions"
          :key="tx.id"
          class="hover:bg-slate-50/80 transition"
        >
          <td class="py-3.5 px-4">
            <div class="flex items-center gap-3">
              <StatusBadge type="transaction" :value="tx.type" size="sm" />
              <span class="font-mono text-[11px] text-slate-400 hidden sm:inline">
                {{ tx.reference || `#TX-${tx.id}` }}
              </span>
            </div>
          </td>
          <td v-if="showUser" class="py-3.5 px-4 font-medium text-slate-700">
            {{ tx.user?.name || '-' }}
          </td>
          <td class="py-3.5 px-4">
            <span :class="getAmountClass(tx.type)">
              {{ getAmountPrefix(tx.type) }} {{ formatCurrency(tx.amount) }}
            </span>
          </td>
          <td class="py-3.5 px-4">
            <span
              :class="[
                'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                tx.status === 'success'
                  ? 'bg-emerald-50 text-emerald-700'
                  : tx.status === 'pending'
                  ? 'bg-amber-50 text-amber-700'
                  : 'bg-rose-50 text-rose-700',
              ]"
            >
              {{ tx.status }}
            </span>
          </td>
          <td class="py-3.5 px-4 text-right text-slate-400">
            {{ formatDateTime(tx.created_at) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
