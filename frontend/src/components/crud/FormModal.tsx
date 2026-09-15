import * as Dialog from '@radix-ui/react-dialog'
import type { FormEvent, ReactNode } from 'react'
import { CloseIcon, SaveIcon } from './icons'
import { ButtonSpinner } from '../PageLoader'
import { FormAlert } from '../ui/FormAlert'

type FormModalProps = {
  open: boolean
  title: string
  submitLabel: string
  submitting?: boolean
  error?: string
  wide?: boolean
  extraWide?: boolean
  onClose: () => void
  onSubmit: (event: FormEvent) => void
  children: ReactNode
}

export function FormModal({
  open,
  title,
  submitLabel,
  submitting = false,
  error,
  wide = false,
  extraWide = false,
  onClose,
  onSubmit,
  children,
}: FormModalProps) {
  const widthClass = extraWide ? 'sm:max-w-6xl' : wide ? 'sm:max-w-2xl' : 'sm:max-w-lg'

  return (
    <Dialog.Root
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen && !submitting) {
          onClose()
        }
      }}
    >
      <Dialog.Portal>
        <Dialog.Overlay className="dialog-overlay fixed inset-0 z-50 bg-black/45 backdrop-blur-[1px]" />
        <Dialog.Content
          className={`dialog-content fixed inset-x-0 bottom-0 z-50 flex max-h-[min(96dvh,100%)] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:inset-auto sm:left-1/2 sm:top-1/2 sm:max-h-[calc(100vh-2rem)] sm:w-[calc(100vw-2rem)] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl ${widthClass}`}
          onPointerDownOutside={(event) => {
            if (submitting) {
              event.preventDefault()
            }
          }}
        >
          <div className="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-4">
            <Dialog.Title className="min-w-0 flex-1 truncate text-base font-bold text-slate-900 sm:text-lg">
              {title}
            </Dialog.Title>
            <Dialog.Close asChild>
              <button
                type="button"
                disabled={submitting}
                onClick={onClose}
                className="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-60 sm:size-8"
                aria-label="Close"
              >
                <CloseIcon />
              </button>
            </Dialog.Close>
          </div>
          <form className="flex min-h-0 flex-1 flex-col" onSubmit={onSubmit}>
            <div className="min-h-0 flex-1 space-y-4 overflow-auto overscroll-contain px-4 py-4 sm:max-h-[70vh] sm:px-6 sm:py-5">
              {error ? <FormAlert>{error}</FormAlert> : null}
              {children}
            </div>
            <div className="flex shrink-0 gap-2 border-t border-slate-100 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:justify-end sm:px-6 sm:py-4">
              <Dialog.Close asChild>
                <button
                  type="button"
                  disabled={submitting}
                  onClick={onClose}
                  className="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60 sm:flex-none"
                >
                  <CloseIcon />
                  Cancel
                </button>
              </Dialog.Close>
              <button
                type="submit"
                disabled={submitting}
                className="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-app-header px-4 py-2.5 text-sm font-semibold text-white hover:bg-app-header-hover disabled:opacity-60 sm:flex-none"
              >
                {submitting ? <ButtonSpinner /> : <SaveIcon />}
                {submitLabel}
              </button>
            </div>
          </form>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  )
}
