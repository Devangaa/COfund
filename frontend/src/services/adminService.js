import api from './api'

export const adminService = {
  // User Management
  getUsers: (params = {}) => api.get('/admin/users', { params }),
  getUser: (userId) => api.get(`/admin/users/${userId}`),
  suspendUser: (userId) => api.put(`/admin/users/${userId}/suspend`),
  unsuspendUser: (userId) => api.put(`/admin/users/${userId}/unsuspend`),

  // Platform Statistics
  getStatistics: () => api.get('/admin/statistics'),

  // Campaign Approval & Management
  approveCampaign: (slug) => api.put(`/admin/campaigns/${slug}/approve`),
  rejectCampaign: (slug, data) => api.put(`/admin/campaigns/${slug}/reject`, data),
  forceFailCampaign: (slug) => api.put(`/admin/campaigns/${slug}/force-fail`),
}
