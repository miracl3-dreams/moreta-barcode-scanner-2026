import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import type { MutableRefObject, PointerEvent as ReactPointerEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchEirSigning, submitEirSignature } from '../api'
import type { EirSigningDetail } from '../api'
import { ButtonSpinner, PageLoader } from '../components/PageLoader'
import { FormAlert } from '../components/ui/FormAlert'
import { useToast } from '../components/ui/ToastProvider'
import { fieldErrorsFromAxios } from '../lib/validation'
import { usePageTitle } from '../lib/pageTitle'

export function EirSigningPadPage() {
  usePageTitle('EIR Signing', 'Sign')
  const { recid: recidParam } = useParams()
  const recid = Number(recidParam)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const toast = useToast()
  const [detail, setDetail] = useState<EirSigningDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const canvasRef = useRef<HTMLCanvasElement | null>(null)
  const dirtyRef = useRef(false)
  const [dirty, setDirty] = useState(false)
  const [padKey, setPadKey] = useState(0)

  const markDirty = useCallback(() => {
    if (!dirtyRef.current) {
      dirtyRef.current = true
      setDirty(true)
    }
  }, [])

  const clearPad = useCallback(() => {
    dirtyRef.current = false
    setDirty(false)
    setPadKey((value) => value + 1)
  }, [])

  useEffect(() => {
    if (!Number.isInteger(recid) || recid <= 0) {
      setLoadError('EIR form not present.')
      setLoading(false)
      return
    }

    let cancelled = false
    setLoading(true)
    fetchEirSigning(recid)
      .then((row) => {
        if (!cancelled) {
          setDetail(row)
          setLoadError('')
        }
      })
      .catch((err) => {
        if (cancelled) {
          return
        }
        const parsed = fieldErrorsFromAxios(err, 'Unable to load this EIR.')
        const message = parsed.fields.signature || parsed.form
        setLoadError(message)
        toast.error(message)
        navigate('/data-entry/eir-signing', { replace: true })
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [navigate, recid, toast])

  async function onSubmit() {
    if (!detail) {
      return
    }
    if (!dirtyRef.current) {
      setFormError('Please sign before submitting.')
      return
    }
    const canvas = canvasRef.current
    if (!canvas) {
      setFormError('Signature pad is not ready.')
      return
    }

    setSubmitting(true)
    setFormError('')
    try {
      const blob = await canvasToPng(canvas)
      await submitEirSignature(detail.recid, blob)
      await queryClient.invalidateQueries({ queryKey: ['list', 'eir-signing'] })
      toast.success(`Signed ${detail.docnum}`)
      navigate('/data-entry/eir-signing', { replace: true })
    } catch (err) {
      const parsed = fieldErrorsFromAxios(err, 'Unable to save the signature.')
      const message = parsed.fields.signature || parsed.form
      setFormError(message)
      toast.error(message)
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <PageLoader inline label="Loading EIR" />
  }

  if (!detail) {
    return (
      <div className="mx-auto w-full max-w-2xl">
        <FormAlert>{loadError || 'EIR form not present.'}</FormAlert>
      </div>
    )
  }

  const typeSize = [detail.type, detail.size].filter(Boolean).join(' / ')

  return (
    <div className="mx-auto w-full max-w-2xl">
      <Link
        to="/data-entry/eir-signing"
        className="mb-3 inline-flex min-h-10 items-center text-sm font-medium text-slate-600 hover:text-slate-900"
      >
        ← Pending list
      </Link>

      <article className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200">
        <header className="bg-[#1b3a5f] px-5 py-4 text-white">
          <h1 className="text-xl font-bold">Driver / Shipper Signature</h1>
          <p className="mt-1 text-sm text-white/80">Please verify the details below, then sign on the pad.</p>
        </header>

        <div className="px-5 py-4">
          <dl className="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <SummaryItem label="EIR No" value={detail.docnum} />
            <SummaryItem label="Container" value={detail.vannum} />
            <SummaryItem label="Trucker" value={detail.trucker} />
            <SummaryItem label="Plate No" value={detail.plateno} />
            <SummaryItem label="Driver" value={detail.driver_name} />
            <SummaryItem label="Type/Size" value={typeSize} />
            <SummaryItem label="Origin" value={detail.origin} />
            <SummaryItem label="Move" value={detail.move_type} />
          </dl>

          {formError ? (
            <div className="mt-4">
              <FormAlert>{formError}</FormAlert>
            </div>
          ) : null}

          <SignaturePad key={padKey} canvasRef={canvasRef} dirty={dirty} onStroke={markDirty} />

          <div className="mt-4 flex gap-3">
            <button
              type="button"
              onClick={clearPad}
              disabled={submitting || !dirty}
              className="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-lg bg-slate-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-600 disabled:cursor-default disabled:opacity-50"
            >
              Clear
            </button>
            <button
              type="button"
              onClick={() => void onSubmit()}
              disabled={submitting}
              className="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-600 disabled:opacity-60"
            >
              {submitting ? <ButtonSpinner /> : null}
              Submit Signature
            </button>
          </div>
        </div>
      </article>
    </div>
  )
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <dt className="inline font-semibold text-slate-800">{label}:</dt>{' '}
      <dd className="inline text-slate-700">{value || '—'}</dd>
    </div>
  )
}

function SignaturePad({
  canvasRef,
  dirty,
  onStroke,
}: {
  canvasRef: MutableRefObject<HTMLCanvasElement | null>
  dirty: boolean
  onStroke: () => void
}) {
  const wrapRef = useRef<HTMLDivElement | null>(null)
  const drawing = useRef(false)
  const inked = useRef(false)
  const last = useRef<{ x: number; y: number } | null>(null)

  const sizeCanvas = useCallback(() => {
    const canvas = canvasRef.current
    const wrap = wrapRef.current
    if (!canvas || !wrap || inked.current) {
      return
    }
    const cssWidth = Math.max(1, wrap.clientWidth)
    const cssHeight = Math.max(260, Math.round(cssWidth * 0.5))
    const dpr = window.devicePixelRatio || 1
    canvas.width = Math.round(cssWidth * dpr)
    canvas.height = Math.round(cssHeight * dpr)
    canvas.style.width = `${cssWidth}px`
    canvas.style.height = `${cssHeight}px`
    fillWhite(canvas)
  }, [canvasRef])

  useEffect(() => {
    sizeCanvas()
    const wrap = wrapRef.current
    if (!wrap || typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', sizeCanvas)
      return () => window.removeEventListener('resize', sizeCanvas)
    }
    const observer = new ResizeObserver(() => sizeCanvas())
    observer.observe(wrap)
    return () => observer.disconnect()
  }, [sizeCanvas])

  function pointFromEvent(event: PointerEvent, canvas: HTMLCanvasElement) {
    const rect = canvas.getBoundingClientRect()
    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    }
  }

  function stroke(canvas: HTMLCanvasElement, from: { x: number; y: number }, to: { x: number; y: number }) {
    const ctx = canvas.getContext('2d')
    if (!ctx) {
      return
    }
    const dpr = window.devicePixelRatio || 1
    ctx.save()
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.strokeStyle = '#111827'
    ctx.lineWidth = 2.5
    ctx.beginPath()
    ctx.moveTo(from.x, from.y)
    ctx.lineTo(to.x, to.y)
    ctx.stroke()
    ctx.restore()
  }

  function onPointerDown(event: ReactPointerEvent<HTMLCanvasElement>) {
    const canvas = event.currentTarget
    canvas.setPointerCapture(event.pointerId)
    drawing.current = true
    inked.current = true
    last.current = pointFromEvent(event.nativeEvent, canvas)
    onStroke()
  }

  function onPointerMove(event: ReactPointerEvent<HTMLCanvasElement>) {
    if (!drawing.current || !last.current) {
      return
    }
    const canvas = event.currentTarget
    const next = pointFromEvent(event.nativeEvent, canvas)
    stroke(canvas, last.current, next)
    last.current = next
    onStroke()
  }

  function onPointerUp(event: ReactPointerEvent<HTMLCanvasElement>) {
    drawing.current = false
    last.current = null
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId)
    }
  }

  return (
    <div ref={wrapRef} className="relative mt-5">
      <canvas
        ref={canvasRef}
        className="block w-full touch-none rounded-md border border-slate-300 bg-white"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      />
      {!dirty ? (
        <p className="pointer-events-none absolute inset-x-0 bottom-3 text-center text-sm text-slate-400">
          Sign here using your finger or stylus
        </p>
      ) : null}
    </div>
  )
}

function fillWhite(canvas: HTMLCanvasElement) {
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    return
  }
  ctx.save()
  ctx.setTransform(1, 0, 0, 1, 0, 0)
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.restore()
}

function canvasToPng(canvas: HTMLCanvasElement): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (blob) {
        resolve(blob)
      } else {
        reject(new Error('Unable to capture the signature.'))
      }
    }, 'image/png')
  })
}
