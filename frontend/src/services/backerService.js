import api from './api'

export const backerService = {
  getStatistics: () => api.get('/backer/statistics'),
}
