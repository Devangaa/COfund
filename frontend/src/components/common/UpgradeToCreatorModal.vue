<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'success'])
const router = useRouter()
const authStore = useAuthStore()

const reason = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)

function handleClose() {
  reason.value = ''
  errorMessage.value = ''
  emit('update:visible', false)
}

async function handleSubmit() {
  if (!reason.value.trim()) {
    errorMessage.value = 'Alasan upgrade ke Creator wajib diisi.'
    return
  }
  if (reason.value.trim().length < 10) {
    errorMessage.value = 'Mohon berikan alasan minimal 10 karakter.'
    return
  }
  if (reason.value.trim().length > 500) {
    errorMessage.value = 'Alasan maksimal 500 karakter.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  const result = await authStore.upgradeToCreator(reason.value.trim())
  isSubmitting.value = false

  if (result.success) {
    handleClose()
    emit('success')
    router.push('/creator/dashboard')
  } else {
    errorMessage.value =
      result.error?.response?.data?.errors?.reason?.[0] ||
      result.error?.response?.data?.message ||
      'Gagal melakukan upgrade ke Creator. Silakan coba lagi.'
  }
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <!-- Close button -->
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <!-- Header -->
      <div class="space-y-2 mb-6">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
          <i class="pi pi-bolt text-amber-500 text-xs"></i>
          <span>Creator Studio</span>
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
          Upgrade Akun Menjadi Kreator
        </h3>
        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
          Wujudkan ide dan proyek impian Anda. Sebagai Kreator, Anda dapat membuat kampanye galang dana, mengelola reward tier, dan mempublikasikan kabar terbaru kepada para backer.
        </p>
      </div>

      <!-- Feature highlights -->
      <div class="grid grid-cols-2 gap-2.5 p-3.5 rounded-2xl bg-slate-50 border border-slate-100 mb-5 text-[11px] text-slate-700">
        <div class="flex items-center gap-2 font-medium">
          <i class="pi pi-check-circle text-emerald-600 text-xs"></i>
          <span>Buat Kampanye Tanpa Batas</span>
        </div>
        <div class="flex items-center gap-2 font-medium">
          <i class="pi pi-check-circle text-emerald-600 text-xs"></i>
          <span>Kelola Paket Reward Tier</span>
        </div>
        <div class="flex items-center gap-2 font-medium">
          <i class="pi pi-check-circle text-emerald-600 text-xs"></i>
          <span>Pencairan Dana Otomatis</span>
        </div>
        <div class="flex items-center gap-2 font-medium">
          <i class="pi pi-check-circle text-emerald-600 text-xs"></i>
          <span>Kabar Proyek ke Seluruh Backer</span>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Alasan Ingin Menjadi Kreator <span class="text-rose-500">*</span>
            </label>
            <span class="text-[10px] text-slate-400 font-medium">
              {{ reason.length }}/500 karakter
            </span>
          </div>
          <textarea
            v-model="reason"
            maxlength="500"
            rows="4"
            placeholder="Ceritakan secara singkat rencana proyek atau inovasi yang ingin Anda danai di CoFund..."
            class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition leading-relaxed"
          ></textarea>
        </div>

        <!-- Error message -->
        <div
          v-if="errorMessage"
          class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2"
        >
          <i class="pi pi-exclamation-circle text-rose-500"></i>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3 pt-2">
          <button
            type="button"
            @click="handleClose"
            class="flex-1 py-3.5 px-4 rounded-2xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="flex-2 py-3.5 px-6 rounded-2xl text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
            <i v-else class="pi pi-bolt text-xs text-amber-300"></i>
            <span>{{ isSubmitting ? 'Memproses Upgrade...' : 'Upgrade Sekarang' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
