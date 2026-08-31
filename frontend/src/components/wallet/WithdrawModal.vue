<script setup>
import { ref } from 'vue'
import { formatCurrency } from '@/utils/formatCurrency'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  balance: {
    type: Number,
    required: true,
    default: 0,
  },
  isProcessing: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'submit'])

const amount = ref(10000)
const errorMessage = ref('')

function setMaxAmount() {
  amount.value = props.balance
  errorMessage.value = ''
}

function handleClose() {
  errorMessage.value = ''
  emit('update:visible', false)
}

function handleSubmit() {
  const num = Number(amount.value)
  if (isNaN(num) || num < 10000) {
    errorMessage.value = 'Nominal penarikan minimal adalah Rp 10.000'
    return
  }
  if (num > props.balance) {
    errorMessage.value = 'Saldo tidak mencukupi untuk melakukan penarikan ini'
    return
  }
  errorMessage.value = ''
  emit('submit', num)
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <div class="mb-6">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-3">
          <i class="pi pi-arrow-up-right text-xl"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900">
          Tarik Saldo Dompet
        </h3>
        <p class="text-xs text-slate-500 mt-1">
          Saldo tersedia: <span class="font-bold text-slate-900">{{ formatCurrency(balance) }}</span>
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-5">
        <div>
          <div class="flex justify-between items-center mb-2">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
              Nominal Penarikan (Rp)
            </label>
            <button
              type="button"
              @click="setMaxAmount"
              class="text-xs text-blue-600 font-bold hover:underline"
            >
              Tarik Semua
            </button>
          </div>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">
              Rp
            </span>
            <input
              type="number"
              v-model="amount"
              min="10000"
              :max="balance"
              step="5000"
              class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-rose-500 focus:bg-white transition"
              placeholder="10000"
            />
          </div>
          <p v-if="errorMessage" class="text-xs text-rose-500 font-medium mt-1.5">
            {{ errorMessage }}
          </p>
          <p v-else class="text-[11px] text-slate-400 mt-1.5">
            Minimal penarikan: Rp 10.000
          </p>
        </div>

        <div class="flex gap-3 pt-3">
          <button
            type="button"
            @click="handleClose"
            :disabled="isProcessing"
            class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isProcessing || balance < 10000"
            class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-md shadow-rose-600/20 flex items-center justify-center gap-2"
          >
            <i v-if="isProcessing" class="pi pi-spin pi-spinner text-xs"></i>
            <span>{{ isProcessing ? 'Memproses...' : 'Tarik Saldo' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
