import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useState } from 'react'
import type { Paginated } from './types'

export type SortDir = 'asc' | 'desc'

export type ListQuery = {
  search: string
  page: number
  perPage: number
  sort: string
  dir: SortDir
}

type Options = {
  queryKey: string
  defaultSort: string
  defaultDir?: SortDir
  /** Override React Query staleTime (ms). Use 0 for lists that must drop settled rows immediately. */
  staleTime?: number
  refetchOnMount?: boolean | 'always'
}

export function paginatedListKey(scope: string, params: ListQuery) {
  return ['list', scope, params] as const
}

export function usePaginatedLoader<T>(
  loader: (params: ListQuery) => Promise<Paginated<T>>,
  deps: readonly unknown[],
) {
  return useCallback(loader, deps)
}

export function usePaginatedResource<T>(loader: (params: ListQuery) => Promise<Paginated<T>>, options: Options) {
  const queryClient = useQueryClient()
  const defaultDir = options.defaultDir ?? 'asc'
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(5)
  const [sort, setSort] = useState(options.defaultSort)
  const [dir, setDir] = useState<SortDir>(defaultDir)

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const next = searchInput.trim()
      setSearch((prev) => {
        if (prev !== next) {
          setPage(1)
        }
        return next
      })
    }, 350)
    return () => window.clearTimeout(timer)
  }, [searchInput])

  const queryParams: ListQuery = { search, page, perPage, sort, dir }

  const query = useQuery({
    queryKey: paginatedListKey(options.queryKey, queryParams),
    queryFn: () => loader(queryParams),
    placeholderData: keepPreviousData,
    staleTime: options.staleTime,
    refetchOnMount: options.refetchOnMount,
  })

  const rows = query.data?.data ?? []
  const total = query.data?.meta.total ?? 0
  const lastPage = query.data?.meta.last_page ?? 1

  useEffect(() => {
    if (page > lastPage && lastPage > 0) {
      setPage(lastPage)
    }
  }, [lastPage, page])

  function reload() {
    void queryClient.invalidateQueries({ queryKey: ['list', options.queryKey] })
  }

  function changePerPage(value: number) {
    setPerPage(value)
    setPage(1)
  }

  function changeSort(column: string) {
    if (sort === column) {
      setDir((current) => (current === 'asc' ? 'desc' : 'asc'))
    } else {
      setSort(column)
      setDir('asc')
    }
    setPage(1)
  }

  return {
    searchInput,
    setSearchInput,
    search,
    page,
    setPage,
    perPage,
    setPerPage: changePerPage,
    changePerPage,
    sort,
    dir,
    changeSort,
    rows,
    total,
    lastPage,
    loading: query.isPending,
    fetching: query.isFetching,
    error: query.isError ? 'Unable to load records.' : '',
    reload,
  }
}
