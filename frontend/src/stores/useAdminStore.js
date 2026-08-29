import { defineStore } from 'pinia'
import { ref } from 'vue'
import { adminService } from '@/services/adminService'
import { campaignService } from '@/services/campaignService'
import { useToast } from '@/composables/useToast'

export const useAdminStore = defineStore('admin', () => {
  const toast = useToast()

  const statistics = ref(null)
  const users = ref([])
  const reviewCampaigns = ref([])
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
  })
  const isLoading = ref(false)
  const isActionLoading = ref(false)

  // Fetch Platform Statistics
  async function fetchStatistics() {
    isLoading.value = true
    try {
      const response = await adminService.getStatistics()
      statistics.value = response.data.data || response.data
      return statistics.value
    } catch (error) {
      statistics.value = null
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch Users List
  async function fetchUsers(params = {}) {
    isLoading.value = true
    try {
      const response = await adminService.getUsers(params)
      const data = response.data
      users.value = data.data || []
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
      users.value = []
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Suspend User
  async function suspendUser(userId) {
    isActionLoading.value = true
    try {
      const response = await adminService.suspendUser(userId)
      toast.success(response.data?.message || 'Akun pengguna berhasil ditangguhkan.')
      // Update in local users array
      const user = users.value.find((u) => u.id === userId)
      if (user) user.is_suspended = true
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isActionLoading.value = false
    }
  }

  // Unsuspend User
  async function unsuspendUser(userId) {
    isActionLoading.value = true
    try {
      const response = await adminService.unsuspendUser(userId)
      toast.success(response.data?.message || 'Akun pengguna berhasil diaktifkan kembali.')
      // Update in local users array
      const user = users.value.find((u) => u.id === userId)
      if (user) user.is_suspended = false
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isActionLoading.value = false
    }
  }

  // Fetch Campaigns in Review status
  async function fetchReviewCampaigns(params = {}) {
    isLoading.value = true
    try {
      const response = await campaignService.getAll({ status: 'review', ...params })
      const data = response.data
      reviewCampaigns.value = data.data || []
      return data
    } catch (error) {
      reviewCampaigns.value = []
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Approve Campaign
  async function approveCampaign(slug) {
    isActionLoading.value = true
    try {
      const response = await adminService.approveCampaign(slug)
      toast.success(response.data?.message || 'Kampanye telah disetujui dan kini aktif!')
      reviewCampaigns.value = reviewCampaigns.value.filter((c) => c.slug !== slug)
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isActionLoading.value = false
    }
  }

  // Reject Campaign
  async function rejectCampaign(slug, rejectionNote) {
    isActionLoading.value = true
    try {
      const response = await adminService.rejectCampaign(slug, {
        rejection_note: rejectionNote,
      })
      toast.info(response.data?.message || 'Kampanye ditolak dan dikembalikan ke status draft inisiator.')
      reviewCampaigns.value = reviewCampaigns.value.filter((c) => c.slug !== slug)
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isActionLoading.value = false
    }
  }

  // Force Fail Campaign
  async function forceFailCampaign(slug) {
    isActionLoading.value = true
    try {
      const response = await adminService.forceFailCampaign(slug)
      toast.warning(response.data?.message || 'Kampanye digagalkan dan refund telah dipicu.')
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isActionLoading.value = false
    }
  }

  return {
    statistics,
    users,
    reviewCampaigns,
    pagination,
    isLoading,
    isActionLoading,
    fetchStatistics,
    fetchUsers,
    suspendUser,
    unsuspendUser,
    fetchReviewCampaigns,
    approveCampaign,
    rejectCampaign,
    forceFailCampaign,
  }
})
