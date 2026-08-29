<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const isResending = ref(false)
const resendSuccess = ref(false)
const resendError = ref('')

async function handleResend() {
  isResending.value = true
  resendSuccess.value = false
  resendError.value = ''
  try {
    const res = await authStore.resendVerification()
    if (res.success) {
      resendSuccess.value = true
    } else {
      resendError.value = res.error?.response?.data?.message || 'Gagal mengirim ulang email verifikasi.'
    }
  } finally {
    isResending.value = false
  }
}

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await authStore.fetchMe()
  }
})
</script>

<template>
  <div class="space-y-6 text-center max-w-md mx-auto">
    <!-- Icon State -->
    <div class="mx-auto w-16 h-16 rounded-3xl flex items-center justify-center shadow-lg"
      :class="authStore.isEmailVerified ? 'bg-emerald-500/20 text-emerald-400 ring-1 ring-emerald-500/30' : 'bg-blue-500/20 text-sky-400 ring-1 ring-blue-500/30'"
    >
      <i :class="authStore.isEmailVerified ? 'pi pi-check-circle text-3xl' : 'pi pi-envelope text-3xl'"></i>
    </div>

    <!-- Verified State -->
    <div v-if="authStore.isEmailVerified" class="space-y-4">
      <div class="space-y-1">
        <h2 class="text-2xl font-black text-white tracking-tight">Email Terverifikasi!</h2>
        <p class="text-xs text-slate-400">
          Akun CoFund Anda telah aktif sepenuhnya. Anda dapat melakukan donasi, deposit saldo, atau mengelola proyek.
        </p>
      </div>

      <div class="pt-2">
        <RouterLink
          to="/dashboard"
          class="w-full py-3.5 px-6 rounded-2xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 transition shadow-lg shadow-blue-600/25 inline-flex items-center justify-center gap-2"
        >
          <span>Buka Dashboard Saya</span>
          <i class="pi pi-arrow-right text-xs"></i>
        </RouterLink>
      </div>
    </div>

    <!-- Unverified / Pending Notice State -->
    <div v-else class="space-y-4">
      <div class="space-y-1">
        <h2 class="text-2xl font-black text-white tracking-tight">Verifikasi Email Anda</h2>
        <p class="text-xs text-slate-400 leading-relaxed">
          Tautan verifikasi telah kami kirimkan ke alamat email
          <strong class="text-white">{{ authStore.user?.email || 'Anda' }}</strong>. Silakan klik tautan di dalam email tersebut untuk mengaktifkan akun Anda.
        </p>
      </div>

      <!-- Resend Status Alerts -->
      <div
        v-if="resendSuccess"
        class="p-3.5 rounded-2xl bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-xs flex items-center gap-2.5"
      >
        <i class="pi pi-check text-emerald-400"></i>
        <span>Email verifikasi baru berhasil dikirimkan ke kotak masuk Anda.</span>
      </div>

      <div
        v-if="resendError"
        class="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center gap-2.5"
      >
        <i class="pi pi-exclamation-circle text-rose-400"></i>
        <span>{{ resendError }}</span>
      </div>

      <div class="space-y-2 pt-2">
        <button
          type="button"
          @click="handleResend"
          :disabled="isResending"
          class="w-full py-3.5 px-6 rounded-2xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2"
        >
          <i v-if="isResending" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-send text-xs"></i>
          <span>{{ isResending ? 'Mengirim Ulang...' : 'Kirim Ulang Email Verifikasi' }}</span>
        </button>

        <RouterLink
          to="/dashboard"
          class="w-full py-3 px-4 rounded-2xl text-xs font-semibold text-slate-400 hover:text-slate-200 bg-slate-800/40 hover:bg-slate-800 transition inline-block"
        >
          Lanjut ke Dashboard Sementara
        </RouterLink>
      </div>
    </div>
  </div>
</template>
