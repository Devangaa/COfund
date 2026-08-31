<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { useAuth } from '@/composables/useAuth'

const { authStore, handleRegister } = useAuth()

const schema = yup.object({
  name: yup.string().required('Nama lengkap wajib diisi').min(3, 'Nama minimal 3 karakter'),
  email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
  password: yup.string().required('Password wajib diisi').min(8, 'Password minimal 8 karakter'),
  password_confirmation: yup
    .string()
    .required('Konfirmasi password wajib diisi')
    .oneOf([yup.ref('password')], 'Konfirmasi password tidak cocok'),
  role: yup.string().default('backer'),
})

const { handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: {
    role: 'backer',
  },
})

const { value: name } = useField('name')
const { value: email } = useField('email')
const { value: password } = useField('password')
const { value: passwordConfirmation } = useField('password_confirmation')
const { value: role } = useField('role')

const showPassword = ref(false)
const serverError = ref('')

const onSubmit = handleSubmit(async (values) => {
  serverError.value = ''
  const result = await handleRegister(values)
  if (!result.success) {
    serverError.value =
      result.error?.response?.data?.message || 'Registrasi gagal. Silakan periksa data Anda.'
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="text-center space-y-1">
      <h2 class="text-2xl font-black text-white tracking-tight">Daftar Akun Baru</h2>
      <p class="text-xs text-slate-400">
        Bergabung dengan ribuan inisiator dan donatur di CoFund
      </p>
    </div>

    <!-- Error alert from server -->
    <div
      v-if="serverError"
      class="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center gap-2.5"
    >
      <i class="pi pi-exclamation-circle text-rose-400"></i>
      <span>{{ serverError }}</span>
    </div>

    <form @submit="onSubmit" class="space-y-4">
      <!-- Name Field -->
      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Nama Lengkap
        </label>
        <div class="relative">
          <i class="pi pi-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
          <input
            type="text"
            v-model="name"
            placeholder="John Doe"
            class="w-full pl-11 pr-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
          />
        </div>
        <p v-if="errors.name" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.name }}
        </p>
      </div>

      <!-- Email Field -->
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

      <!-- Password Field -->
      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Password
        </label>
        <div class="relative">
          <i class="pi pi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
          <input
            :type="showPassword ? 'text' : 'password'"
            v-model="password"
            placeholder="Minimal 8 karakter"
            class="w-full pl-11 pr-11 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 text-xs focus:outline-none"
          >
            <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
          </button>
        </div>
        <p v-if="errors.password" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.password }}
        </p>
      </div>

      <!-- Password Confirmation Field -->
      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Ulangi Password
        </label>
        <div class="relative">
          <i class="pi pi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
          <input
            :type="showPassword ? 'text' : 'password'"
            v-model="passwordConfirmation"
            placeholder="Ketik ulang password Anda"
            class="w-full pl-11 pr-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
          />
        </div>
        <p v-if="errors.password_confirmation" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.password_confirmation }}
        </p>
      </div>

      <!-- Submit CTA -->
      <div class="pt-2">
        <button
          type="submit"
          :disabled="authStore.isLoading"
          class="w-full py-3.5 px-6 rounded-2xl text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-500 transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2"
        >
          <i v-if="authStore.isLoading" class="pi pi-spin pi-spinner text-xs"></i>
          <span>{{ authStore.isLoading ? 'Mendaftarkan Akun...' : 'Daftar Sekarang' }}</span>
        </button>
      </div>
    </form>

    <div class="text-center pt-2 text-xs text-slate-400">
      Sudah memiliki akun?
      <RouterLink to="/login" class="text-sky-400 hover:text-sky-300 font-bold ml-1">
        Masuk di Sini
      </RouterLink>
    </div>
  </div>
</template>
