import type { InputHTMLAttributes } from 'react'
import type { FieldError } from 'react-hook-form'
import { cn } from '@/lib/utils'

export function FieldError({ error }: { error?: FieldError | string }) {
  if (!error) return null
  const message = typeof error === 'string' ? error : error.message
  if (!message) return null
  return <p className="mt-1.5 text-sm text-danger">{message}</p>
}

type RhfFieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  hint?: string
  error?: FieldError | string
}

export function RhfField({ label, hint, error, className, ...props }: RhfFieldProps) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">{label}</span>
      <input
        className={cn('field w-full px-4 py-3 text-sm', error && 'border-danger', className)}
        aria-invalid={!!error}
        {...props}
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
      <FieldError error={error} />
    </label>
  )
}
