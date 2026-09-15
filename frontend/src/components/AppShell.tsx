import { useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth'
import { logout as logoutRequest } from '../api'
import { appUrl, MORETA_LOGO, publicUrl } from '../lib/routes'
import { ConfirmDialog } from './crud/ConfirmDialog'
// Dark mode — deferred
// import { DarkModeToggle } from './DarkModeToggle'
import { PageLoader } from './PageLoader'
import { SideMenu } from './SideMenu'
import type { PortalNavGroup } from './SideMenu'
import { useToast } from './ui/ToastProvider'

const MENU_GROUPS: PortalNavGroup[] = [
  {
    caption: 'DATA ENTRY',
    items: [
      { caption: 'Barcode Scanner', path: '/data-entry/scanner' },
      { caption: 'EIR Signing', path: '/data-entry/eir-signing' },
    ],
  },
]

function selectedPathFromLocation(pathname: string): string | null {
  if (pathname.startsWith('/data-entry/eir-signing') || pathname.startsWith('/eir-signing')) {
    return '/data-entry/eir-signing'
  }
  if (pathname.startsWith('/data-entry/scanner') || pathname.startsWith('/scanner')) {
    return '/data-entry/scanner'
  }
  return null
}

export function AppShell({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const location = useLocation()
  const [signingOut, setSigningOut] = useState(false)
  const [logoutOpen, setLogoutOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const [navOpen, setNavOpen] = useState(false)
  const [isDesktop, setIsDesktop] = useState(() => window.matchMedia('(min-width: 1024px)').matches)
  const [query, setQuery] = useState('')
  const dashboardActive = location.pathname === '/dashboard' || location.pathname === '/'
  const selectedPath = selectedPathFromLocation(location.pathname)
  const railed = collapsed && isDesktop

  useEffect(() => {
    const media = window.matchMedia('(min-width: 1024px)')
    function onChange(event: MediaQueryListEvent) {
      setIsDesktop(event.matches)
      if (event.matches) {
        setNavOpen(false)
      }
    }
    media.addEventListener('change', onChange)
    return () => media.removeEventListener('change', onChange)
  }, [])

  useEffect(() => {
    setNavOpen(false)
  }, [location.pathname])

  useEffect(() => {
    if (!navOpen) {
      return
    }
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setNavOpen(false)
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [navOpen])

  async function confirmLogout() {
    setSigningOut(true)
    const loadingId = toast.loading('Logging out…')
    try {
      await logoutRequest()
      toast.update(loadingId, { title: 'Signed out successfully', variant: 'success', duration: 3000 })
      window.location.href = appUrl('/login')
    } catch {
      toast.dismiss(loadingId)
      toast.error('Unable to sign out. Please try again.')
      setSigningOut(false)
      setLogoutOpen(false)
    }
  }

  return (
    <div className="flex h-dvh overflow-hidden bg-brand-gray font-sans text-slate-900">
      {signingOut ? <PageLoader overlay label="Signing out" /> : null}

      <ConfirmDialog
        open={logoutOpen}
        title="Sign out?"
        message="You will be returned to the login screen."
        confirmLabel="Continue"
        cancelLabel="Cancel"
        variant="default"
        busy={signingOut}
        onCancel={() => setLogoutOpen(false)}
        onConfirm={() => void confirmLogout()}
      />

      {navOpen ? (
        <button
          type="button"
          aria-label="Close menu"
          onClick={() => setNavOpen(false)}
          className="fixed inset-0 z-30 cursor-pointer bg-slate-900/50 lg:hidden"
        />
      ) : null}

      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-[280px] max-w-[85vw] shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:relative lg:z-20 lg:h-full lg:max-w-none lg:translate-x-0 lg:transition-[width] ${
          navOpen ? 'translate-x-0' : '-translate-x-full'
        } ${railed ? 'lg:w-[76px]' : 'lg:w-[280px]'}`}
      >
        <button
          type="button"
          title={collapsed ? 'Expand menu' : 'Collapse menu'}
          onClick={() => setCollapsed((value) => !value)}
          className="absolute top-12 right-0 z-20 hidden size-7 translate-x-1/2 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-app-header shadow-sm hover:bg-slate-50 lg:flex"
        >
          <svg
            viewBox="0 0 24 24"
            className={`size-4 transition-transform ${collapsed ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            strokeWidth="2.2"
          >
            <path d="M14 6 8 12l6 6" />
          </svg>
        </button>
        <div className={`flex items-center gap-3 px-4 py-4 ${railed ? 'justify-center px-2' : ''}`}>
          <img
            src={publicUrl(MORETA_LOGO)}
            alt="Moreta Shipping Lines"
            className={`object-contain ${railed ? 'h-10 w-10' : 'h-12 w-auto max-w-[210px]'}`}
          />
          {railed ? null : (
            <div className="min-w-0">
              <p className="truncate text-xs text-slate-500">{user?.comdsc || 'MORETA SHIPPING LINES, INC.'}</p>
            </div>
          )}
          <button
            type="button"
            aria-label="Close menu"
            onClick={() => setNavOpen(false)}
            className="ml-auto flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
          >
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        </div>

        <div className="min-h-0 flex-1">
          <SideMenu
            groups={MENU_GROUPS}
            selectedPath={selectedPath}
            dashboardActive={dashboardActive}
            collapsed={railed}
            query={query}
            onQueryChange={setQuery}
            onDashboard={() => navigate('/dashboard')}
            onSelect={(item) => navigate(item.path)}
          />
        </div>

        <div
          className={`shrink-0 border-t border-slate-100 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] ${
            railed ? 'px-2' : ''
          }`}
        >
          {railed ? (
            <p className="truncate text-center text-[10px] font-semibold text-app-header">
              {(user?.usrcde || user?.usrname || '?').slice(0, 2).toUpperCase()}
            </p>
          ) : (
            <div className="rounded-xl bg-app-header px-3 py-3 text-white">
              <p className="truncate text-sm font-medium">{user?.usrname || user?.usrcde}</p>
              <p className="truncate text-xs text-white/70">{user?.usrlvl || 'User'}</p>
            </div>
          )}
          <button
            type="button"
            id="btn_logout"
            onClick={() => setLogoutOpen(true)}
            className={`mt-3 flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-600 hover:bg-slate-100 ${
              railed ? 'justify-center' : ''
            }`}
          >
            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="1.8">
              <path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2" />
              <path d="M4 12h11M8 8l-4 4 4 4" />
            </svg>
            {railed ? null : <span>Sign Out</span>}
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 sm:px-4">
          <button
            type="button"
            aria-label="Open menu"
            aria-expanded={navOpen}
            onClick={() => setNavOpen(true)}
            className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-brand-navy hover:bg-slate-100 lg:hidden"
          >
            <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
          </button>
          <div className="ml-auto flex min-w-0 items-center gap-2">
            {/* Dark mode — deferred
            <DarkModeToggle />
            */}
            <div
              className="flex min-w-0 max-w-[min(100%,18rem)] items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-slate-700 ring-1 ring-slate-200"
              title={user?.usrcde || undefined}
            >
              <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-white text-brand-navy ring-1 ring-slate-200">
                <svg viewBox="0 0 24 24" className="size-3.5" fill="none" stroke="currentColor" strokeWidth="1.8">
                  <circle cx="12" cy="8" r="3.25" />
                  <path d="M5.5 19.5c1.6-3.2 4-4.5 6.5-4.5s4.9 1.3 6.5 4.5" strokeLinecap="round" />
                </svg>
              </span>
              <p className="min-w-0 truncate text-sm leading-tight">
                <span className="text-slate-500">Current User:</span>{' '}
                <span className="font-semibold tracking-wide text-brand-navy">{user?.usrcde || '—'}</span>
              </p>
            </div>
          </div>
        </header>

        <main className="min-h-0 flex-1 overflow-auto overscroll-contain p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:p-6 md:p-8">
          {children}
        </main>
      </div>
    </div>
  )
}
