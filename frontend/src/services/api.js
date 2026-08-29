import axios from 'axios'
import { useToast } from '@/composables/useToast'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
  },
})

// Request Interceptor: Attach Sanctum Bearer Token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('cofund_token') || localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response Interceptor: Handle Global Errors & Notifications
api.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    // Avoid toast spam if toast instance is unavailable during SSR / test
    let toast
    try {
      toast = useToast()
    } catch {
      // ignore
    }

    if (error.response) {
      const { status, data } = error.response
      const message = data?.message || 'Terjadi kesalahan pada sistem.'

      if (status === 401) {
        // Clear stored token on 401 if not already on login/register page
        localStorage.removeItem('cofund_token')
        localStorage.removeItem('token')
        localStorage.removeItem('cofund_user')
        if (toast && !window.location.pathname.includes('/login')) {
          toast.error('Sesi Anda telah berakhir. Silakan login kembali.')
        }
      } else if (status === 403) {
        if (toast) {
          toast.warning(message || 'Akses ditolak. Pastikan email telah terverifikasi.')
        }
      } else if (status === 409) {
        if (toast) {
          toast.error(message)
        }
      } else if (status === 422) {
        // Form validation errors
        if (data?.errors && typeof data.errors === 'object') {
          const firstKey = Object.keys(data.errors)[0]
          const firstMsg = Array.isArray(data.errors[firstKey]) ? data.errors[firstKey][0] : data.errors[firstKey]
          if (toast) {
            toast.error(firstMsg || message)
          }
        } else if (toast) {
          toast.error(message)
        }
      } else if (status === 429) {
        if (toast) {
          toast.warning('Terlalu banyak permintaan. Silakan tunggu beberapa saat.')
        }
      } else if (status >= 500) {
        if (toast) {
          toast.error('Server sedang mengalami kendala. Silakan coba lagi nanti.')
        }
      }
    } else if (error.request) {
      if (toast) {
        toast.error('Tidak dapat terhubung ke server backend.')
      }
    }

    return Promise.reject(error)
  }
)

export default api
