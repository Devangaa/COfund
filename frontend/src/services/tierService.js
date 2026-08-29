import api from './api'

export const tierService = {
  store: (slug, data) => api.post(`/campaigns/${slug}/tiers`, data),
  update: (slug, tierId, data) => api.put(`/campaigns/${slug}/tiers/${tierId}`, data),
  destroyMany: (slug, tierIds) =>
    api.delete(`/campaigns/${slug}/tiers`, { data: { ids: tierIds } }),
}
