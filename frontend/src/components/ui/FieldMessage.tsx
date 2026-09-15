import type { ReactNode } from 'react'
import { AlertCircleIcon } from './icons'

type FieldMessageProps = {
  id?: string
  children: ReactNode
}

export function FieldMessage({ id, children }: FieldMessageProps) {
  return (
    <p id={id} role="alert" className="mt-1.5 flex items-start gap-1.5 text-xs text-red-600">
      <AlertCircleIcon className="mt-0.5 size-3.5 shrink-0" />
      <span>{children}</span>
    </p>
  )
}

export function fieldMessageId(fieldId: string) {
  return `${fieldId}-error`
}

export function fieldControlClassWithError(baseClass: string, hasError: boolean) {
  return hasError
    ? `${baseClass} border-red-300 bg-red-50/40 focus:border-red-400 focus:ring-red-100`
    : baseClass
}
