import { useEffect, useRef, useState } from 'react'
import { useTheme } from '../theme-context'
import type { ColorMode } from '../theme'

const OPTIONS: { mode: ColorMode; label: string; swatch: string }[] = [
  { mode: 'light', label: 'Light', swatch: '#f8fafc' },
  { mode: 'dark', label: 'Dark', swatch: '#152033' },
  { mode: 'blue', label: 'Blue', swatch: '#2563eb' },
  { mode: 'pink', label: 'Pink', swatch: '#db2777' },
]

export function DarkModeToggle() {
  const { mode, isDark, setMode } = useTheme()
  const [open, setOpen] = useState(false)
  const rootRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) {
      return
    }
    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  const active = OPTIONS.find((option) => option.mode === mode) ?? OPTIONS[0]

  return (
    <div ref={rootRef} className="relative shrink-0">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-label="Color theme"
        aria-haspopup="menu"
        aria-expanded={open}
        title={`Theme: ${active.label}`}
        className="flex size-10 cursor-pointer items-center justify-center rounded-lg text-brand-navy hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
      >
        {isDark ? (
          <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="12" cy="12" r="4" />
            <path
              strokeLinecap="round"
              d="M12 3v1.5M12 19.5V21M4.93 4.93l1.06 1.06M18.01 18.01l1.06 1.06M3 12h1.5M19.5 12H21M4.93 19.07l1.06-1.06M18.01 5.99l1.06-1.06"
            />
          </svg>
        ) : (
          <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M20.5 14.2A8.2 8.2 0 0 1 9.8 3.5 7.2 7.2 0 1 0 20.5 14.2Z"
            />
          </svg>
        )}
        {mode !== 'light' && mode !== 'dark' ? (
          <span
            className="absolute right-1.5 bottom-1.5 size-2 rounded-full ring-1 ring-white"
            style={{ backgroundColor: active.swatch }}
            aria-hidden
          />
        ) : null}
      </button>

      {open ? (
        <div
          role="menu"
          aria-label="Color theme"
          className="absolute right-0 z-50 mt-1 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-slate-800 shadow-lg dark:border-slate-600 dark:bg-[#152033] dark:text-white"
        >
          {OPTIONS.map((option) => {
            const selected = option.mode === mode
            return (
              <button
                key={option.mode}
                type="button"
                role="menuitemradio"
                aria-checked={selected}
                onClick={() => {
                  setMode(option.mode)
                  setOpen(false)
                }}
                className={`flex w-full cursor-pointer items-center gap-2.5 px-3 py-2 text-left text-sm ${
                  selected
                    ? 'bg-slate-100 font-medium text-slate-900 dark:bg-[#243352] dark:text-white'
                    : 'text-slate-700 hover:bg-slate-50 dark:text-white dark:hover:bg-[#243352]'
                }`}
              >
                <span
                  className="size-3.5 shrink-0 rounded-full ring-1 ring-slate-300"
                  style={{ backgroundColor: option.swatch }}
                  aria-hidden
                />
                {option.label}
              </button>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
