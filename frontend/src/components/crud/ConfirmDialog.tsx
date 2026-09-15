import * as AlertDialog from '@radix-ui/react-alert-dialog'
import { ButtonSpinner } from '../PageLoader'
import { AlertCircleIcon, InfoCircleIcon } from '../ui/icons'

type ConfirmDialogProps = {
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'default'
  busy?: boolean
  onCancel: () => void
  onConfirm: () => void
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Delete',
  cancelLabel = 'Cancel',
  variant = 'danger',
  busy = false,
  onCancel,
  onConfirm,
}: ConfirmDialogProps) {
  const confirmClass =
    variant === 'danger'
      ? 'bg-red-600 hover:bg-red-700'
      : 'bg-app-header hover:bg-app-header-hover'

  return (
    <AlertDialog.Root
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen && !busy) {
          onCancel()
        }
      }}
    >
      <AlertDialog.Portal>
        <AlertDialog.Overlay className="dialog-overlay fixed inset-0 z-50 bg-black/45 backdrop-blur-[1px]" />
        <AlertDialog.Content className="dialog-content fixed left-1/2 top-1/2 z-50 w-[min(420px,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl bg-white shadow-2xl">
          <div className="flex items-start gap-3 px-6 pt-6">
            <div
              className={`flex size-10 shrink-0 items-center justify-center rounded-full ${
                variant === 'danger' ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-app-header'
              }`}
            >
              {variant === 'danger' ? <AlertCircleIcon className="size-5" /> : <InfoCircleIcon className="size-5" />}
            </div>
            <div className="min-w-0 flex-1">
              <AlertDialog.Title className="text-lg font-bold text-slate-900">{title}</AlertDialog.Title>
              <AlertDialog.Description className="mt-1 text-sm leading-relaxed text-slate-600">
                {message}
              </AlertDialog.Description>
            </div>
          </div>
          <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:px-6 sm:py-4">
            <AlertDialog.Cancel asChild>
              <button
                type="button"
                disabled={busy}
                onClick={onCancel}
                className="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60 sm:flex-none"
              >
                {cancelLabel}
              </button>
            </AlertDialog.Cancel>
            <AlertDialog.Action asChild>
              <button
                type="button"
                disabled={busy}
                onClick={onConfirm}
                className={`inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none ${confirmClass}`}
              >
                {busy ? <ButtonSpinner /> : null}
                {confirmLabel}
              </button>
            </AlertDialog.Action>
          </div>
        </AlertDialog.Content>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  )
}
