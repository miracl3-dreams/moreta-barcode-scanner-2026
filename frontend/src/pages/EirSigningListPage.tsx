import { useNavigate } from 'react-router-dom'
import { fetchPendingEirSignings } from '../api'
import type { EirSigningRow } from '../api'
import { LoadingInline } from '../components/PageLoader'
import { PaginationBar } from '../components/crud/PaginationBar'
import { CloseIcon, SearchIcon } from '../components/crud/icons'
import { usePaginatedLoader, usePaginatedResource } from '../components/crud/usePaginatedResource'
import { FormAlert } from '../components/ui/FormAlert'
import { usePageTitle } from '../lib/pageTitle'

export function EirSigningListPage() {
  usePageTitle('EIR Signing', 'List')
  const navigate = useNavigate()
  const loader = usePaginatedLoader(
    (params) => fetchPendingEirSignings(params.search, params.page, params.perPage, params.sort, params.dir),
    [],
  )
  const list = usePaginatedResource(loader, {
    queryKey: 'eir-signing',
    defaultSort: 'recid',
    defaultDir: 'desc',
    // Signed rows must leave this list as soon as the pad saves.
    staleTime: 0,
    refetchOnMount: 'always',
  })

  return (
    <div className="mx-auto w-full max-w-4xl">
      <h1 className="text-2xl font-bold tracking-tight text-slate-900">EIRs pending signature</h1>
      <p className="mt-1.5 text-sm text-slate-500">
        Unaccepted EIR records awaiting a driver / shipper signature.
      </p>

      <div className="mt-5 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div className="border-b border-slate-100 px-4 py-3">
          <label className="relative block w-full max-w-sm">
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
              <SearchIcon />
            </span>
            <input
              type="search"
              value={list.searchInput}
              onChange={(event) => list.setSearchInput(event.target.value)}
              placeholder="Search EIR no. or container…"
              className="min-h-11 w-full rounded-lg border border-slate-200 bg-white py-2.5 pr-11 pl-9 text-base text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 sm:text-sm [&::-webkit-search-cancel-button]:hidden"
            />
            {list.searchInput.trim() !== '' ? (
              <button
                type="button"
                aria-label="Clear search"
                onClick={() => list.setSearchInput('')}
                className="absolute inset-y-0 right-1 flex size-11 cursor-pointer items-center justify-center rounded-lg text-slate-400 hover:bg-slate-50 hover:text-slate-600"
              >
                <CloseIcon />
              </button>
            ) : null}
          </label>
        </div>

        {list.error ? (
          <div className="p-4">
            <FormAlert>{list.error}</FormAlert>
          </div>
        ) : null}

        {list.loading && list.rows.length === 0 ? (
          <LoadingInline label="Loading unsigned EIRs" />
        ) : list.rows.length === 0 ? (
          <p className="px-4 py-10 text-center text-sm text-slate-500">No unsigned EIRs.</p>
        ) : (
          <>
            <ul className="divide-y divide-slate-100 md:hidden">
              {list.rows.map((row) => (
                <li key={row.recid}>
                  <PendingEirCard row={row} onSign={() => navigate(`/data-entry/eir-signing/${row.recid}/edit`)} />
                </li>
              ))}
            </ul>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                  <tr>
                    <th className="whitespace-nowrap px-4 py-3">EIR No.</th>
                    <th className="whitespace-nowrap px-4 py-3">Container</th>
                    <th className="whitespace-nowrap px-4 py-3">Driver Name</th>
                    <th className="whitespace-nowrap px-4 py-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {list.rows.map((row) => (
                    <PendingEirRow key={row.recid} row={row} onSign={() => navigate(`/data-entry/eir-signing/${row.recid}/edit`)} />
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}

        <PaginationBar
          page={list.page}
          lastPage={list.lastPage}
          perPage={list.perPage}
          total={list.total}
          onPageChange={list.setPage}
          onPerPageChange={list.setPerPage}
        />
      </div>
    </div>
  )
}

function PendingEirRow({ row, onSign }: { row: EirSigningRow; onSign: () => void }) {
  return (
    <tr>
      <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">{row.docnum || '—'}</td>
      <td className="whitespace-nowrap px-4 py-3 text-slate-700">{row.vannum || '—'}</td>
      <td className="max-w-[9rem] truncate px-4 py-3 text-slate-700 sm:max-w-none sm:overflow-visible sm:whitespace-nowrap">
        {row.driver_name || '—'}
      </td>
      <td className="whitespace-nowrap px-4 py-3 text-right">
        <button
          type="button"
          onClick={onSign}
          className="inline-flex min-h-10 cursor-pointer items-center rounded-md bg-green-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-600"
        >
          Upload Sign.
        </button>
      </td>
    </tr>
  )
}

function PendingEirCard({ row, onSign }: { row: EirSigningRow; onSign: () => void }) {
  return (
    <div className="px-4 py-3">
      <p className="font-medium text-slate-900">{row.docnum || '—'}</p>
      <p className="mt-0.5 text-sm text-slate-600">Container {row.vannum || '—'}</p>
      <p className="mt-0.5 text-sm text-slate-500">{row.driver_name || '—'}</p>
      <button
        type="button"
        onClick={onSign}
        className="mt-3 inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-lg bg-green-500 px-3 text-sm font-semibold text-white hover:bg-green-600"
      >
        Upload Sign.
      </button>
    </div>
  )
}
