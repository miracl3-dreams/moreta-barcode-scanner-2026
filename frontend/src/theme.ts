export const COLOR_MODE_KEY = 'moreta-color-mode'
/** @deprecated kept so old localStorage values still migrate */
const LEGACY_THEME_KEY = 'moreta-theme'

export type ColorMode = 'light' | 'dark' | 'blue' | 'pink'

export const COLOR_MODES: ColorMode[] = ['light', 'dark', 'blue', 'pink']

export function isColorMode(value: string | null): value is ColorMode {
  return value === 'light' || value === 'dark' || value === 'blue' || value === 'pink'
}

export function isDarkColorMode(mode: ColorMode): boolean {
  return mode !== 'light'
}

export function getStoredColorMode(): ColorMode {
  try {
    const stored = localStorage.getItem(COLOR_MODE_KEY)
    if (isColorMode(stored)) {
      return stored
    }
    const legacy = localStorage.getItem(LEGACY_THEME_KEY)
    if (legacy === 'midnight' || legacy === 'dark') {
      return 'dark'
    }
    if (legacy === 'blue' || legacy === 'ocean') {
      return 'blue'
    }
    if (legacy === 'pink' || legacy === 'rose') {
      return 'pink'
    }
  } catch {
    // ignore
  }
  return 'light'
}

export function applyColorMode(mode: ColorMode): void {
  document.documentElement.classList.toggle('dark', isDarkColorMode(mode))
  document.documentElement.setAttribute('data-theme', mode)
  try {
    localStorage.setItem(COLOR_MODE_KEY, mode)
  } catch {
    // ignore
  }
}

export function nextColorMode(mode: ColorMode): ColorMode {
  const index = COLOR_MODES.indexOf(mode)
  return COLOR_MODES[(index + 1) % COLOR_MODES.length]
}
