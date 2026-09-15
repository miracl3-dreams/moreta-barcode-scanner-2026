import { useCallback, useEffect, useRef, useState } from 'react'

export const FEEDBACK_CLEAR_MS = 5_000

/** String message that auto-clears after 5 seconds (CRUD notices, etc.). */
export function useTimedMessage(initial = '', clearAfterMs = FEEDBACK_CLEAR_MS) {
  const [message, setMessageState] = useState(initial)
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const clear = useCallback(() => {
    clearTimeout(timerRef.current)
    setMessageState('')
  }, [])

  const setMessage = useCallback(
    (next: string) => {
      clearTimeout(timerRef.current)
      setMessageState(next)
      if (next) {
        timerRef.current = window.setTimeout(() => {
          setMessageState('')
        }, clearAfterMs)
      }
    },
    [clearAfterMs],
  )

  useEffect(() => () => clearTimeout(timerRef.current), [])

  return [message, setMessage, clear] as const
}

/** Form-level and field validation that auto-clears after 5 seconds. */
export function useFormFeedback(clearAfterMs = FEEDBACK_CLEAR_MS) {
  const [fieldErrors, setFieldErrorsState] = useState<Record<string, string>>({})
  const [formError, setFormErrorState] = useState('')
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const clearFormFeedback = useCallback(() => {
    clearTimeout(timerRef.current)
    setFieldErrorsState({})
    setFormErrorState('')
  }, [])

  const scheduleClear = useCallback(() => {
    clearTimeout(timerRef.current)
    timerRef.current = window.setTimeout(() => {
      setFieldErrorsState({})
      setFormErrorState('')
    }, clearAfterMs)
  }, [clearAfterMs])

  const setFieldErrors = useCallback(
    (fields: Record<string, string>) => {
      setFieldErrorsState(fields)
      if (Object.keys(fields).length > 0) {
        scheduleClear()
      }
    },
    [scheduleClear],
  )

  const setFormError = useCallback(
    (next: string) => {
      setFormErrorState(next)
      if (next) {
        scheduleClear()
      }
    },
    [scheduleClear],
  )

  useEffect(() => () => clearTimeout(timerRef.current), [])

  return {
    fieldErrors,
    formError,
    setFieldErrors,
    setFormError,
    clearFormFeedback,
  }
}
