import type { ReactNode } from 'react'
import { AlertCircleIcon, InfoCircleIcon } from './icons'

type FormAlertProps = {
  children: ReactNode
  variant?: 'error' | 'info'
}

export function FormAlert({ children, variant = 'error' }: FormAlertProps) {
  const styles =
    variant === 'error'
      ? 'border-red-200 bg-red-50 text-red-800'
      : 'border-sky-200 bg-sky-50 text-sky-900'

  return (
    <div role="alert" className={`flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm ${styles}`}>
      {variant === 'error' ? (
        <AlertCircleIcon className="mt-0.5 size-4 shrink-0 text-red-600" />
      ) : (
        <InfoCircleIcon className="mt-0.5 size-4 shrink-0 text-sky-600" />
      )}
      <div className="min-w-0 flex-1 whitespace-pre-line">{children}</div>
    </div>
  )
}
