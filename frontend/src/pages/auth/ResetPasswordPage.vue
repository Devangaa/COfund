<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { authService } from '@/services/authService'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const schema = yup.object({
  token: yup.string().required('Token reset wajib ada'),
  email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
  password: yup.string().required('Password baru wajib diisi').min(8, 'Password minimal 8 karakter'),
  password_confirmation: yup
    .string()
    .required('Konfirmasi password wajib diisi')
    .oneOf([yup.ref('password')], 'Konfirmasi password tidak cocok'),
})

const { handleSubmit, errors, setFieldValue } = useForm({
  validationSchema: schema,
})

const { value: token } = useField('token')
const { value: email } = useField('email')
const { value: password } = useField('password')
const { value: passwordConfirmation } = useField('password_confirmation')

const isLoading = ref(false)
const serverError = ref('')

onMounted(() => {
  if (route.query.token) setFieldValue('token', route.query.token)
  if (route.query.email) setFieldValue('email', route.query.email)
})

const onSubmit = handleSubmit(async (values) => {
  isLoading.value = true
  serverError.value = ''
  try {
    const response = await authService.resetPassword(values)
    toast.success(response.data?.message || 'Password berhasil diperbarui! Silakan masuk.')
    router.push('/login')
  } catch (error) {
    serverError.value = error.response?.data?.message || 'Gagal mereset password. Token mungkin telah kedaluwarsa.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="text-center space-y-1">
      <h2 class="text-2xl font-black text-white tracking-tight">Atur Ulang Password</h2>
      <p class="text-xs text-slate-400">
        Buat password baru yang aman untuk akun CoFund Anda
      </p>
    </div>

    <!-- Error alert -->
    <div
      v-if="serverError"
      class="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center gap-2.5"
    >
      <i class="pi pi-exclamation-circle text-rose-400"></i>
      <span>{{ serverError }}</span>
    </div>

    <form @submit="onSubmit" class="space-y-4">
      <input type="hidden" v-model="token" />

      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Email Terdaftar
        </label>
        <input
          type="email"
          v-model="email"
          placeholder="nama@email.com"
          class="w-full px-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
        />
        <p v-if="errors.email" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.email }}
        </p>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Password Baru
        </label>
        <input
          type="password"
          v-model="password"
          placeholder="Minimal 8 karakter"
          class="w-full px-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
        />
        <p v-if="errors.password" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.password }}
        </p>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
          Ulangi Password Baru
        </label>
        <input
          type="password"
          v-model="passwordConfirmation"
          placeholder="Konfirmasi password baru"
          class="w-full px-4 py-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-slate-800 transition"
        />
        <p v-if="errors.password_confirmation" class="text-xs text-rose-400 font-medium mt-1">
          {{ errors.password_confirmation }}
        </p>
      </div>

      <div class="pt-2">
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full py-3.5 px-6 rounded-2xl text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-500 transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2"
        >
          <i v-if="isLoading" class="pi pi-spin pi-spinner text-xs"></i>
          <span>{{ isLoading ? 'Menyimpan Password...' : 'Simpan Password Baru' }}</span>
        </button>
      </div>
    </form>

    <div class="text-center pt-2 text-xs text-slate-400">
      <RouterLink to="/login" class="text-sky-400 hover:text-sky-300 font-bold">
        Kembali ke Login
      </RouterLink>
    </div>
  </div>
</template>
