export const APP_BASE = '/barcode_scanner'
export const MORETA_LOGO = '/images/moreta-logo.png'

/** Build an app URL under /barcode_scanner (for window.location and new tabs). */
export function appUrl(path = ''): string {
  if (!path) {
    return `${APP_BASE}/`
  }

  const normalized = path.startsWith('/') ? path : `/${path}`
  if (normalized === APP_BASE || normalized.startsWith(`${APP_BASE}/`)) {
    return normalized
  }

  return `${APP_BASE}${normalized}`
}
