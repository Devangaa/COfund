<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { authService } from '@/services/authService'

const schema = yup.object({
  email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
})

const { handleSubmit, errors } = useForm({
  validationSchema: schema,
})

const { value: email } = useField('email')

const isLoading = ref(false)
const successMessage = ref('')
const serverError = ref('')

const onSubmit = handleSubmit(async (values) => {
  isLoading.value = true
  serverError.value = ''
  successMessage.value = ''
  try {
    const response = await authService.forgotPassword(values)
    successMessage.value = response.data?.message || 'Link reset password telah dikirim ke email Anda.'
  } catch (error) {
    serverError.value = error.response?.data?.message || 'Gagal mengirim link reset password.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="text-center space-y-1">
      <h2 class="text-2xl font-black text-white tracking-tight">Lupa Password</h2>
      <p class="text-xs text-slate-400">
        Masukkan email terdaftar Anda untuk menerima tautan pemulihan kata sandi
      </p>
    </div>

    <!-- Success alert -->
    <div
      v-if="successMessage"
      class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-xs flex items-start gap-2.5"
    >
      <i class="pi pi-check-circle text-emerald-400 mt-0.5"></i>
      <span>{{ successMessage }}</span>
    </div>

    <!-- Error alert -->
    <div
      v-if="serverError"
      class="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center gap-2.5"
    >
      <i class="pi pi-exclamation-circle text-rose-400"></i>
      <span>{{ serverError }}</span>
    </div>

    <form v-if="!successMessage" @submit="onSubmit" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Alamat Email
        </label>
        <div class="relative">
          <i class="pi pi-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
          <input
            type="email"
            v-model="email"
            placeholder="nama@email.com"
            class="w-full pl-11 pr-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
          />
        </div>
        <p v-if="errors.email" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.email }}
        </p>
      </div>

      <div class="pt-2">
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full py-3.5 px-6 rounded-2xl text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-500 transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2"
        >
          <i v-if="isLoading" class="pi pi-spin pi-spinner text-xs"></i>
          <span>{{ isLoading ? 'Mengirim...' : 'Kirim Link Reset' }}</span>
        </button>
      </div>
    </form>

    <div class="text-center pt-2 text-xs text-slate-400">
      Ingat kata sandi Anda?
      <RouterLink to="/login" class="text-sky-400 hover:text-sky-300 font-bold ml-1">
        Kembali ke Login
      </RouterLink>
    </div>
  </div>
</template>
