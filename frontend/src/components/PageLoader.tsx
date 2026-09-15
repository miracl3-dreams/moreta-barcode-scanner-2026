type SpinnerProps = {
  className?: string
}

export function Spinner({ className = 'size-10 border-slate-200 border-t-app-header' }: SpinnerProps) {
  return (
    <span className={`inline-block animate-spin rounded-full border-2 ${className}`} aria-hidden="true" />
  )
}

/** Spinner preset for primary action buttons. */
export function ButtonSpinner({ className = 'size-4 border-white/30 border-t-white' }: SpinnerProps) {
  return <Spinner className={className} />
}

type PageLoaderProps = {
  label?: string
  overlay?: boolean
  inline?: boolean
  compact?: boolean
  /** When set, overrides the default label visibility rules. */
  showLabel?: boolean
}

export function PageLoader({
  label = 'Loading',
  overlay = false,
  inline = false,
  compact = false,
  showLabel,
}: PageLoaderProps) {
  const visibleLabel = showLabel ?? (overlay || (inline && !compact))
  const shell = overlay
    ? 'fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-[1px]'
    : inline
      ? compact
        ? 'flex items-center justify-center py-8'
        : 'flex items-center justify-center py-16'
      : 'flex min-h-screen items-center justify-center bg-stone-50'

  return (
    <div className={shell} role="status" aria-live="polite" aria-busy="true">
      <div className={`flex flex-col items-center ${compact ? 'gap-2' : 'gap-3'}`}>
        <Spinner className={compact ? 'size-8 border-slate-200 border-t-app-header' : 'size-10 border-slate-200 border-t-app-header'} />
        {visibleLabel ? (
          <p className={`font-medium text-slate-500 ${compact ? 'text-xs' : 'text-sm'}`}>{label}</p>
        ) : (
          <span className="sr-only">{label}</span>
        )}
      </div>
    </div>
  )
}

/** Compact inline loader for panels, modals, and table areas. */
export function LoadingInline({ label = 'Loading' }: { label?: string }) {
  return <PageLoader inline compact label={label} showLabel />
}
