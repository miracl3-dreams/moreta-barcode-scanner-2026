import { useEffect, useMemo, useState } from 'react'

export type PortalNavItem = {
  caption: string
  path: string
}

export type PortalNavGroup = {
  caption: string
  items: PortalNavItem[]
}

type SideMenuProps = {
  groups: PortalNavGroup[]
  selectedPath: string | null
  dashboardActive: boolean
  collapsed: boolean
  query: string
  onQueryChange: (value: string) => void
  onDashboard: () => void
  onSelect: (item: PortalNavItem) => void
}

function Chevron({ open }: { open: boolean }) {
  return (
    <svg
      viewBox="0 0 20 20"
      className={`size-4 shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
      fill="currentColor"
    >
      <path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" />
    </svg>
  )
}

function NavIcon({ name }: { name: string }) {
  const common = 'size-4 shrink-0'
  if (name === 'dashboard') {
    return (
      <svg viewBox="0 0 24 24" className={common} fill="none" stroke="currentColor" strokeWidth="1.8">
        <rect x="3" y="3" width="8" height="8" rx="1.5" />
        <rect x="13" y="3" width="8" height="5" rx="1.5" />
        <rect x="13" y="10" width="8" height="11" rx="1.5" />
        <rect x="3" y="13" width="8" height="8" rx="1.5" />
      </svg>
    )
  }
  if (name === 'entry') {
    return (
      <svg viewBox="0 0 24 24" className={common} fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z" />
      </svg>
    )
  }
  return (
    <svg viewBox="0 0 24 24" className={common} fill="none" stroke="currentColor" strokeWidth="1.8">
      <path d="M7 4h8l4 4v12H7z" />
      <path d="M15 4v4h4M9 13h6M9 17h4" />
    </svg>
  )
}

function iconForGroup(caption: string): string {
  const text = caption.toLowerCase()
  if (text.includes('data') || text.includes('entry')) return 'entry'
  return 'grid'
}

export function SideMenu({
  groups,
  selectedPath,
  dashboardActive,
  collapsed,
  query,
  onQueryChange,
  onDashboard,
  onSelect,
}: SideMenuProps) {
  const [openIdx, setOpenIdx] = useState(0)

  useEffect(() => {
    if (!selectedPath) {
      return
    }
    const index = groups.findIndex((group) => group.items.some((item) => selectedPath.startsWith(item.path)))
    if (index >= 0) {
      setOpenIdx(index)
    }
  }, [selectedPath, groups])

  const visibleGroups = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q) {
      return groups
    }
    return groups
      .map((group) => ({
        ...group,
        items: group.items.filter((item) => item.caption.toLowerCase().includes(q)),
      }))
      .filter((group) => group.items.length > 0 || group.caption.toLowerCase().includes(q))
  }, [groups, query])

  return (
    <div className="flex h-full flex-col">
      <div className={`shrink-0 ${collapsed ? 'px-2 pt-3' : 'px-4 pt-4'}`}>
        {collapsed ? (
          <div className="flex justify-center text-slate-400">
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8">
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-3-3" />
            </svg>
          </div>
        ) : (
          <label className="relative block">
            <svg
              viewBox="0 0 24 24"
              className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
            >
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-3-3" />
            </svg>
            <input
              type="search"
              value={query}
              onChange={(e) => onQueryChange(e.target.value)}
              placeholder="Search"
              className="w-full rounded-lg border-0 bg-slate-100 py-2 pr-3 pl-9 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-app-header/20"
            />
          </label>
        )}
      </div>

      <nav className="mt-3 min-h-0 flex-1 overflow-auto px-2 pb-3">
        <button
          type="button"
          title="Dashboard"
          onClick={onDashboard}
          className={`mb-1 flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium ${
            dashboardActive ? 'bg-app-header text-white' : 'text-slate-600 hover:bg-slate-100'
          } ${collapsed ? 'justify-center px-2' : ''}`}
        >
          <NavIcon name="dashboard" />
          {collapsed ? null : <span>Dashboard</span>}
        </button>

        {visibleGroups.map((group, index) => {
          const open = query.trim() !== '' || openIdx === index
          return (
            <section key={`${group.caption}-${index}`} className="mt-0.5">
              <button
                type="button"
                title={group.caption}
                onClick={() => setOpenIdx(open && query.trim() === '' ? -1 : index)}
                className={`flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-100 ${
                  collapsed ? 'justify-center px-2' : ''
                }`}
              >
                <NavIcon name={iconForGroup(group.caption)} />
                {collapsed ? null : (
                  <>
                    <span className="min-w-0 flex-1 truncate text-left">{group.caption}</span>
                    <Chevron open={open} />
                  </>
                )}
              </button>
              {open && !collapsed ? (
                <div className="mb-1 ml-4 border-l border-slate-200 pl-2">
                  {group.items.map((item) => {
                    const active = Boolean(selectedPath && selectedPath.startsWith(item.path))
                    return (
                      <button
                        key={item.path}
                        type="button"
                        title={item.caption}
                        onClick={() => onSelect(item)}
                        className={`flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm ${
                          active
                            ? 'bg-app-header font-medium text-white'
                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800'
                        }`}
                      >
                        <NavIcon name="file" />
                        <span className="truncate">{item.caption}</span>
                      </button>
                    )
                  })}
                </div>
              ) : null}
            </section>
          )
        })}
      </nav>
    </div>
  )
}
