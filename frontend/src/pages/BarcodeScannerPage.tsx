import { useCallback, useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import {
  ensureCameraPermission,
  enumerateCameraDevices,
  isMobileDevice,
  needsCameraUnlock,
  normalizeScannedCode,
  pickDefaultCameraDevice,
  startBarcodeScanner,
} from '../lib/barcodeReader'
import {
  fetchBarcodeScannerLookups,
  saveBarcodeScan,
  scanBarcodeEir,
} from '../api'
import type {
  BarcodeScanResult,
  BarcodeScannerLookups,
  EirSketchFlags,
} from '../api'
import { FormModal } from '../components/crud/FormModal'
import { fieldControlClass, FieldLabel } from '../components/crud/fields'
import { PageHeading } from '../components/PageHeading'
import { useToast } from '../components/ui/ToastProvider'
import { errorFromAxios, fieldErrorsFromAxios } from '../lib/validation'
import { usePageTitle } from '../lib/pageTitle'

type BarcodeScannerPageProps = {
  title: string
}

type MoveType = 'pickup_mt' | 'pickup_full' | 'return_full' | 'return_mt' | ''

const FLAG_KEYS: (keyof EirSketchFlags)[] = [
  'ok',
  'lk',
  'be',
  'br',
  'cr',
  'di',
  'h',
  'l',
  'm',
  't',
  'pi',
  'c',
  'po',
  'bo',
]

const emptyLookups: BarcodeScannerLookups = {
  destinations: [],
  voyages: [],
  sketch_parts: {},
  sketch_groups: {},
}

const PICKUP_MT_TO_RETURN_MT_MESSAGE =
  'Cannot change Pickup MT to Return MT. This indicates a damaged container. Ask a supervisor to update the EIR status.'

const ALREADY_RETURNED_MESSAGE = 'This EIR is already returned. It cannot be scanned again.'

function emptyFlags(): EirSketchFlags {
  return {
    ok: false,
    lk: false,
    be: false,
    br: false,
    cr: false,
    di: false,
    h: false,
    l: false,
    m: false,
    t: false,
    pi: false,
    c: false,
    po: false,
    bo: false,
  }
}

function emptySketch(parts: Record<string, string>): Record<string, EirSketchFlags> {
  const sketch: Record<string, EirSketchFlags> = {}
  for (const key of Object.keys(parts)) {
    sketch[key] = emptyFlags()
  }
  return sketch
}

function isPickupMt(value: string): boolean {
  return value.trim().toUpperCase() === 'MT'
}

function isReturnedMove(value: string): boolean {
  const move = value.trim().toUpperCase()
  return move === 'FULL' || move === 'MT'
}

function destinationRequiresVoyage(
  dstcde: string,
  destinations: BarcodeScannerLookups['destinations'],
): boolean {
  const row = destinations.find((item) => item.dstcde === dstcde)
  if (row?.requires_voyage) {
    return true
  }
  const haystack = `${row?.dstcde ?? ''} ${row?.dstdsc ?? ''} ${dstcde}`.toUpperCase()
  return haystack.includes('LD TO VSL') || haystack.includes('LOADED TO VESSEL') || haystack.includes('LOAD TO VESSEL')
}

function moveTypeFromScan(result: BarcodeScanResult): MoveType {
  if (isPickupMt(result.pickup)) {
    return 'pickup_mt'
  }
  if (result.pickup.trim().toUpperCase() === 'FULL') {
    return 'pickup_full'
  }
  if (result.return.trim().toUpperCase() === 'MT') {
    return 'return_mt'
  }
  if (result.return.trim().toUpperCase() === 'FULL') {
    return 'return_full'
  }
  return ''
}

export function BarcodeScannerPage({ title }: BarcodeScannerPageProps) {
  usePageTitle(title)
  const toast = useToast()
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const stopScanRef = useRef<(() => void) | null>(null)
  const scanningRef = useRef(true)
  const lookupsRef = useRef<BarcodeScannerLookups>(emptyLookups)
  const modalOpenRef = useRef(false)
  const applyScanRef = useRef<(code: string) => void>(() => {})

  const [lookups, setLookups] = useState<BarcodeScannerLookups>(emptyLookups)
  const [devices, setDevices] = useState<MediaDeviceInfo[]>([])
  const [deviceId, setDeviceId] = useState('')
  const [cameraError, setCameraError] = useState('')
  const [cameraStarting, setCameraStarting] = useState(true)
  const [cameraUnlocked, setCameraUnlocked] = useState(() => !needsCameraUnlock())
  const [scanHint, setScanHint] = useState('Point the EIR barcode at the camera.')
  const [scanLogs, setScanLogs] = useState<string[]>([])
  const [manualCode, setManualCode] = useState('')

  const [modalOpen, setModalOpen] = useState(false)
  const [scanned, setScanned] = useState('')
  const [moveType, setMoveType] = useState<MoveType>('')
  const [dstcde, setDstcde] = useState('')
  const [voynum, setVoynum] = useState('')
  const [originalPickup, setOriginalPickup] = useState('')
  const [originalReturn, setOriginalReturn] = useState('')
  const [sketch, setSketch] = useState<Record<string, EirSketchFlags>>({})
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')

  lookupsRef.current = lookups
  modalOpenRef.current = modalOpen

  const addLog = useCallback((message: string) => {
    const line = `${new Date().toLocaleTimeString()}  ${message}`
    console.info('[scanner]', message)
    setScanLogs((current) => [...current.slice(-19), line])
  }, [])

  const applyScan = useCallback(
    async (code: string) => {
      const trimmed = normalizeScannedCode(code)
      if (!trimmed || !scanningRef.current) {
        return
      }
      scanningRef.current = false
      setScanHint(`Looking up ${trimmed}…`)
      addLog(`Looking up ${trimmed}`)
      try {
        const result = await scanBarcodeEir(trimmed)
        if (isReturnedMove(result.return)) {
          addLog(`Blocked ${result.docnum}: already returned`)
          toast.error(ALREADY_RETURNED_MESSAGE)
          setScanHint('This EIR is already returned. Scan another barcode.')
          scanningRef.current = true
          return
        }
        const parts = lookupsRef.current.sketch_parts
        stopScanRef.current?.()
        stopScanRef.current = null
        addLog(`Found EIR ${result.docnum}`)
        setScanned(result.docnum)
        setDstcde(result.dstcde)
        setOriginalPickup(result.pickup)
        setOriginalReturn(result.return)
        setVoynum(result.voynum || '')
        setSketch({ ...emptySketch(parts), ...result.sketch })
        const nextMove = moveTypeFromScan(result)
        setMoveType(nextMove === 'return_mt' && isPickupMt(result.pickup) ? '' : nextMove)
        setFormError('')
        setModalOpen(true)
      } catch (err) {
        const parsed = fieldErrorsFromAxios(err, 'Invalid EIR no.')
        const message = parsed.fields.code || parsed.form || 'Invalid EIR no.'
        addLog(`Lookup failed: ${message}`)
        toast.error(message)
        setScanHint('Scan failed. Adjust lighting and try again.')
        scanningRef.current = true
      }
    },
    [addLog, toast],
  )

  applyScanRef.current = (code: string) => {
    void applyScan(code)
  }

  useEffect(() => {
    fetchBarcodeScannerLookups()
      .then((data) => {
        setLookups(data)
        setSketch(emptySketch(data.sketch_parts))
      })
      .catch(() => toast.error('Unable to load scanner lookups.'))
  }, [toast])

  useEffect(() => {
    if (!cameraUnlocked) {
      setCameraStarting(false)
      setCameraError('')
      return
    }

    let cancelled = false

    async function loadCameras() {
      try {
        await ensureCameraPermission()
        const listed = await enumerateCameraDevices()
        if (cancelled) {
          return
        }

        setDevices(listed)
        setDeviceId((current) => {
          if (current && listed.some((device) => device.deviceId === current)) {
            return current
          }
          return pickDefaultCameraDevice(listed)
        })
        if (listed.length === 0) {
          setCameraError('No camera found on this device.')
          setCameraStarting(false)
        }
      } catch (err) {
        if (!cancelled) {
          setCameraStarting(false)
          setCameraError(err instanceof Error ? err.message : 'Unable to access camera.')
        }
      }
    }

    void loadCameras()

    function onDeviceChange() {
      void enumerateCameraDevices()
        .then((listed) => {
          setDevices(listed)
          setDeviceId((current) => {
            if (current && listed.some((device) => device.deviceId === current)) {
              return current
            }
            return pickDefaultCameraDevice(listed)
          })
        })
        .catch(() => {
          // ignore refresh errors
        })
    }

    navigator.mediaDevices?.addEventListener('devicechange', onDeviceChange)

    return () => {
      cancelled = true
      navigator.mediaDevices?.removeEventListener('devicechange', onDeviceChange)
    }
  }, [cameraUnlocked])

  useEffect(() => {
    if (modalOpen || !deviceId || !cameraUnlocked) {
      return
    }

    let cancelled = false
    scanningRef.current = true
    setCameraStarting(true)
    setCameraError('')
    setScanHint('Point the EIR barcode at the camera.')

    async function start() {
      try {
        const video = videoRef.current
        if (!video) {
          throw new Error('Camera preview is not available.')
        }

        stopScanRef.current?.()
        stopScanRef.current = null

        const stop = await startBarcodeScanner(
          deviceId,
          video,
          (text) => {
            if (!scanningRef.current || modalOpenRef.current || !text) {
              return
            }
            setScanHint(`Detected: ${text}`)
            applyScanRef.current(text)
          },
          addLog,
        )

        if (cancelled) {
          stop()
          return
        }

        stopScanRef.current = stop
        setCameraStarting(false)
      } catch (err) {
        if (!cancelled) {
          setCameraStarting(false)
          const message = err instanceof Error ? err.message : 'Unable to start camera.'
          addLog(`Camera error: ${message}`)
          setCameraError(message)
        }
      }
    }

    void start()

    return () => {
      cancelled = true
      scanningRef.current = false
      stopScanRef.current?.()
      stopScanRef.current = null
    }
  }, [addLog, cameraUnlocked, deviceId, modalOpen])

  async function unlockCamera() {
    setCameraError('')
    setCameraStarting(true)
    try {
      await ensureCameraPermission()
      setCameraUnlocked(true)
      addLog('Camera permission granted.')
    } catch (err) {
      setCameraStarting(false)
      const message = err instanceof Error ? err.message : 'Unable to access camera.'
      addLog(`Camera error: ${message}`)
      setCameraError(message)
    }
  }

  function toggleFlag(part: string, flag: keyof EirSketchFlags) {
    setSketch((current) => {
      const next = { ...(current[part] ?? emptyFlags()) }
      const turningOn = !next[flag]
      for (const key of FLAG_KEYS) {
        next[key] = false
      }
      next[flag] = turningOn
      return { ...current, [part]: next }
    })
  }

  function closeModal() {
    setModalOpen(false)
    setFormError('')
    setVoynum('')
    setOriginalPickup('')
    setOriginalReturn('')
    setScanHint('Point the EIR barcode at the camera.')
    scanningRef.current = true
  }

  async function onSave(event: FormEvent) {
    event.preventDefault()
    if (!scanned) {
      setFormError('No scanned code detected.')
      return
    }
    if (!moveType) {
      setFormError('Type of Move is required.')
      return
    }
    if (isPickupMt(originalPickup) && moveType === 'return_mt') {
      setFormError(PICKUP_MT_TO_RETURN_MT_MESSAGE)
      return
    }
    if (isReturnedMove(originalReturn)) {
      setFormError(ALREADY_RETURNED_MESSAGE)
      return
    }
    if (!dstcde) {
      setFormError('Return Van To is required.')
      return
    }
    if (destinationRequiresVoyage(dstcde, lookups.destinations) && !voynum) {
      setFormError('Voyage Number is required when Return Van To is Loaded to Vessel.')
      return
    }
    const parts = Object.keys(lookups.sketch_parts)
    const allValid = parts.every((key) => FLAG_KEYS.filter((flag) => sketch[key]?.[flag]).length === 1)
    if (!allValid) {
      setFormError('Each part must have exactly one damage selected.')
      return
    }

    setSaving(true)
    setFormError('')
    try {
      const result = await saveBarcodeScan({
        code: scanned,
        movetype: moveType,
        dstcde,
        voynum,
        sketch,
      })
      toast.success(result.message)
      closeModal()
    } catch (err) {
      const parsed = fieldErrorsFromAxios(err, 'Failed to save EIR.')
      const message = parsed.form || errorFromAxios(err, 'Failed to save EIR.')
      setFormError(message)
      toast.error(message)
    } finally {
      setSaving(false)
    }
  }

  const pickupMtLocked = isPickupMt(originalPickup)

  const moveOptionClass = (active: boolean, disabled = false) =>
    `min-h-11 flex-1 rounded-xl border px-3 py-2.5 text-center text-sm font-semibold transition ${
      disabled
        ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300'
        : active
          ? 'border-app-header bg-app-header text-white'
          : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
    }`

  const flagChipClass = (active: boolean, disabled: boolean) =>
    `inline-flex min-h-10 min-w-12 items-center justify-center rounded-lg border px-2.5 text-xs font-semibold transition ${
      active
        ? 'border-app-header bg-app-header text-white'
        : disabled
          ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300'
          : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
    }`

  return (
    <div className="mx-auto w-full max-w-lg md:max-w-xl">
      <PageHeading
        title={title}
        description={
          isMobileDevice()
            ? 'Point the camera at a printed EIR barcode.'
            : 'Scan an EIR barcode to update move type, destination, and container sketch.'
        }
      />

      {!isMobileDevice() ? (
        <div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">
          <p className="font-semibold">Phone testing</p>
          <p className="mt-1 text-sky-900/90">
            Use the PC hotspot and open <code className="text-xs">https://192.168.137.1:5175/barcode_scanner/</code> on the
            phone. Camera on HTTP may need the Chrome insecure-origin flag.
          </p>
        </div>
      ) : !window.isSecureContext ? (
        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
          <p className="font-semibold">Enable camera once</p>
          <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm">
            <li>
              Open <code className="break-all text-[11px]">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code>
            </li>
            <li>
              Paste: <code className="break-all text-[11px]">{window.location.origin}</code>
            </li>
            <li>Enabled → Relaunch → tap Enable camera below</li>
          </ol>
        </div>
      ) : null}

      <section className="mt-4 space-y-3">
        {devices.length > 1 ? (
          <div>
            <label htmlFor="select_cam" className="block text-sm font-medium text-slate-700">
              Camera
            </label>
            <select
              id="select_cam"
              value={deviceId}
              onChange={(event) => setDeviceId(event.target.value)}
              className={`${fieldControlClass} mt-1.5 min-h-11`}
            >
              {devices.map((device) => (
                <option key={device.deviceId} value={device.deviceId}>
                  {device.label || `Camera ${device.deviceId.slice(0, 8)}`}
                </option>
              ))}
            </select>
          </div>
        ) : null}

        {!cameraUnlocked ? (
          <button
            type="button"
            onClick={() => void unlockCamera()}
            className="inline-flex min-h-12 w-full cursor-pointer items-center justify-center rounded-xl bg-app-header px-4 py-3 text-base font-semibold text-white hover:bg-app-header-hover"
          >
            Enable camera
          </button>
        ) : null}

        <div className="overflow-hidden rounded-2xl border border-slate-300 bg-black shadow-sm">
          <video
            ref={videoRef}
            className="aspect-[3/4] w-full object-cover sm:aspect-[4/3] sm:max-h-[min(60vh,480px)] sm:object-contain"
            autoPlay
            muted
            playsInline
          />
        </div>

        <p
          className={`rounded-xl px-3 py-2.5 text-sm ${
            cameraError
              ? 'bg-red-50 text-red-700'
              : cameraStarting
                ? 'bg-slate-100 text-slate-500'
                : 'bg-emerald-50 text-emerald-800'
          }`}
        >
          {cameraError || (cameraStarting ? 'Starting camera…' : scanHint)}
        </p>

        <details className="rounded-xl border border-slate-200 bg-white open:shadow-sm">
          <summary className="cursor-pointer list-none px-3 py-3 text-sm font-medium text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
            <span className="flex items-center justify-between gap-2">
              Scan log
              <span className="text-xs font-normal text-slate-400">{scanLogs.length} entries</span>
            </span>
          </summary>
          <pre className="max-h-36 overflow-auto border-t border-slate-100 bg-slate-50 p-3 text-[11px] leading-5 text-slate-700">
            {scanLogs.length === 0 ? 'Waiting for camera…' : scanLogs.join('\n')}
          </pre>
        </details>

        <form
          className="flex flex-col gap-2 sm:flex-row"
          onSubmit={(event) => {
            event.preventDefault()
            void applyScan(manualCode)
          }}
        >
          <input
            value={manualCode}
            onChange={(event) => setManualCode(event.target.value)}
            placeholder="Or type EIR no."
            inputMode="text"
            autoCapitalize="characters"
            autoCorrect="off"
            className={`${fieldControlClass} min-h-12 text-base sm:text-sm`}
          />
          <button
            type="submit"
            className="inline-flex min-h-12 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-app-header px-5 text-base font-semibold text-white hover:bg-app-header-hover sm:min-h-11 sm:text-sm"
          >
            Scan
          </button>
        </form>
      </section>

      <FormModal
        open={modalOpen}
        title={`Confirm EIR: ${scanned}`}
        submitLabel="Confirm"
        submitting={saving}
        error={formError}
        extraWide
        onClose={closeModal}
        onSubmit={(event) => void onSave(event)}
      >
        <div>
          <FieldLabel>Type of Move</FieldLabel>
          <div className="mt-2 space-y-3">
            <div>
              <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup</p>
              <div className="flex gap-2">
                <button type="button" className={moveOptionClass(moveType === 'pickup_mt')} onClick={() => setMoveType('pickup_mt')}>
                  MT
                </button>
                <button
                  type="button"
                  className={moveOptionClass(moveType === 'pickup_full')}
                  onClick={() => setMoveType('pickup_full')}
                >
                  FULL
                </button>
              </div>
            </div>
            <div>
              <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Return</p>
              <div className="flex gap-2">
                <button
                  type="button"
                  className={moveOptionClass(moveType === 'return_full')}
                  onClick={() => setMoveType('return_full')}
                >
                  FULL
                </button>
                <button
                  type="button"
                  className={moveOptionClass(moveType === 'return_mt', pickupMtLocked)}
                  disabled={pickupMtLocked}
                  onClick={() => {
                    if (pickupMtLocked) {
                      setFormError(PICKUP_MT_TO_RETURN_MT_MESSAGE)
                      return
                    }
                    setMoveType('return_mt')
                  }}
                >
                  MT
                </button>
              </div>
            </div>
          </div>
          {pickupMtLocked ? (
            <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
              {PICKUP_MT_TO_RETURN_MT_MESSAGE}
            </p>
          ) : null}
        </div>

        <div>
          <FieldLabel htmlFor="destination_select" required>
            Return Van To
          </FieldLabel>
          <select
            id="destination_select"
            value={dstcde}
            onChange={(event) => {
              const next = event.target.value
              setDstcde(next)
              if (!destinationRequiresVoyage(next, lookups.destinations)) {
                setVoynum('')
              }
            }}
            className={`${fieldControlClass} mt-1.5 min-h-11 text-base sm:text-sm`}
          >
            <option value="">-- Select Destination --</option>
            {lookups.destinations.map((row) => (
              <option key={row.dstcde} value={row.dstcde}>
                {row.dstdsc || row.dstcde}
              </option>
            ))}
          </select>
        </div>

        <div>
          <FieldLabel htmlFor="voyage_select" required={destinationRequiresVoyage(dstcde, lookups.destinations)}>
            Voyage Number
          </FieldLabel>
          <select
            id="voyage_select"
            value={voynum}
            onChange={(event) => setVoynum(event.target.value)}
            className={`${fieldControlClass} mt-1.5 min-h-11 text-base sm:text-sm`}
          >
            <option value="">-- Select Voyage --</option>
            {(lookups.voyages ?? []).map((row) => (
              <option key={row.voynum} value={row.voynum}>
                {row.voynum}
                {row.trndte ? ` — sailing ${row.trndte}` : ''}
              </option>
            ))}
          </select>
          <p className="mt-1 text-xs text-slate-500">
            {destinationRequiresVoyage(dstcde, lookups.destinations)
              ? 'Required for Loaded to Vessel (empty van on a voyage with no BL). Active voyages by sailing date.'
              : 'Required only when Return Van To is Loaded to Vessel (LD TO VSL empty / full).'}
          </p>
        </div>

        <fieldset>
          <FieldLabel>Container Sketch Labels</FieldLabel>
          <p className="mt-1 text-xs text-slate-500">Tap one damage code per part.</p>
          {Object.entries(lookups.sketch_groups).map(([group, keys]) => (
            <div key={group} className="mt-3 rounded-xl border border-slate-200 bg-slate-50/80 p-3">
              <p className="text-sm font-semibold text-slate-800">{group}</p>
              {keys.map((key) => {
                const selected = FLAG_KEYS.filter((flag) => sketch[key]?.[flag])
                return (
                  <div key={key} className="mt-3 border-t border-slate-200 pt-3 first:border-t-0 first:pt-2">
                    <p className="text-sm font-medium text-slate-700">{lookups.sketch_parts[key] ?? key}</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {FLAG_KEYS.map((flag) => {
                        const active = sketch[key]?.[flag] ?? false
                        const disabled = selected.length === 1 && !active
                        return (
                          <button
                            key={flag}
                            type="button"
                            disabled={disabled}
                            className={flagChipClass(active, disabled)}
                            onClick={() => toggleFlag(key, flag)}
                          >
                            {flag.toUpperCase()}
                          </button>
                        )
                      })}
                    </div>
                  </div>
                )
              })}
            </div>
          ))}
        </fieldset>
      </FormModal>
    </div>
  )
}
