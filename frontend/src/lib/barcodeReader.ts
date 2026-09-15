import {
  BinaryBitmap,
  Code128Reader,
  GlobalHistogramBinarizer,
  HTMLCanvasElementLuminanceSource,
  HybridBinarizer,
  NotFoundException,
} from '@zxing/library'

export function normalizeScannedCode(raw: string): string {
  return raw.replace(/[\x00-\x1F\x7F]/g, '').trim()
}

export function isMobileDevice(): boolean {
  if (typeof navigator === 'undefined') {
    return false
  }

  return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
}

/** Prefer the rear camera on phones; fall back to the first listed device. */
export function pickDefaultCameraDevice(devices: MediaDeviceInfo[]): string {
  if (devices.length === 0) {
    return ''
  }

  const backCamera = devices.find((device) => /back|rear|environment|wide|telephoto/i.test(device.label))
  if (backCamera?.deviceId) {
    return backCamera.deviceId
  }

  if (isMobileDevice() && devices.length > 1) {
    return devices[devices.length - 1]?.deviceId ?? devices[0].deviceId
  }

  return devices[0].deviceId
}

function cameraAccessError(): string {
  if (typeof window !== 'undefined' && !window.isSecureContext) {
    const origin = window.location.origin
    return (
      `Camera blocked on this page (${origin}). In Chrome open chrome://flags/#unsafely-treat-insecure-origin-as-secure, ` +
      `paste ${origin}, set Enabled, Relaunch, then reopen this page and tap Enable camera.`
    )
  }

  return 'Camera access is not supported in this browser.'
}

export function assertCameraSupported(): void {
  if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
    throw new Error(cameraAccessError())
  }
}

export function needsCameraUnlock(): boolean {
  return typeof window !== 'undefined' && !window.isSecureContext
}

export function mobileCameraConstraints(deviceId?: string): MediaTrackConstraints {
  if (deviceId) {
    return {
      deviceId: { exact: deviceId },
      facingMode: { ideal: 'environment' },
      width: { ideal: 1920 },
      height: { ideal: 1080 },
    }
  }

  return {
    facingMode: { ideal: 'environment' },
    width: { ideal: 1920 },
    height: { ideal: 1080 },
  }
}

export async function enumerateCameraDevices(): Promise<MediaDeviceInfo[]> {
  if (!navigator.mediaDevices?.enumerateDevices) {
    return []
  }

  const devices = await navigator.mediaDevices.enumerateDevices()
  return devices.filter((device) => device.kind === 'videoinput')
}

/** Request camera permission so device labels are populated. */
export async function ensureCameraPermission(): Promise<void> {
  assertCameraSupported()

  const stream = await navigator.mediaDevices.getUserMedia({
    video: isMobileDevice() ? mobileCameraConstraints() : true,
  })
  for (const track of stream.getTracks()) {
    track.stop()
  }
}

export async function openCameraStream(deviceId?: string): Promise<MediaStream> {
  assertCameraSupported()

  const mobile = isMobileDevice()
  const base: MediaTrackConstraints = deviceId
    ? { deviceId: { exact: deviceId } }
    : mobile
      ? { facingMode: { ideal: 'environment' } }
      : {}

  const attempts: MediaStreamConstraints[] = mobile
    ? [
        { video: mobileCameraConstraints(deviceId) },
        { video: { ...base, width: { ideal: 1280 }, height: { ideal: 720 } } },
        { video: base },
      ]
    : [
        { video: { ...base, width: { ideal: 1280 }, height: { ideal: 720 } } },
        { video: { ...base, width: { ideal: 640 }, height: { ideal: 480 } } },
        { video: base },
      ]

  let lastError: unknown
  for (const constraints of attempts) {
    try {
      return await navigator.mediaDevices.getUserMedia(constraints)
    } catch (err) {
      lastError = err
    }
  }

  throw lastError instanceof Error ? lastError : new Error('Unable to open camera.')
}

async function waitForVideoReady(video: HTMLVideoElement): Promise<void> {
  if (video.videoWidth > 0 && video.videoHeight > 0) {
    return
  }

  await new Promise<void>((resolve, reject) => {
    const timeout = window.setTimeout(() => {
      cleanup()
      reject(new Error('Camera preview timed out.'))
    }, 10_000)

    const cleanup = () => {
      window.clearTimeout(timeout)
      video.removeEventListener('loadedmetadata', onReady)
      video.removeEventListener('loadeddata', onReady)
    }

    const onReady = () => {
      if (video.videoWidth > 0 && video.videoHeight > 0) {
        cleanup()
        resolve()
      }
    }

    video.addEventListener('loadedmetadata', onReady)
    video.addEventListener('loadeddata', onReady)
    onReady()
  })
}

export async function bindStreamToVideo(stream: MediaStream, video: HTMLVideoElement): Promise<void> {
  video.srcObject = stream
  video.setAttribute('playsinline', 'true')
  video.muted = true

  const playPromise = video.play()
  if (playPromise) {
    await playPromise.catch(() => {
      // Autoplay can be interrupted during React strict-mode remounts; retry once.
    })
  }

  await waitForVideoReady(video)

  if (video.paused) {
    await video.play()
  }
}

export function stopCameraStream(stream: MediaStream | null, video?: HTMLVideoElement | null): void {
  if (stream) {
    for (const track of stream.getTracks()) {
      track.stop()
    }
  }
  if (video) {
    video.pause()
    video.srcObject = null
  }
}

function isBarcodeMiss(err: unknown): boolean {
  if (!err || typeof err !== 'object') {
    return false
  }
  if (err instanceof NotFoundException) {
    return true
  }
  const name = 'name' in err ? String(err.name) : ''
  const message = 'message' in err ? String(err.message) : ''
  return name === 'NotFoundException' || message.includes('NotFoundException')
}

function enhanceContrast(context: CanvasRenderingContext2D, width: number, height: number): void {
  if (width <= 0 || height <= 0) {
    return
  }

  const image = context.getImageData(0, 0, width, height)
  const data = image.data
  let min = 255
  let max = 0

  for (let i = 0; i < data.length; i += 4) {
    const gray = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114) | 0
    if (gray < min) {
      min = gray
    }
    if (gray > max) {
      max = gray
    }
  }

  const range = max - min || 1
  for (let i = 0; i < data.length; i += 4) {
    const gray = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114) | 0
    const value = (((gray - min) * 255) / range) | 0
    data[i] = value
    data[i + 1] = value
    data[i + 2] = value
  }

  context.putImageData(image, 0, 0)
}

function decodeWithBinarizer(
  canvas: HTMLCanvasElement,
  reader: Code128Reader,
  binarizer: 'hybrid' | 'global',
): string | null {
  const luminance = new HTMLCanvasElementLuminanceSource(canvas)
  const bitmap = new BinaryBitmap(
    binarizer === 'hybrid' ? new HybridBinarizer(luminance) : new GlobalHistogramBinarizer(luminance),
  )

  try {
    return normalizeScannedCode(reader.decode(bitmap).getText())
  } catch (err) {
    if (isBarcodeMiss(err)) {
      return null
    }
    throw err
  }
}

function drawFrame(
  context: CanvasRenderingContext2D,
  video: HTMLVideoElement,
  width: number,
  height: number,
  cropRatio: number,
): void {
  const sourceWidth = video.videoWidth
  const sourceHeight = video.videoHeight
  if (sourceWidth <= 0 || sourceHeight <= 0) {
    return
  }

  const cropWidth = Math.round(sourceWidth * cropRatio)
  const cropHeight = Math.round(sourceHeight * cropRatio)
  const sx = Math.round((sourceWidth - cropWidth) / 2)
  const sy = Math.round((sourceHeight - cropHeight) / 2)
  context.drawImage(video, sx, sy, cropWidth, cropHeight, 0, 0, width, height)
}

function decodeVideoFrame(video: HTMLVideoElement, canvas: HTMLCanvasElement, reader: Code128Reader): string | null {
  if (video.videoWidth <= 0 || video.videoHeight <= 0) {
    return null
  }

  const context = canvas.getContext('2d', { willReadFrequently: true })
  if (!context) {
    return null
  }

  const strategies: Array<{ crop: number; contrast: boolean; binarizer: 'hybrid' | 'global' }> = [
    { crop: 1, contrast: false, binarizer: 'hybrid' },
    { crop: 1, contrast: true, binarizer: 'hybrid' },
    { crop: 0.75, contrast: false, binarizer: 'hybrid' },
    { crop: 0.75, contrast: true, binarizer: 'global' },
    { crop: 0.55, contrast: true, binarizer: 'global' },
  ]

  for (const strategy of strategies) {
    const width = Math.round(video.videoWidth * strategy.crop)
    const height = Math.round(video.videoHeight * strategy.crop)
    if (width <= 0 || height <= 0) {
      continue
    }

    canvas.width = width
    canvas.height = height
    drawFrame(context, video, width, height, strategy.crop)
    if (strategy.contrast) {
      enhanceContrast(context, width, height)
    }

    const text = decodeWithBinarizer(canvas, reader, strategy.binarizer)
    if (text) {
      return text
    }
  }

  return null
}

export function startContinuousVideoScan(
  video: HTMLVideoElement,
  onCode: (code: string) => void,
  onLog?: (message: string) => void,
): () => void {
  const canvas = document.createElement('canvas')
  const reader = new Code128Reader()
  let active = true
  let scanning = false
  let lastAttempt = 0
  let lastLoggedMiss = 0
  let attempts = 0

  function tick(now: number) {
    if (!active) {
      return
    }

    if (
      !scanning &&
      video.readyState >= HTMLMediaElement.HAVE_ENOUGH_DATA &&
      video.videoWidth > 0 &&
      video.videoHeight > 0 &&
      now - lastAttempt >= 200
    ) {
      scanning = true
      lastAttempt = now
      attempts += 1

      try {
        const text = decodeVideoFrame(video, canvas, reader)
        if (text) {
          onLog?.(`Detected: ${text}`)
          onCode(text)
        } else if (attempts % 10 === 0 && now - lastLoggedMiss >= 2000) {
          lastLoggedMiss = now
          onLog?.(
            `No barcode in frame (${video.videoWidth}×${video.videoHeight}). Fill the view with the EIR barcode.`,
          )
        }
      } catch (err) {
        if (err instanceof Error && !isBarcodeMiss(err)) {
          onLog?.(`Decode error: ${err.message}`)
        }
      } finally {
        scanning = false
      }
    }

    if (active) {
      window.requestAnimationFrame(tick)
    }
  }

  window.requestAnimationFrame(tick)

  return () => {
    active = false
  }
}

export async function startBarcodeScanner(
  deviceId: string,
  video: HTMLVideoElement,
  onCode: (code: string) => void,
  onLog?: (message: string) => void,
): Promise<() => void> {
  const stream = await openCameraStream(deviceId)
  await bindStreamToVideo(stream, video)

  const settings = stream.getVideoTracks()[0]?.getSettings()
  onLog?.(
    `Camera started ${Math.round(settings?.width ?? video.videoWidth)}×${Math.round(settings?.height ?? video.videoHeight)}`,
  )
  onLog?.('Scanner ready. Point the EIR barcode at the camera.')

  const stopScan = startContinuousVideoScan(video, onCode, onLog)

  return () => {
    stopScan()
    stopCameraStream(stream, video)
  }
}
