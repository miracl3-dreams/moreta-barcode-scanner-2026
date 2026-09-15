import axios from 'axios'

export type ValidationResult = {
  form: string
  fields: Record<string, string>
}

function uniqueMessages(errors?: Record<string, string[]>): string[] {
  const seen = new Set<string>()
  const lines: string[] = []
  if (!errors) {
    return lines
  }
  for (const messages of Object.values(errors)) {
    for (const raw of messages) {
      const text = String(raw ?? '').trim()
      if (!text || seen.has(text)) {
        continue
      }
      seen.add(text)
      lines.push(text)
    }
  }
  return lines
}

function isTruncatedSummary(message: string): boolean {
  return /\(and \d+ more errors?\)$/i.test(message.trim())
}

export function fieldErrorsFromAxios(err: unknown, fallback = 'Unable to save this record.'): ValidationResult {
  if (axios.isAxiosError(err) && err.response?.data) {
    const data = err.response.data as { message?: string; errors?: Record<string, string[]> }
    const fields: Record<string, string> = {}
    if (data.errors) {
      for (const [key, messages] of Object.entries(data.errors)) {
        fields[key] = messages[0] ?? ''
      }
    }
    const lines = uniqueMessages(data.errors)
    const summary = String(data.message ?? '').trim()
    const form =
      lines.join('\n') || (summary && !isTruncatedSummary(summary) ? summary : '') || fallback
    return { form, fields }
  }
  return { form: fallback, fields: {} }
}

export function errorFromAxios(err: unknown, fallback = 'Something went wrong.'): string {
  return fieldErrorsFromAxios(err, fallback).form
}
