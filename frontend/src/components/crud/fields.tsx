import * as Label from '@radix-ui/react-label'
import type { InputHTMLAttributes, ReactNode } from 'react'
import { FieldMessage, fieldControlClassWithError, fieldMessageId } from '../ui/FieldMessage'

export const fieldControlClass =
  'w-full min-h-11 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-800 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 sm:min-h-10 sm:text-sm'

/** Same control, plus the gap used when the label sits above it. */
export const fieldInputClass = `mt-1.5 ${fieldControlClass}`

export function FieldLabel({
  htmlFor,
  required,
  children,
}: {
  htmlFor?: string
  required?: boolean
  children: ReactNode
}) {
  return (
    <Label.Root htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
      {children}
      {required ? <span className="text-red-500"> *</span> : null}
    </Label.Root>
  )
}

export function TextField({
  id,
  label,
  required,
  error,
  hint,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & {
  label: string
  required?: boolean
  error?: string
  hint?: string
}) {
  const messageId = id ? fieldMessageId(id) : undefined

  return (
    <div>
      <FieldLabel htmlFor={id} required={required}>
        {label}
      </FieldLabel>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={error && messageId ? messageId : undefined}
        className={fieldControlClassWithError(fieldInputClass, Boolean(error))}
        {...props}
      />
      {hint && !error ? <p className="mt-1 text-xs text-slate-400">{hint}</p> : null}
      {error && messageId ? <FieldMessage id={messageId}>{error}</FieldMessage> : null}
    </div>
  )
}

export function CheckboxField({
  id,
  label,
  checked,
  onChange,
}: {
  id: string
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
}) {
  return (
    <label htmlFor={id} className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
      <input
        id={id}
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="size-4 cursor-pointer rounded accent-app-header"
      />
      {label}
    </label>
  )
}

export function SelectField({
  id,
  label,
  required,
  error,
  value,
  onChange,
  options,
  placeholder,
  disabled,
}: {
  id: string
  label: string
  required?: boolean
  error?: string
  value: string
  onChange: (value: string) => void
  options: { value: string; label: string }[]
  placeholder?: string
  disabled?: boolean
}) {
  const messageId = fieldMessageId(id)

  return (
    <div>
      <FieldLabel htmlFor={id} required={required}>
        {label}
      </FieldLabel>
      <select
        id={id}
        value={value}
        disabled={disabled}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? messageId : undefined}
        onChange={(e) => onChange(e.target.value)}
        className={`${fieldControlClassWithError(fieldInputClass, Boolean(error))} disabled:cursor-not-allowed disabled:bg-slate-100`}
      >
        <option value="">{placeholder || 'Select'}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? <FieldMessage id={messageId}>{error}</FieldMessage> : null}
    </div>
  )
}

export function TextAreaField({
  id,
  label,
  required,
  error,
  value,
  onChange,
  maxLength,
}: {
  id: string
  label: string
  required?: boolean
  error?: string
  value: string
  onChange: (value: string) => void
  maxLength?: number
}) {
  const messageId = fieldMessageId(id)

  return (
    <div>
      <FieldLabel htmlFor={id} required={required}>
        {label}
      </FieldLabel>
      <textarea
        id={id}
        value={value}
        maxLength={maxLength}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? messageId : undefined}
        onChange={(e) => onChange(e.target.value)}
        rows={3}
        className={fieldControlClassWithError(fieldInputClass, Boolean(error))}
      />
      {error ? <FieldMessage id={messageId}>{error}</FieldMessage> : null}
    </div>
  )
}
