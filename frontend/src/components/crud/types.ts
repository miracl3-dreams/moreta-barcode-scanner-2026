import type { ReactNode } from 'react'

export type PaginatedMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type Paginated<T> = {
  data: T[]
  meta: PaginatedMeta
}

export type CrudColumn<T> = {
  key: string
  header: string
  className?: string
  sortable?: boolean
  render: (row: T) => ReactNode
}

export type ImportErrorRow = {
  line: number
  code: string
  message: string
}

export type ImportResult = {
  imported: number
  errors: ImportErrorRow[]
}
