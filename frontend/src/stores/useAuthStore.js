import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/authService'
import { useToast } from '@/composables/useToast'

export const useAuthStore = defineStore('auth', () => {
  const toast = useToast()

  // Initialize state from localStorage if present
  const token = ref(localStorage.getItem('cofund_token') || localStorage.getItem('token') || null)
  const user = ref(
    (() => {
      try {
        const stored = localStorage.getItem('cofund_user')
        return stored ? JSON.parse(stored) : null
      } catch {
        return null
      }
    })()
  )
  const isLoading = ref(false)

  // Getters / Computeds
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const role = computed(() => user.value?.role || 'guest')
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isCreator = computed(() => user.value?.role === 'creator')
  const isBacker = computed(() => user.value?.role === 'backer' || !user.value?.role)
  const isEmailVerified = computed(() => !!user.value?.email_verified_at)
  const isSuspended = computed(() => !!user.value?.is_suspended)
  const balance = computed(() => Number(user.value?.balance) || 0)

  // Actions
  function setAuthData(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('cofund_token', newToken)
    localStorage.setItem('token', newToken)
    localStorage.setItem('cofund_user', JSON.stringify(newUser))
  }

  function clearAuthData() {
    token.value = null
    user.value = null
    localStorage.removeItem('cofund_token')
    localStorage.removeItem('token')
    localStorage.removeItem('cofund_user')
  }

  async function login(credentials) {
    isLoading.value = true
    try {
      const response = await authService.login(credentials)
      const data = response.data
      const authToken = data.token || data.data?.token
      const authUser = data.user || data.data?.user || data.data

      setAuthData(authToken, authUser)
      toast.success(data.message || `Selamat datang kembali, ${authUser.name}!`)
      return { success: true, user: authUser }
    } catch (error) {
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  async function register(formData) {
    isLoading.value = true
    try {
      const response = await authService.register(formData)
      const data = response.data
      const authToken = data.token || data.data?.token
      const authUser = data.user || data.data?.user || data.data

      if (authToken) {
        setAuthData(authToken, authUser)
      }
      toast.success(data.message || 'Registrasi berhasil! Silakan periksa email untuk verifikasi akun.')
      return { success: true, data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return null
    try {
      const response = await authService.getMe()
      const authUser = response.data.data || response.data.user || response.data
      user.value = authUser
      localStorage.setItem('cofund_user', JSON.stringify(authUser))
      return authUser
    } catch {
      clearAuthData()
      return null
    }
  }

  async function logout() {
    try {
      await authService.logout()
    } catch {
      // ignore network failure on logout
    } finally {
      clearAuthData()
      toast.info('Anda telah keluar dari akun.')
    }
  }

  async function resendVerification() {
    isLoading.value = true
    try {
      const response = await authService.resendVerification()
      toast.success(response.data.message || 'Email verifikasi berhasil dikirim ulang.')
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  async function upgradeToCreator(reason) {
    isLoading.value = true
    try {
      const response = await authService.upgradeToCreator({ reason })
      const data = response.data
      const updatedUser = data.user || data.data?.user || data.data

      if (updatedUser) {
        user.value = { ...user.value, ...updatedUser, role: 'creator' }
      } else if (user.value) {
        user.value.role = 'creator'
      }

      localStorage.setItem('cofund_user', JSON.stringify(user.value))
      toast.success(data.message || 'Selamat! Akun Anda berhasil ditingkatkan menjadi Kreator.')
      return { success: true, user: user.value }
    } catch (error) {
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  function updateBalance(newBalance) {
    if (user.value) {
      user.value.balance = newBalance
      localStorage.setItem('cofund_user', JSON.stringify(user.value))
    }
  }

  return {
    token,
    user,
    isLoading,
    isAuthenticated,
    role,
    isAdmin,
    isCreator,
    isBacker,
    isEmailVerified,
    isSuspended,
    balance,
    setAuthData,
    clearAuthData,
    login,
    register,
    fetchMe,
    logout,
    resendVerification,
    upgradeToCreator,
    updateBalance,
  }
})
