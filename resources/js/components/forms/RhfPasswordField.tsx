import { useState, type InputHTMLAttributes } from 'react'
import type { FieldError } from 'react-hook-form'
import { EyeIcon, EyeOffIcon } from '@/components/icons'
import { FieldError as FieldErrorText } from '@/components/forms/RhfField'
import { cn } from '@/lib/utils'

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: string
  hint?: string
  error?: FieldError | string
  valid?: boolean
}

export function RhfPasswordField({ label, hint, error, valid, className, ...props }: Props) {
  const [visible, setVisible] = useState(false)

  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">{label}</span>
      <span className="relative block">
        <input
          type={visible ? 'text' : 'password'}
          className={cn(
            'field w-full px-4 py-3 pr-12 text-sm transition-all duration-200',
            error && 'border-danger',
            valid && !error && 'field-valid',
            className,
          )}
          aria-invalid={!!error}
          {...props}
        />
        <button
          type="button"
          onClick={() => setVisible((v) => !v)}
          className="absolute inset-y-0 right-0 flex items-center px-3 text-muted transition hover:text-mint"
          aria-label={visible ? 'Hide password' : 'Show password'}
          tabIndex={-1}
        >
          {visible ? <EyeOffIcon size={18} /> : <EyeIcon size={18} />}
        </button>
      </span>
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
      <FieldErrorText error={error} />
    </label>
  )
}
