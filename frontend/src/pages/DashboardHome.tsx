import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth'
import { PageHeading } from '../components/PageHeading'
import { usePageTitle } from '../lib/pageTitle'

export function DashboardHome() {
  usePageTitle('Dashboard')
  const { user } = useAuth()
  const firstName = (user?.usrname || user?.usrcde || 'there').split(' ')[0]
  const company = user?.comdsc || 'MORETA SHIPPING LINES, INC.'

  return (
    <section>
      <PageHeading
        title="Operations"
        description={`${company}. ${firstName}, choose a gate task to continue.`}
      />

      <div className="mt-6 grid gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4">
        <ModuleCard
          to="/data-entry/scanner"
          title="Barcode Scanner"
          hint="Scan container barcode and capture damage sketch"
          icon={
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.7">
              <rect x="5" y="3" width="14" height="18" rx="2" />
              <path d="M8 7h8M8 11h8M8 15h5" />
            </svg>
          }
        />
        <ModuleCard
          to="/data-entry/eir-signing"
          title="EIR Signing"
          hint="Upload driver / shipper signature for pending EIRs"
          icon={
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.7">
              <path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z" />
            </svg>
          }
        />
      </div>
    </section>
  )
}

function ModuleCard({
  to,
  title,
  hint,
  icon,
}: {
  to: string
  title: string
  hint: string
  icon: ReactNode
}) {
  return (
    <Link
      to={to}
      className="flex min-h-[4.5rem] flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:ring-brand-navy/30 sm:min-h-0 sm:p-5"
    >
      <div className="flex items-start justify-between gap-3">
        <p className="text-sm font-semibold text-slate-500">DATA ENTRY</p>
        <span className="shrink-0 rounded-lg bg-brand-navy/10 p-1.5 text-brand-navy">{icon}</span>
      </div>
      <p className="mt-3 text-2xl font-bold tracking-tight text-brand-navy sm:mt-4">{title}</p>
      <p className="mt-2 text-sm leading-snug text-slate-400">{hint}</p>
    </Link>
  )
}
