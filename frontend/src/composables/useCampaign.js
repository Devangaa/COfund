import { ref } from 'vue'
import { useCampaignStore } from '@/stores/useCampaignStore'

export function useCampaign() {
  const campaignStore = useCampaignStore()
  const searchQuery = ref('')
  const selectedCategory = ref('')
  const selectedSort = ref('latest')

  async function loadCampaigns(page = 1) {
    const params = {
      page,
      per_page: 9,
    }
    if (searchQuery.value) params.search = searchQuery.value
    if (selectedCategory.value) params.category = selectedCategory.value
    if (selectedSort.value) params.sort = selectedSort.value

    return await campaignStore.fetchCampaigns(params)
  }

  async function loadCampaignDetail(slug) {
    return await campaignStore.fetchCampaignBySlug(slug)
  }

  return {
    campaignStore,
    searchQuery,
    selectedCategory,
    selectedSort,
    loadCampaigns,
    loadCampaignDetail,
  }
}
