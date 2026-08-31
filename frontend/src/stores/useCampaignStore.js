import { defineStore } from 'pinia'
import { ref } from 'vue'
import { campaignService } from '@/services/campaignService'
import { useToast } from '@/composables/useToast'

export const useCampaignStore = defineStore('campaign', () => {
  const toast = useToast()

  const campaigns = ref([])
  const creatorCampaigns = ref([])
  const currentCampaign = ref(null)
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
  })
  const isLoading = ref(false)
  const isSubmitting = ref(false)

  // Fetch Public / Filtered Campaigns
  async function fetchCampaigns(params = {}) {
    isLoading.value = true
    try {
      const response = await campaignService.getAll(params)
      const data = response.data
      campaigns.value = data.data || []
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
      campaigns.value = []
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch Creator's Own Campaigns (scope=mine)
  async function fetchCreatorCampaigns(params = {}) {
    isLoading.value = true
    try {
      const response = await campaignService.getAll({ scope: 'mine', ...params })
      const data = response.data
      creatorCampaigns.value = data.data || []
      return data
    } catch (error) {
      creatorCampaigns.value = []
      return { success: false, error }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch Single Campaign Detail by Slug
  async function fetchCampaignBySlug(slug) {
    isLoading.value = true
    currentCampaign.value = null
    try {
      const response = await campaignService.getBySlug(slug)
      const data = response.data.data || response.data
      currentCampaign.value = data
      return data
    } catch {
      currentCampaign.value = null
      return null
    } finally {
      isLoading.value = false
    }
  }

  // Create Campaign
  async function createCampaign(formData) {
    isSubmitting.value = true
    try {
      const response = await campaignService.create(formData)
      const data = response.data
      toast.success(data.message || 'Kampanye berhasil dibuat sebagai draft!')
      return { success: true, data: data.data || data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isSubmitting.value = false
    }
  }

  // Update Draft Campaign
  async function updateCampaign(slug, updateData) {
    isSubmitting.value = true
    try {
      const response = await campaignService.update(slug, updateData)
      const data = response.data
      toast.success(data.message || 'Kampanye berhasil diperbarui!')
      return { success: true, data: data.data || data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isSubmitting.value = false
    }
  }

  // Submit Draft to Review
  async function submitForReview(slug) {
    isSubmitting.value = true
    try {
      const response = await campaignService.submitReview(slug)
      const data = response.data
      toast.success(data.message || 'Kampanye berhasil dikirim untuk peninjauan admin!')
      return { success: true, data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isSubmitting.value = false
    }
  }

  // Delete Draft Campaign
  async function deleteCampaign(slug) {
    isSubmitting.value = true
    try {
      const response = await campaignService.delete(slug)
      toast.success(response.data?.message || 'Kampanye berhasil dihapus.')
      creatorCampaigns.value = creatorCampaigns.value.filter((c) => c.slug !== slug)
      return { success: true }
    } catch (error) {
      return { success: false, error }
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    campaigns,
    creatorCampaigns,
    currentCampaign,
    pagination,
    isLoading,
    isSubmitting,
    fetchCampaigns,
    fetchCreatorCampaigns,
    fetchCampaignBySlug,
    createCampaign,
    updateCampaign,
    submitForReview,
    deleteCampaign,
  }
})
