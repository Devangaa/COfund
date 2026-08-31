<script setup>
import { ref } from 'vue'
import { formatCurrency } from '@/utils/formatCurrency'

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  isProcessing: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'submit'])

const amount = ref(50000)
const errorMessage = ref('')

const presets = [25000, 50000, 100000, 250000, 500000, 1000000]

function setPreset(val) {
  amount.value = val
  errorMessage.value = ''
}

function handleClose() {
  errorMessage.value = ''
  emit('update:visible', false)
}

function handleSubmit() {
  const num = Number(amount.value)
  if (isNaN(num) || num < 10000) {
    errorMessage.value = 'Nominal deposit minimal adalah Rp 10.000'
    return
  }
  if (num > 100000000) {
    errorMessage.value = 'Nominal deposit maksimal adalah Rp 100.000.000'
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
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
          <i class="pi pi-arrow-down-left text-xl"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900">
          Top-up Saldo Dompet
        </h3>
        <p class="text-xs text-slate-500 mt-1">
          Deposit instan untuk mempermudah proses backing kampanye.
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-5">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Nominal Deposit (Rp)
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">
              Rp
            </span>
            <input
              type="number"
              v-model="amount"
              min="10000"
              max="100000000"
              step="5000"
              class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition"
              placeholder="50000"
            />
          </div>
          <p v-if="errorMessage" class="text-xs text-rose-500 font-medium mt-1.5">
            {{ errorMessage }}
          </p>
          <p v-else class="text-[11px] text-slate-400 mt-1.5">
            Minimal Rp 10.000 &bull; Maksimal Rp 100.000.000
          </p>
        </div>

        <!-- Presets -->
        <div class="space-y-2">
          <span class="text-xs text-slate-400 font-medium">Nominal Cepat:</span>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              v-for="val in presets"
              :key="val"
              @click="setPreset(val)"
              :class="[
                'py-2 px-2 rounded-xl text-xs font-bold border transition',
                amount === val
                  ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                  : 'border-slate-200 text-slate-700 hover:bg-slate-50',
              ]"
            >
              {{ formatCurrency(val, true) }}
            </button>
          </div>
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
            :disabled="isProcessing"
            class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2"
          >
            <i v-if="isProcessing" class="pi pi-spin pi-spinner text-xs"></i>
            <span>{{ isProcessing ? 'Memproses...' : 'Konfirmasi Deposit' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
