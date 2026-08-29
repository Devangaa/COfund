import { defineStore } from 'pinia'
import { ref } from 'vue'
import { walletService } from '@/services/walletService'
import { transactionService } from '@/services/transactionService'
import { useAuthStore } from './useAuthStore'
import { useToast } from '@/composables/useToast'

export const useWalletStore = defineStore('wallet', () => {
  const toast = useToast()
  const authStore = useAuthStore()

  const transactions = ref([])
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
  })
  const isLoading = ref(false)
  const isProcessing = ref(false)

  // Fetch Transaction History
  async function fetchTransactions(params = {}) {
    isLoading.value = true
    try {
      const response = await transactionService.getAll(params)
      const data = response.data
      transactions.value = data.data || []
      if (data.meta?.pagination) {
        pagination.value = {
          currentPage: data.meta.pagination.current_page,
          lastPage: data.meta.pagination.last_page,
          perPage: data.meta.pagination.per_page,
          total: data.meta.pagination.total,
        }
      }
      return data
    } catch (error) {
      transactions.value = []
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Deposit funds
  async function deposit(amount) {
    isProcessing.value = true
    try {
      const response = await walletService.deposit({ amount: Number(amount) })
      const data = response.data
      toast.success(data.message || 'Deposit berhasil ditambahkan ke saldo dompet!')
      // Refresh user profile for updated balance
      await authStore.fetchMe()
      await fetchTransactions({ page: 1 })
      return { success: true, data: data.data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isProcessing.value = false
    }
  }

  // Withdraw funds
  async function withdraw(amount) {
    isProcessing.value = true
    try {
      const response = await walletService.withdraw({ amount: Number(amount) })
      const data = response.data
      toast.success(data.message || 'Penarikan saldo berhasil diproses!')
      // Refresh user profile for updated balance
      await authStore.fetchMe()
      await fetchTransactions({ page: 1 })
      return { success: true, data: data.data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isProcessing.value = false
    }
  }

  return {
    transactions,
    pagination,
    isLoading,
    isProcessing,
    fetchTransactions,
    deposit,
    withdraw,
  }
})
