import { Component, type ErrorInfo, type ReactNode, useEffect, useRef, useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { CheckIcon } from '@/components/icons'
import { toast, type ToastItem, type ToastTone } from '@/lib/toast'
import type { SharedProps } from '@/types'
import { cn } from '@/lib/utils'

const toneClass: Record<ToastTone, string> = {
  success: 'border-mint/30 bg-[#0b7a57] text-white shadow-[0_12px_32px_-12px_rgba(11,122,87,0.55)]',
  error: 'border-danger/30 bg-[#b42318] text-white shadow-[0_12px_32px_-12px_rgba(180,35,24,0.5)]',
  info: 'border-line bg-[#122a22] text-white shadow-[0_12px_32px_-12px_rgba(18,42,34,0.45)]',
}

function firstError(errors: Record<string, unknown> | undefined): string | null {
  if (!errors) {
    return null
  }

  for (const value of Object.values(errors)) {
    if (typeof value === 'string' && value.trim() !== '') {
      return value
    }
    if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim() !== '') {
      return value[0]
    }
  }

  return null
}

class ToastBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError(): { failed: boolean } {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('Reton toast crash', error, info.componentStack)
  }

  render() {
    if (this.state.failed) {
      return null
    }

    return this.props.children
  }
}

function ToastHostInner() {
  const page = usePage<SharedProps & { errors?: Record<string, unknown> }>()
  const flash = page.props.flash
  const errors = page.props.errors
  const [items, setItems] = useState<ToastItem[]>([])
  const seenFlash = useRef<string>('')
  const seenError = useRef<string>('')

  useEffect(() => toast.subscribe(setItems), [])

  useEffect(() => {
    const key = `${flash?.success ?? ''}|${flash?.error ?? ''}`
    if (key === '|' || key === seenFlash.current) {
      return
    }
    seenFlash.current = key
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
  }, [flash?.success, flash?.error])

  useEffect(() => {
    const message = firstError(errors)
    if (!message || message === seenError.current) {
      return
    }
    seenError.current = message
    toast.error(message)
  }, [errors])

  useEffect(() => {
    return router.on('invalid', (event) => {
      const status = event.detail.response?.status
      if (status && status >= 500) {
        toast.error('Something went wrong on our side. Please try again.')
      }
    })
  }, [])

  return (
    <div
      className="pointer-events-none fixed inset-x-0 top-0 z-[100] flex flex-col items-center gap-2 px-3 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-4"
      aria-live="assertive"
      aria-relevant="additions"
    >
      <AnimatePresence initial={false}>
        {items.map((item) => (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, y: -12, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -8, scale: 0.98 }}
            transition={{ duration: 0.14, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
              'pointer-events-auto flex w-full max-w-md items-start gap-2.5 rounded-2xl border px-3.5 py-3 text-sm font-medium leading-snug',
              toneClass[item.tone],
            )}
            role="status"
          >
            {item.tone === 'success' ? (
              <CheckIcon size={16} className="mt-0.5 shrink-0 opacity-90" />
            ) : (
              <span className="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-white/20 text-[10px] font-bold">
                !
              </span>
            )}
            <p className="min-w-0 flex-1">{item.message}</p>
            <button
              type="button"
              onClick={() => toast.dismiss(item.id)}
              className="shrink-0 rounded-lg px-1.5 py-0.5 text-xs font-semibold text-white/80 transition hover:bg-white/10 hover:text-white"
              aria-label="Dismiss"
            >
              Close
            </button>
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  )
}

/**
 * Global, instant feedback layer — must render inside Inertia's <App> tree
 * so usePage() has context. Isolated so toast failures never blank the app.
 */
export function ToastHost() {
  return (
    <ToastBoundary>
      <ToastHostInner />
    </ToastBoundary>
  )
}
