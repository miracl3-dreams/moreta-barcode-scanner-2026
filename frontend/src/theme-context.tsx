import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import {
  applyColorMode,
  getStoredColorMode,
  isDarkColorMode,
  nextColorMode,
  type ColorMode,
} from './theme'

type ThemeContextValue = {
  mode: ColorMode
  isDark: boolean
  setMode: (mode: ColorMode) => void
  toggleMode: () => void
}

const ThemeContext = createContext<ThemeContextValue | null>(null)

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ColorMode>(getStoredColorMode)

  useEffect(() => {
    applyColorMode(mode)
  }, [mode])

  const value = useMemo<ThemeContextValue>(
    () => ({
      mode,
      isDark: isDarkColorMode(mode),
      setMode: (next) => {
        setModeState(next)
        applyColorMode(next)
      },
      toggleMode: () => {
        setModeState((current) => {
          const next = nextColorMode(current)
          applyColorMode(next)
          return next
        })
      },
    }),
    [mode],
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext)
  if (!ctx) {
    throw new Error('useTheme must be used inside ThemeProvider')
  }
  return ctx
}
