export { APP_BASE, MORETA_LOGO, appUrl } from '../../app.config'

/** Public asset path (images in /public) respecting Vite base URL. */
export function publicUrl(path: string): string {
  const normalized = path.startsWith('/') ? path.slice(1) : path
  return `${import.meta.env.BASE_URL}${normalized}`
}
