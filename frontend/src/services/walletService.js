import api from './api'

export const walletService = {
  /**
   * Deposit virtual funds into user wallet
   * @param {Object} data { amount: number }
   */
  deposit: (data) => api.post('/wallet/deposit', data),

  /**
   * Withdraw funds from user wallet
   * @param {Object} data { amount: number }
   */
  withdraw: (data) => api.post('/wallet/withdraw', data),
}
