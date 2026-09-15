import * as Toast from '@radix-ui/react-toast'
import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { AlertCircleIcon, CheckCircleIcon, InfoCircleIcon, SpinnerIcon } from './icons'
import { FEEDBACK_CLEAR_MS } from '../../lib/feedback'

export type ToastVariant = 'success' | 'error' | 'info' | 'loading'

type ToastItem = {
  id: string
  title: string
  description?: string
  variant: ToastVariant
  duration: number
}

type ToastInput = {
  title: string
  description?: string
  duration?: number
}

type ToastContextValue = {
  success: (input: ToastInput | string) => string
  error: (input: ToastInput | string) => string
  info: (input: ToastInput | string) => string
  loading: (input: ToastInput | string) => string
  dismiss: (id: string) => void
  update: (id: string, input: ToastInput & { variant?: ToastVariant }) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

const variantStyles: Record<ToastVariant, { root: string; icon: string }> = {
  success: {
    root: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    icon: 'text-emerald-600',
  },
  error: {
    root: 'border-red-200 bg-red-50 text-red-900',
    icon: 'text-red-600',
  },
  info: {
    root: 'border-sky-200 bg-sky-50 text-sky-900',
    icon: 'text-sky-600',
  },
  loading: {
    root: 'border-slate-200 bg-white text-slate-900',
    icon: 'text-app-header',
  },
}

function normalizeInput(input: ToastInput | string): ToastInput {
  return typeof input === 'string' ? { title: input } : input
}

function ToastIcon({ variant }: { variant: ToastVariant }) {
  const className = `size-5 shrink-0 ${variantStyles[variant].icon}`
  if (variant === 'success') {
    return <CheckCircleIcon className={className} />
  }
  if (variant === 'error') {
    return <AlertCircleIcon className={className} />
  }
  if (variant === 'loading') {
    return <SpinnerIcon className={className} />
  }
  return <InfoCircleIcon className={className} />
}

let toastCounter = 0

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([])
  const itemsRef = useRef(items)
  itemsRef.current = items

  const dismiss = useCallback((id: string) => {
    setItems((current) => current.filter((item) => item.id !== id))
  }, [])

  const push = useCallback((variant: ToastVariant, input: ToastInput | string) => {
    const normalized = normalizeInput(input)
    const id = `toast-${++toastCounter}`
    const duration = normalized.duration ?? (variant === 'loading' ? 120_000 : FEEDBACK_CLEAR_MS)

    setItems((current) => [
      ...current.filter((item) => item.variant !== 'loading'),
      {
        id,
        title: normalized.title,
        description: normalized.description,
        variant,
        duration,
      },
    ])

    return id
  }, [])

  const update = useCallback((id: string, input: ToastInput & { variant?: ToastVariant }) => {
    setItems((current) =>
      current.map((item) =>
        item.id === id
          ? {
              ...item,
              title: input.title,
              description: input.description ?? item.description,
              variant: input.variant ?? item.variant,
              duration: input.duration ?? item.duration,
            }
          : item,
      ),
    )
  }, [])

  const value = useMemo<ToastContextValue>(
    () => ({
      success: (input) => push('success', input),
      error: (input) => push('error', input),
      info: (input) => push('info', input),
      loading: (input) => push('loading', input),
      dismiss,
      update,
    }),
    [dismiss, push, update],
  )

  return (
    <ToastContext.Provider value={value}>
      <Toast.Provider swipeDirection="up" duration={FEEDBACK_CLEAR_MS}>
        {children}
        {items.map((item) => (
          <Toast.Root
            key={item.id}
            duration={item.duration}
            onOpenChange={(open) => {
              if (!open) {
                dismiss(item.id)
              }
            }}
            className={`toast-root flex w-[min(420px,calc(100vw-2rem))] items-start gap-3 rounded-xl border px-4 py-3 shadow-lg ${variantStyles[item.variant].root}`}
          >
            <ToastIcon variant={item.variant} />
            <div className="min-w-0 flex-1">
              <Toast.Title className="whitespace-pre-line text-sm font-semibold">{item.title}</Toast.Title>
              {item.description ? (
                <Toast.Description className="mt-0.5 whitespace-pre-line text-sm opacity-90">
                  {item.description}
                </Toast.Description>
              ) : null}
            </div>
            <Toast.Close
              aria-label="Dismiss"
              className="cursor-pointer rounded-md p-1 text-current/50 hover:bg-black/5 hover:text-current"
            >
              ×
            </Toast.Close>
          </Toast.Root>
        ))}
        <Toast.Viewport className="fixed top-4 left-1/2 z-[100] flex max-h-screen w-full max-w-[420px] -translate-x-1/2 flex-col gap-2 outline-none" />
      </Toast.Provider>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast must be used inside ToastProvider')
  }
  return ctx
}
