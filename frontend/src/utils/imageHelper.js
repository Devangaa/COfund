const DEFAULT_CAMPAIGN_FALLBACK =
  'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?q=80&w=800&auto=format&fit=crop'

/**
 * Resolves a campaign image URL to a valid browser-accessible URL.
 * Handles seeded absolute URLs (Picsum/Unsplash), Laravel Storage URLs with/without port, and relative paths.
 *
 * @param {string|null|undefined} rawUrl
 * @param {string} [fallback]
 * @returns {string}
 */
export function getImageUrl(rawUrl, fallback = DEFAULT_CAMPAIGN_FALLBACK) {
  if (!rawUrl || typeof rawUrl !== 'string' || rawUrl.trim() === '') {
    return fallback
  }

  const trimmed = rawUrl.trim()

  // 1. If it's already an external / absolute URL
  if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('//')) {
    // If backend generated `http://localhost/storage/...` but backend is running on port 8000
    const storageBase = import.meta.env.VITE_STORAGE_URL || 'http://localhost:8000/storage'
    if (trimmed.startsWith('http://localhost/storage/')) {
      return trimmed.replace('http://localhost/storage', storageBase)
    }
    return trimmed
  }

  // 2. If it's a relative storage path (e.g. '/storage/campaigns/xyz.jpg' or 'campaigns/xyz.jpg')
  const storageBase = (import.meta.env.VITE_STORAGE_URL || 'http://localhost:8000/storage').replace(/\/$/, '')
  const cleanPath = trimmed.replace(/^\/?storage\//, '').replace(/^\//, '')

  return `${storageBase}/${cleanPath}`
}

/**
 * Image onError handler to fallback gracefully
 */
export function onImageError(event, fallback = DEFAULT_CAMPAIGN_FALLBACK) {
  if (event?.target && event.target.src !== fallback) {
    event.target.src = fallback
  }
}
