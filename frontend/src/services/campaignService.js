import api from './api'

export const campaignService = {
  /**
   * Get list of campaigns with query params (category, sort, search, scope, status, page, per_page, etc.)
   */
  getAll: (params = {}) => api.get('/campaigns', { params }),

  /**
   * Get campaign detail by slug
   */
  getBySlug: (slug) => api.get(`/campaigns/${slug}`),

  /**
   * Create new campaign (requires multipart/form-data for image uploads)
   */
  create: (formData) =>
    api.post('/campaigns', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  /**
   * Update draft campaign
   */
  update: (slug, data) => api.put(`/campaigns/${slug}`, data),

  /**
   * Delete draft campaign
   */
  delete: (slug) => api.delete(`/campaigns/${slug}`),

  /**
   * Submit draft campaign for admin review
   */
  submitReview: (slug) => api.post(`/campaigns/${slug}/submit-review`),
}
