import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/id'

dayjs.extend(relativeTime)
dayjs.locale('id')

/**
 * Format date to standard Indonesian format (e.g., 28 Agu 2026)
 * @param {string|Date} date
 * @param {string} [format='DD MMM YYYY']
 * @returns {string}
 */
export function formatDate(date, format = 'DD MMM YYYY') {
  if (!date) return '-'
  return dayjs(date).format(format)
}

/**
 * Format date with time (e.g., 28 Agu 2026 14:30)
 * @param {string|Date} date
 * @returns {string}
 */
export function formatDateTime(date) {
  if (!date) return '-'
  return dayjs(date).format('DD MMM YYYY HH:mm')
}

/**
 * Get relative time from now (e.g., '2 jam yang lalu')
 * @param {string|Date} date
 * @returns {string}
 */
export function formatRelativeTime(date) {
  if (!date) return '-'
  return dayjs(date).fromNow()
}

/**
 * Calculate days remaining until deadline
 * @param {string|Date} deadline
 * @returns {{ days: number, isExpired: boolean, text: string }}
 */
export function getDaysRemaining(deadline) {
  if (!deadline) return { days: 0, isExpired: true, text: 'Berakhir' }
  const now = dayjs().startOf('day')
  const target = dayjs(deadline).startOf('day')
  const diff = target.diff(now, 'day')

  if (diff < 0) {
    return { days: 0, isExpired: true, text: 'Telah berakhir' }
  }
  if (diff === 0) {
    return { days: 0, isExpired: false, text: 'Hari ini terakhir' }
  }
  return { days: diff, isExpired: false, text: `${diff} hari lagi` }
}
