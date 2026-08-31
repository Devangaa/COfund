import api from './api'

export const backingService = {
  /**
   * Back a campaign
   * @param {string} slug
   * @param {Object} data { amount: number, tier_id?: number }
   */
  store: (slug, data) => api.post(`/campaigns/${slug}/back`, data),

  /**
   * Get authenticated user's backings
   */
  getMyBackings: (params = {}) => api.get('/backings', { params }),

  /**
   * Get public backers list of a campaign
   */
  getByCampaign: (slug, params = {}) => api.get(`/campaigns/${slug}/backings`, { params }),
}
