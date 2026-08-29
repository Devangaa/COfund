import api from './api'

export const transactionService = {
  /**
   * Get user or platform transactions
   * @param {Object} params { type, status, start_date, end_date, user_id, sort, per_page, page }
   */
  getAll: (params = {}) => api.get('/transactions', { params }),
}
