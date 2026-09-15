import { useEffect, useRef } from 'react'
import { useToast } from './ToastProvider'
import { FEEDBACK_CLEAR_MS } from '../../lib/feedback'

type FeedbackToastProps = {
  message: string
  variant?: 'success' | 'error' | 'info'
  onClear?: () => void
  clearAfterMs?: number
}

/** Shows a toast when `message` becomes non-empty, then clears source state after 5 seconds. */
export function FeedbackToast({
  message,
  variant = 'success',
  onClear,
  clearAfterMs = FEEDBACK_CLEAR_MS,
}: FeedbackToastProps) {
  const toast = useToast()
  const lastMessage = useRef('')

  useEffect(() => {
    if (message && message !== lastMessage.current) {
      toast[variant]({ title: message, duration: clearAfterMs })
      lastMessage.current = message

      const timer = window.setTimeout(() => {
        onClear?.()
        lastMessage.current = ''
      }, clearAfterMs)

      return () => window.clearTimeout(timer)
    }

    if (!message) {
      lastMessage.current = ''
    }

    return undefined
  }, [message, toast, variant, onClear, clearAfterMs])

  return null
}
