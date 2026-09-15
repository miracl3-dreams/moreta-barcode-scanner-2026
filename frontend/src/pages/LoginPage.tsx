import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import axios from 'axios'
import { useAuth } from '../auth'
import { fetchLoginContext } from '../api'
import type { LoginFailure } from '../api'
import { MORETA_LOGO, publicUrl } from '../lib/routes'
import { ButtonSpinner } from '../components/PageLoader'
import { FormAlert } from '../components/ui/FormAlert'
import { FieldMessage, fieldControlClassWithError, fieldMessageId } from '../components/ui/FieldMessage'
import { useToast } from '../components/ui/ToastProvider'
import { FEEDBACK_CLEAR_MS } from '../lib/feedback'

function EyeIcon({ open }: { open: boolean }) {
  if (open) {
    return (
      <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z" />
        <circle cx="12" cy="12" r="2.5" />
      </svg>
    )
  }
  return (
    <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8">
      <path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z" />
      <circle cx="12" cy="12" r="2.5" />
      <path d="M4 20 20 4" />
    </svg>
  )
}

export function LoginPage() {
  const { user, loading, login } = useAuth()
  const toast = useToast()
  const [usrname, setUsrname] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState<LoginFailure | null>(null)
  const [fieldErrors, setFieldErrors] = useState<{ usrname?: string; password?: string }>({})
  const [submitting, setSubmitting] = useState(false)
  const [redirecting, setRedirecting] = useState(false)
  const [appTitle, setAppTitle] = useState('Moreta Barcode Scanner')
  const [focusOnLoad] = useState(() => window.matchMedia('(min-width: 768px)').matches)

  useEffect(() => {
    document.title = appTitle
  }, [appTitle])

  useEffect(() => {
    fetchLoginContext()
      .then((ctx) => {
        setAppTitle(ctx.app_title || 'Moreta Barcode Scanner')
      })
      .catch((err) => {
        if (axios.isAxiosError(err) && err.response?.status === 403) {
          const message =
            err.response.data?.message ||
            'Access denied. This system is restricted to users within the local network only.'
          setError({
            code: 'lan_denied',
            message,
          })
          toast.error(message)
        }
      })
  }, [toast])

  useEffect(() => {
    if (!error && !fieldErrors.usrname && !fieldErrors.password) {
      return undefined
    }
    const timer = window.setTimeout(() => {
      setError(null)
      setFieldErrors({})
    }, FEEDBACK_CLEAR_MS)
    return () => window.clearTimeout(timer)
  }, [error, fieldErrors])

  if (redirecting) {
    if (user) {
      return <Navigate to="/dashboard" replace />
    }
    return (
      <div className="flex min-h-dvh items-center justify-center bg-slate-50 px-4">
        <div className="text-center">
          <ButtonSpinner className="mx-auto size-8 border-slate-200 border-t-app-header" />
          <p className="mt-4 text-sm text-slate-600">Redirecting…</p>
        </div>
      </div>
    )
  }

  if (!loading && user) {
    return <Navigate to="/dashboard" replace />
  }

  function validateForm() {
    const nextErrors: { usrname?: string; password?: string } = {}
    if (!usrname.trim()) {
      nextErrors.usrname = 'Username is required.'
    }
    if (!password) {
      nextErrors.password = 'Password is required.'
    }
    setFieldErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    if (!validateForm()) {
      toast.error('Please fill in all required fields.')
      return
    }

    setSubmitting(true)
    try {
      await login(usrname, password)
      toast.success('Authenticated successfully')
      setRedirecting(true)
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.data) {
        const data = err.response.data as LoginFailure
        const nextError = {
          code: data.code || 'invalid_login',
          message: data.message || 'Incorrect password or username',
        }
        setError(nextError)
        toast.error(nextError.message)
      } else if (axios.isAxiosError(err) && (err.code === 'ECONNABORTED' || err.message.includes('timeout'))) {
        const message =
          'Login timed out. The database server is unreachable. Connect to the office Wi‑Fi (192.168.2.x) or fix DB_HOST in backend/.env.'
        setError({ code: 'unavailable', message })
        toast.error(message)
      } else {
        const message = 'Unable to sign in. Check the API is running.'
        setError({ code: 'unavailable', message })
        toast.error(message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  const busy = submitting || loading || redirecting
  const fieldClass =
    'mt-1.5 min-h-11 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-800 outline-none placeholder:text-slate-400 focus:border-app-header focus:ring-2 focus:ring-app-header/15'

  return (
    <div className="relative min-h-dvh overflow-x-hidden font-sans text-slate-900">
      <img
        src={publicUrl('/images/login-bg.png')}
        alt=""
        className="absolute inset-0 size-full object-cover object-center blur-[2px] scale-105"
      />
      <div className="absolute inset-0 bg-slate-950/35" />

      <div className="relative flex min-h-dvh items-center justify-center px-4 pt-[max(3.5rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-6 sm:pt-16 sm:pb-16">
        <div className="relative w-full max-w-4xl">
          <div className="absolute left-1/2 top-0 z-10 flex size-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white px-3 shadow-md md:size-24">
            <img
              src={publicUrl(MORETA_LOGO)}
              alt="Moreta Shipping Lines"
              className="h-14 w-auto object-contain md:h-16"
            />
          </div>

          <div className="grid overflow-hidden rounded-2xl bg-slate-100 shadow-[0_20px_50px_rgba(0,0,0,0.28)] md:grid-cols-2">
            <form
              className="flex flex-col px-5 pb-7 pt-14 sm:px-8 sm:pb-8 md:px-10 md:pt-16 lg:px-12"
              onSubmit={onSubmit}
              name="myform"
              noValidate
            >
              <h1 className="text-center text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">User Login</h1>

              {error ? (
                <div className="mt-6">
                  <FormAlert>
                    <p>{error.message}</p>
                  </FormAlert>
                </div>
              ) : null}

              <div className="mt-7 space-y-5 sm:mt-8">
                <div>
                  <label htmlFor="usrname" className="block text-sm font-medium text-slate-700">
                    Username <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="usrname"
                    name="usrname"
                    placeholder="Username"
                    autoComplete="username"
                    autoFocus={focusOnLoad}
                    value={usrname}
                    onChange={(e) => {
                      setUsrname(e.target.value)
                      if (fieldErrors.usrname) {
                        setFieldErrors((current) => ({ ...current, usrname: undefined }))
                      }
                    }}
                    aria-invalid={fieldErrors.usrname ? true : undefined}
                    aria-describedby={fieldErrors.usrname ? fieldMessageId('usrname') : undefined}
                    className={fieldControlClassWithError(fieldClass, Boolean(fieldErrors.usrname))}
                  />
                  {fieldErrors.usrname ? (
                    <FieldMessage id={fieldMessageId('usrname')}>{fieldErrors.usrname}</FieldMessage>
                  ) : null}
                </div>
                <div>
                  <label htmlFor="txtusrpwd" className="block text-sm font-medium text-slate-700">
                    Password <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      id="txtusrpwd"
                      name="txtusrpwd"
                      placeholder="Password"
                      autoComplete="current-password"
                      value={password}
                      onChange={(e) => {
                        setPassword(e.target.value)
                        if (fieldErrors.password) {
                          setFieldErrors((current) => ({ ...current, password: undefined }))
                        }
                      }}
                      aria-invalid={fieldErrors.password ? true : undefined}
                      aria-describedby={fieldErrors.password ? fieldMessageId('txtusrpwd') : undefined}
                      className={fieldControlClassWithError(`${fieldClass} pr-12`, Boolean(fieldErrors.password))}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((open) => !open)}
                      className="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center text-slate-400 hover:text-slate-600"
                      aria-label={showPassword ? 'Hide password' : 'Show password'}
                    >
                      <EyeIcon open={showPassword} />
                    </button>
                  </div>
                  {fieldErrors.password ? (
                    <FieldMessage id={fieldMessageId('txtusrpwd')}>{fieldErrors.password}</FieldMessage>
                  ) : null}
                </div>
              </div>

              <button
                type="submit"
                name="cmdlogin"
                disabled={busy}
                className="mt-7 inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-brand-navy py-2.5 text-sm font-semibold text-white hover:bg-brand-navy-hover disabled:cursor-default disabled:opacity-60 sm:mt-8"
              >
                {busy ? <ButtonSpinner /> : null}
                Sign in
              </button>

              <p className="mt-6 text-center text-xs text-slate-500 md:hidden">
                Developed by <span className="font-semibold">LSTV</span>
              </p>
            </form>

            <aside className="relative hidden min-h-[420px] flex-col items-center justify-between bg-app-header/90 px-8 py-16 text-center text-white md:flex lg:px-10">
              <div
                className="absolute inset-0 bg-cover bg-center opacity-30"
                style={{ backgroundImage: `url('${publicUrl('/images/login-bg.png')}')` }}
              />
              <div className="relative flex flex-1 flex-col items-center justify-center">
                <h2 className="text-3xl font-bold tracking-tight">Welcome to MORETA</h2>
                <p className="mt-5 max-w-sm text-sm leading-relaxed text-white/90">
                  Sign in to Moreta Barcode Scanner to scan EIR barcodes at the gate. Use your staff
                  user name and the same password as Moreta.
                </p>
                <p className="mt-4 max-w-sm text-sm font-semibold text-white">On Time, All the Time!</p>
              </div>
              <p className="relative text-xs text-white/80">
                Developed by <span className="font-semibold">LSTV</span>
              </p>
            </aside>
          </div>
        </div>
      </div>
    </div>
  )
}
