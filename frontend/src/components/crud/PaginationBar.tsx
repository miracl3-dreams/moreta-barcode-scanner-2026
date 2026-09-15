import { useEffect, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'

const PER_PAGE_OPTIONS = [5, 10, 25, 50]
const COMPACT_PAGE_THRESHOLD = 100

type PaginationBarProps = {
  page: number
  lastPage: number
  perPage: number
  total?: number
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}

export function PaginationBar({
  page,
  lastPage,
  perPage,
  total,
  onPageChange,
  onPerPageChange,
}: PaginationBarProps) {
  const totalPages = Math.max(1, lastPage)
  const current = Math.min(Math.max(1, page), totalPages)
  const atFirst = current <= 1
  const atLast = current >= totalPages
  const compact = totalPages >= COMPACT_PAGE_THRESHOLD
  const [pageInput, setPageInput] = useState(String(current))

  useEffect(() => {
    setPageInput(String(current))
  }, [current])

  function goTo(next: number) {
    const clamped = Math.min(Math.max(1, next), totalPages)
    if (clamped !== page) {
      onPageChange(clamped)
    }
  }

  function commitPageInput() {
    const parsed = Number.parseInt(pageInput.trim(), 10)
    if (Number.isNaN(parsed)) {
      setPageInput(String(current))
      return
    }
    goTo(parsed)
  }

  function onPageInputSubmit(event: FormEvent) {
    event.preventDefault()
    commitPageInput()
  }

  const rangeStart = total !== undefined && total > 0 ? (current - 1) * perPage + 1 : null
  const rangeEnd =
    total !== undefined && total > 0 ? Math.min(current * perPage, total) : null

  return (
    <div className="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div className="min-w-0 text-sm text-slate-600">
        {rangeStart !== null && rangeEnd !== null ? (
          <p>
            Showing{' '}
            <span className="font-medium text-slate-800">
              {formatNumber(rangeStart)}–{formatNumber(rangeEnd)}
            </span>{' '}
            of <span className="font-medium text-slate-800">{formatNumber(total!)}</span>
          </p>
        ) : (
          <p>
            Page <span className="font-medium text-slate-800">{formatNumber(current)}</span> of{' '}
            <span className="font-medium text-slate-800">{formatNumber(totalPages)}</span>
          </p>
        )}
      </div>

      <nav className="flex flex-wrap items-center gap-1.5" aria-label="Pagination">
        {compact ? (
          <>
            <PagerIcon disabled={atFirst} label="First page" onClick={() => goTo(1)}>
              <ChevronsLeftIcon />
            </PagerIcon>
            <PagerIcon disabled={atFirst} label="Previous page" onClick={() => goTo(current - 1)}>
              <ChevronLeftIcon />
            </PagerIcon>

            <form
              onSubmit={onPageInputSubmit}
              className="mx-1 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1"
            >
              <label htmlFor="pagination-page" className="sr-only">
                Page number
              </label>
              <span className="text-xs font-medium text-slate-500">Page</span>
              <input
                id="pagination-page"
                type="text"
                inputMode="numeric"
                pattern="[0-9]*"
                value={pageInput}
                onChange={(event) => setPageInput(event.target.value)}
                onBlur={commitPageInput}
                className="w-[5.5rem] rounded-md border border-slate-200 bg-white px-2 py-0.5 text-center text-sm font-medium text-slate-800 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
              />
              <span className="text-xs text-slate-500">of {formatNumber(totalPages)}</span>
            </form>

            <PagerIcon disabled={atLast} label="Next page" onClick={() => goTo(current + 1)}>
              <ChevronRightIcon />
            </PagerIcon>
            <PagerIcon disabled={atLast} label="Last page" onClick={() => goTo(totalPages)}>
              <ChevronsRightIcon />
            </PagerIcon>
          </>
        ) : (
          <>
            <PagerText disabled={atFirst} onClick={() => goTo(current - 1)}>
              Previous
            </PagerText>
            {visiblePages(current, totalPages).map((item, index) =>
              item === 'ellipsis' ? (
                <span key={`e-${index}`} className="px-1.5 text-slate-400">
                  …
                </span>
              ) : (
                <button
                  key={item}
                  type="button"
                  aria-current={item === current ? 'page' : undefined}
                  onClick={() => goTo(item)}
                  className={
                    item === current
                      ? 'inline-flex min-w-8 cursor-default items-center justify-center rounded-lg bg-app-header px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm'
                      : 'inline-flex min-w-8 cursor-pointer items-center justify-center rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  }
                >
                  {item}
                </button>
              ),
            )}
            <PagerText disabled={atLast} onClick={() => goTo(current + 1)}>
              Next
            </PagerText>
          </>
        )}
      </nav>

      <label className="flex items-center gap-2 text-sm text-slate-600">
        <span className="hidden sm:inline">Rows per page</span>
        <select
          value={perPage}
          onChange={(event) => onPerPageChange(Number(event.target.value))}
          className="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
        >
          {PER_PAGE_OPTIONS.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
      </label>
    </div>
  )
}

function formatNumber(value: number): string {
  return value.toLocaleString()
}

function PagerText({
  disabled,
  onClick,
  children,
}: {
  disabled: boolean
  onClick: () => void
  children: string
}) {
  if (disabled) {
    return (
      <span className="rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-300">{children}</span>
    )
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className="cursor-pointer rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 hover:text-app-header"
    >
      {children}
    </button>
  )
}

function PagerIcon({
  disabled,
  label,
  onClick,
  children,
}: {
  disabled: boolean
  label: string
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
      className="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 hover:text-app-header disabled:cursor-default disabled:border-slate-100 disabled:bg-slate-50 disabled:text-slate-300"
    >
      {children}
    </button>
  )
}

function ChevronLeftIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="m15 6-6 6 6 6" />
    </svg>
  )
}

function ChevronRightIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="m9 6 6 6-6 6" />
    </svg>
  )
}

function ChevronsLeftIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="m11 6-6 6 6 6M17 6l-6 6 6 6" />
    </svg>
  )
}

function ChevronsRightIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="m7 6 6 6-6 6M13 6l6 6-6 6" />
    </svg>
  )
}

function visiblePages(current: number, total: number): Array<number | 'ellipsis'> {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1)
  }

  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)
  const pages: Array<number | 'ellipsis'> = [1]

  if (start > 2) {
    pages.push('ellipsis')
  }

  for (let pageNum = start; pageNum <= end; pageNum += 1) {
    pages.push(pageNum)
  }

  if (end < total - 1) {
    pages.push('ellipsis')
  }

  pages.push(total)
  return pages
}
