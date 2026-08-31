import api from './api'

export const authService = {
  /**
   * Register a new user
   * @param {Object} data { name, email, password, password_confirmation }
   */
  register: (data) => api.post('/register', data),

  /**
   * Login user
   * @param {Object} data { email, password }
   */
  login: (data) => api.post('/login', data),

  /**
   * Logout user (revoke token)
   */
  logout: () => api.post('/logout'),

  /**
   * Get current authenticated user profile
   */
  getMe: () => api.get('/me'),

  /**
   * Upgrade backer to creator
   * @param {Object} data { reason }
   */
  upgradeToCreator: (data) => api.post('/upgrade-to-creator', data),

  /**
   * Resend email verification notification
   */
  resendVerification: () => api.post('/email/resend'),

  /**
   * Request password reset link
   * @param {Object} data { email }
   */
  forgotPassword: (data) => api.post('/forgot-password', data),

  /**
   * Reset password using token
   * @param {Object} data { token, email, password, password_confirmation }
   */
  resetPassword: (data) => api.post('/reset-password', data),
}
