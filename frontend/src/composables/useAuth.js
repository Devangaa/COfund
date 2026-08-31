import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter } from 'vue-router'

export function useAuth() {
  const authStore = useAuthStore()
  const router = useRouter()

  async function handleLogin(credentials, redirectPath = null) {
    const res = await authStore.login(credentials)
    if (res.success) {
      if (redirectPath) {
        router.push(redirectPath)
      } else if (authStore.isAdmin) {
        router.push('/admin/dashboard')
      } else if (authStore.isCreator) {
        router.push('/creator/dashboard')
      } else {
        router.push('/dashboard')
      }
    }
    return res
  }

  async function handleRegister(formData) {
    const res = await authStore.register(formData)
    if (res.success) {
      router.push('/login')
    }
    return res
  }

  async function handleLogout() {
    await authStore.logout()
    router.push('/login')
  }

  return {
    authStore,
    handleLogin,
    handleRegister,
    handleLogout,
  }
}
