import { ref } from 'vue'
import { useAdminStore } from '@/stores/useAdminStore'

export function useAdmin() {
  const adminStore = useAdminStore()
  const userSearch = ref('')
  const userRole = ref('')
  const userSuspended = ref('')

  async function loadUsers(page = 1) {
    const params = { page, per_page: 10 }
    if (userSearch.value) params.search = userSearch.value
    if (userRole.value) params.role = userRole.value
    if (userSuspended.value !== '') params.is_suspended = userSuspended.value
    return await adminStore.fetchUsers(params)
  }

  async function loadReviewQueue(page = 1) {
    return await adminStore.fetchReviewCampaigns({ page })
  }

  async function loadDashboardStats() {
    return await adminStore.fetchStatistics()
  }

  return {
    adminStore,
    userSearch,
    userRole,
    userSuspended,
    loadUsers,
    loadReviewQueue,
    loadDashboardStats,
  }
}
