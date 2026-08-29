import { ref } from 'vue'
import { useWalletStore } from '@/stores/useWalletStore'

export function useWallet() {
  const walletStore = useWalletStore()
  const isDepositModalOpen = ref(false)
  const isWithdrawModalOpen = ref(false)
  const filterType = ref('')
  const filterStatus = ref('')

  function openDepositModal() {
    isDepositModalOpen.value = true
  }

  function closeDepositModal() {
    isDepositModalOpen.value = false
  }

  function openWithdrawModal() {
    isWithdrawModalOpen.value = true
  }

  function closeWithdrawModal() {
    isWithdrawModalOpen.value = false
  }

  async function loadTransactions(page = 1) {
    const params = { page, per_page: 10 }
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    return await walletStore.fetchTransactions(params)
  }

  async function handleDeposit(amount) {
    const res = await walletStore.deposit(amount)
    if (res.success) {
      closeDepositModal()
    }
    return res
  }

  async function handleWithdraw(amount) {
    const res = await walletStore.withdraw(amount)
    if (res.success) {
      closeWithdrawModal()
    }
    return res
  }

  return {
    walletStore,
    isDepositModalOpen,
    isWithdrawModalOpen,
    filterType,
    filterStatus,
    openDepositModal,
    closeDepositModal,
    openWithdrawModal,
    closeWithdrawModal,
    loadTransactions,
    handleDeposit,
    handleWithdraw,
  }
}
