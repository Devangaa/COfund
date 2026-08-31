import api from './api'

export const creatorService = {
  getStatistics: (params = {}) => api.get('/creator/statistics', { params }),
}
