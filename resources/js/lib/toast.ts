export type ToastTone = 'success' | 'error' | 'info'

export type ToastItem = {
  id: string
  tone: ToastTone
  message: string
  createdAt: number
}

type Listener = (toasts: ToastItem[]) => void

const MAX_VISIBLE = 4
const DEFAULT_TTL_MS = 4200

let toasts: ToastItem[] = []
const listeners = new Set<Listener>()
const timers = new Map<string, ReturnType<typeof setTimeout>>()

function emit(): void {
  const snapshot = [...toasts]
  listeners.forEach((listener) => listener(snapshot))
}

function dismiss(id: string): void {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
  toasts = toasts.filter((t) => t.id !== id)
  emit()
}

function push(tone: ToastTone, message: string, ttlMs = DEFAULT_TTL_MS): string {
  const trimmed = message.trim()
  if (!trimmed) {
    return ''
  }

  // Dedupe identical message already on screen.
  const existing = toasts.find((t) => t.tone === tone && t.message === trimmed)
  if (existing) {
    return existing.id
  }

  const id = `t-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  toasts = [{ id, tone, message: trimmed, createdAt: Date.now() }, ...toasts].slice(0, MAX_VISIBLE)
  emit()

  if (ttlMs > 0) {
    timers.set(
      id,
      setTimeout(() => dismiss(id), ttlMs),
    )
  }

  return id
}

export const toast = {
  success: (message: string, ttlMs?: number) => push('success', message, ttlMs),
  error: (message: string, ttlMs?: number) => push('error', message, ttlMs ?? 5600),
  info: (message: string, ttlMs?: number) => push('info', message, ttlMs),
  dismiss,
  clear: () => {
    timers.forEach((timer) => clearTimeout(timer))
    timers.clear()
    toasts = []
    emit()
  },
  subscribe: (listener: Listener) => {
    listeners.add(listener)
    listener([...toasts])
    return () => listeners.delete(listener)
  },
}
