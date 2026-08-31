/**
 * Format a number or numeric string to Indonesian Rupiah currency format.
 * Example: 50000 -> "Rp 50.000"
 * @param {number|string} amount
 * @param {boolean} [compact=false]
 * @returns {string}
 */
export function formatCurrency(amount, compact = false) {
  const num = Number(amount) || 0
  if (compact && num >= 1000000000) {
    return `Rp ${(num / 1000000000).toFixed(1).replace('.0', '')} M`
  }
  if (compact && num >= 1000000) {
    return `Rp ${(num / 1000000).toFixed(1).replace('.0', '')} Jt`
  }
  if (compact && num >= 1000) {
    return `Rp ${(num / 1000).toFixed(0)} Rb`
  }
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num)
}
