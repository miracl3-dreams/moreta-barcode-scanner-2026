import axios from 'axios'
import type { Paginated } from './components/crud/types'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '',
  withCredentials: true,
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

api.interceptors.request.use((config) => {
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
    const headers = config.headers
    if (headers && typeof headers.delete === 'function') {
      headers.delete('Content-Type')
    } else if (headers) {
      delete headers['Content-Type']
    }
  }
  return config
})

export type AuthUser = {
  usrcde: string
  usrname: string
  usrlvl: string
  brnchcde: string
  isadmin: boolean
  comcde?: string
  comdsc?: string
}

export type LoginContext = {
  app_title: string
  ip?: string
}

export type LoginFailure = {
  code: string
  message: string
}

export async function csrf(): Promise<void> {
  await api.get('/sanctum/csrf-cookie')
}

export async function fetchLoginContext(): Promise<LoginContext> {
  const { data } = await api.get<LoginContext>('/api/login-context')
  return data
}

export async function login(usrname: string, password: string): Promise<AuthUser> {
  await csrf()
  const { data } = await api.post<AuthUser>('/api/login', { usrname, password })
  return data
}

export async function logout(): Promise<void> {
  await api.post('/api/logout')
}

export async function fetchUser(): Promise<AuthUser | null> {
  const { data, status } = await api.get<AuthUser>('/api/user', {
    validateStatus: (s) => s === 200 || s === 401,
  })
  if (status === 401) {
    return null
  }
  return data
}

export type EirSketchFlags = {
  ok: boolean
  lk: boolean
  be: boolean
  br: boolean
  cr: boolean
  di: boolean
  h: boolean
  l: boolean
  m: boolean
  t: boolean
  pi: boolean
  c: boolean
  po: boolean
  bo: boolean
}

export type BarcodeScannerLookups = {
  destinations: { dstcde: string; dstdsc: string; requires_voyage?: boolean }[]
  voyages: { voynum: string; trndte: string }[]
  sketch_parts: Record<string, string>
  sketch_groups: Record<string, string[]>
}

export type BarcodeScanResult = {
  docnum: string
  pickup: string
  return: string
  dstcde: string
  voynum: string
  vannum: string
  sketch: Record<string, EirSketchFlags>
}

export type BarcodeScanSavePayload = {
  code: string
  movetype: 'pickup_mt' | 'pickup_full' | 'return_full' | 'return_mt'
  dstcde: string
  voynum: string
  sketch: Record<string, EirSketchFlags>
}

export async function fetchBarcodeScannerLookups(): Promise<BarcodeScannerLookups> {
  const { data } = await api.get<BarcodeScannerLookups>('/api/barcode-scanner/lookups')
  return data
}

export async function scanBarcodeEir(code: string): Promise<BarcodeScanResult> {
  const { data } = await api.post<BarcodeScanResult>('/api/barcode-scanner/scan', { code })
  return data
}

export async function saveBarcodeScan(
  payload: BarcodeScanSavePayload,
): Promise<{ ok: boolean; message: string; docnum: string }> {
  const { data } = await api.post<{ ok: boolean; message: string; docnum: string }>(
    '/api/barcode-scanner/save',
    payload,
  )
  return data
}

export type EirSigningRow = {
  recid: number
  docnum: string
  issue_dte: string
  vannum: string
  trucker: string
  origin: string
  move_type: string
  driver_name: string
}

export type EirSigningDetail = EirSigningRow & {
  dstcde: string
  plateno: string
  type: string
  size: string
  seal_no: string
}

export async function fetchPendingEirSignings(
  search: string,
  page: number,
  perPage: number | 'all',
  sort?: string,
  dir?: string,
): Promise<Paginated<EirSigningRow>> {
  const { data } = await api.get<Paginated<EirSigningRow>>('/api/eir-signing', {
    params: { search, page, per_page: perPage, sort, dir },
  })
  return data
}

export async function fetchEirSigning(recid: number): Promise<EirSigningDetail> {
  const { data } = await api.get<EirSigningDetail>(`/api/eir-signing/${recid}`)
  return data
}

export async function submitEirSignature(recid: number, file: Blob): Promise<{ ok: boolean; docnum: string }> {
  const body = new FormData()
  body.append('signature', file, 'signature.png')
  const { data } = await api.post<{ ok: boolean; docnum: string }>(`/api/eir-signing/${recid}/signature`, body)
  return data
}

export default api
