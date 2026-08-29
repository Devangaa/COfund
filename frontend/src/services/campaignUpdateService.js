import api from './api'

export const campaignUpdateService = {
  getAll: (slug, params = {}) => api.get(`/campaigns/${slug}/updates`, { params }),
  store: (slug, data) => api.post(`/campaigns/${slug}/updates`, data),
  update: (slug, updateId, data) => api.put(`/campaigns/${slug}/updates/${updateId}`, data),
  delete: (slug, updateId) => api.delete(`/campaigns/${slug}/updates/${updateId}`),
}
