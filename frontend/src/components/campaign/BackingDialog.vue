<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { formatCurrency } from '@/utils/formatCurrency'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  campaign: {
    type: Object,
    required: true,
  },
  initialTier: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'confirm'])
const router = useRouter()
const authStore = useAuthStore()

const amount = ref(10000)
const selectedTier = ref(props.initialTier)
const errorMessage = ref('')
const step = ref('form') // 'form' | 'mock_payment'

const minRequiredAmount = computed(() => {
  if (selectedTier.value && selectedTier.value.min_amount) {
    return Number(selectedTier.value.min_amount)
  }
  return 10000
})

watch(
  () => props.initialTier,
  (newTier) => {
    selectedTier.value = newTier
    if (newTier && newTier.min_amount) {
      amount.value = Number(newTier.min_amount)
    }
  },
  { immediate: true }
)

function setPresetAmount(val) {
  if (val >= minRequiredAmount.value) {
    amount.value = val
    errorMessage.value = ''
  }
}

function handleClose() {
  step.value = 'form'
  errorMessage.value = ''
  emit('update:visible', false)
}

function proceedToPayment() {
  errorMessage.value = ''
  if (!authStore.isAuthenticated) {
    handleClose()
    router.push('/login')
    return
  }

  const numAmount = Number(amount.value)
  if (isNaN(numAmount) || numAmount < minRequiredAmount.value) {
    errorMessage.value = `Nominal minimal adalah ${formatCurrency(minRequiredAmount.value)}`
    return
  }

  step.value = 'mock_payment'
}

function confirmPayment() {
  emit('confirm', {
    amount: Number(amount.value),
    tier: selectedTier.value,
  })
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <!-- Close Button -->
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <!-- Step 1: Form Backing Amount -->
      <div v-if="step === 'form'" class="space-y-6">
        <div>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 mb-2">
            <i class="pi pi-heart-fill text-[10px]"></i>
            Dukung Kampanye
          </span>
          <h3 class="text-xl font-bold text-slate-900">
            {{ campaign.title }}
          </h3>
        </div>

        <!-- Selected Tier Summary (if any) -->
        <div
          v-if="selectedTier"
          class="p-4 rounded-2xl bg-blue-50/50 border border-blue-100 flex items-center justify-between"
        >
          <div>
            <p class="text-xs text-blue-600 font-semibold uppercase tracking-wider">Reward Terpilih</p>
            <p class="text-sm font-bold text-slate-900">{{ selectedTier.name }}</p>
            <p class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ selectedTier.reward_description }}</p>
          </div>
          <button
            @click="selectedTier = null; amount = 10000"
            class="text-xs text-slate-400 hover:text-rose-600 underline font-medium"
          >
            Lepas Tier
          </button>
        </div>

        <!-- Input Amount -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
            Nominal Dukungan (Rp)
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">
              Rp
            </span>
            <input
              type="number"
              v-model="amount"
              :min="minRequiredAmount"
              step="5000"
              class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
              placeholder="10000"
            />
          </div>
          <p v-if="errorMessage" class="text-xs text-rose-500 font-medium">
            {{ errorMessage }}
          </p>
          <p v-else class="text-[11px] text-slate-400">
            Minimal dukungan: {{ formatCurrency(minRequiredAmount) }}
          </p>
        </div>

        <!-- Quick Amount Presets -->
        <div class="space-y-2">
          <span class="text-xs text-slate-400 font-medium">Pilihan Cepat:</span>
          <div class="grid grid-cols-4 gap-2">
            <button
              v-for="val in [25000, 50000, 100000, 250000]"
              :key="val"
              @click="setPresetAmount(val)"
              :class="[
                'py-2 px-2 rounded-xl text-xs font-bold border transition',
                amount === val
                  ? 'border-blue-600 bg-blue-50 text-blue-600'
                  : 'border-slate-200 text-slate-700 hover:bg-slate-50',
              ]"
            >
              {{ formatCurrency(val, true) }}
            </button>
          </div>
        </div>

        <!-- CTA Proceed -->
        <div class="pt-2">
          <button
            @click="proceedToPayment"
            class="w-full py-3.5 px-6 rounded-2xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-600/20 flex items-center justify-center gap-2"
          >
            <span>Lanjut ke Pembayaran</span>
            <i class="pi pi-arrow-right text-xs"></i>
          </button>
        </div>
      </div>

      <!-- Step 2: Mock Payment Gateway Simulation -->
      <div v-else class="space-y-6">
        <div class="text-center space-y-1">
          <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 mx-auto flex items-center justify-center mb-3">
            <i class="pi pi-shield text-xl"></i>
          </div>
          <h3 class="text-lg font-bold text-slate-900">Konfirmasi Pembayaran</h3>
          <p class="text-xs text-slate-500">Virtual Escrow Simulation</p>
        </div>

        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3 text-xs">
          <div class="flex justify-between text-slate-500">
            <span>Penerima Kampanye</span>
            <span class="font-bold text-slate-800">{{ campaign.title }}</span>
          </div>
          <div v-if="selectedTier" class="flex justify-between text-slate-500">
            <span>Reward Tier</span>
            <span class="font-bold text-slate-800">{{ selectedTier.name }}</span>
          </div>
          <div class="flex justify-between text-slate-500">
            <span>Metode Simulasi</span>
            <span class="font-semibold text-slate-800">Mock Virtual Account / Saldo</span>
          </div>
          <div class="border-t border-slate-200 pt-3 flex justify-between items-baseline">
            <span class="font-bold text-slate-900">Total Pembayaran</span>
            <span class="text-lg font-black text-blue-600">{{ formatCurrency(amount) }}</span>
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] flex items-start gap-2.5">
          <i class="pi pi-info-circle text-sm text-amber-600 mt-0.5"></i>
          <span>
            Dana Anda akan disimpan dengan aman di <strong>Virtual Escrow</strong> hingga kampanye mencapai target. Jika kampanye tidak mencapai target saat deadline berakhir, dana akan otomatis di-refund ke saldo akun Anda.
          </span>
        </div>

        <div class="flex gap-3 pt-2">
          <button
            @click="step = 'form'"
            :disabled="isSubmitting"
            class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
          >
            Kembali
          </button>
          <button
            @click="confirmPayment"
            :disabled="isSubmitting"
            class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm shadow-glow-amber transition-all duration-300 flex items-center justify-center gap-2 active:scale-[0.98]"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
            <i v-else class="pi pi-bolt text-xs"></i>
            <span>{{ isSubmitting ? 'Memproses Transaksi...' : 'Konfirmasi & Bayar Sekarang' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
