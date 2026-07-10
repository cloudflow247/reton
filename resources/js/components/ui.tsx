import { useState, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode } from 'react'
import { motion } from 'framer-motion'
import { CheckIcon, CopyIcon } from './icons'

export { Logo, Wordmark } from './AuthBrand'

export function Modal({
  title,
  onClose,
  children,
  wide,
}: {
  title: string
  onClose: () => void
  children: ReactNode
  wide?: boolean
}) {
  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      className="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-4 backdrop-blur-sm sm:items-center"
      onClick={onClose}
    >
      <motion.div
        initial={{ opacity: 0, scale: 0.96, y: 12 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        transition={{ type: 'spring', stiffness: 320, damping: 26 }}
        className={`card shield-glow max-h-[min(92vh,820px)] w-full overflow-y-auto p-6 ${wide ? 'max-w-lg' : 'max-w-md'}`}
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
      >
        <div className="mb-4 flex items-center justify-between">
          <h3 className="font-display text-lg font-bold tracking-tight">{title}</h3>
          <button onClick={onClose} className="text-muted hover:text-text" aria-label="Close">
            ✕
          </button>
        </div>
        {children}
      </motion.div>
    </motion.div>
  )
}

/** A naira amount input with a ₦ prefix and quick-pick chips. */
export function AmountField({
  value,
  onChange,
  invalid,
  quick = [1000, 2000, 5000, 10000],
  label = 'Amount',
  hideLabel = false,
}: {
  value: string
  onChange: (v: string) => void
  invalid?: boolean
  quick?: number[]
  label?: string
  hideLabel?: boolean
}) {
  return (
    <div>
      {!hideLabel && (
        <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">{label}</span>
      )}
      <div
        className={`field flex items-center px-4 focus-within:border-mint focus-within:ring-2 focus-within:ring-mint/15 ${
          invalid ? '!border-danger' : ''
        }`}
      >
        <span className="font-num text-lg text-muted">₦</span>
        <input
          type="number"
          step="0.01"
          min="0.01"
          placeholder="0.00"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full bg-transparent px-2 py-3 font-num text-lg outline-none"
        />
      </div>
      <div className="mt-2 flex flex-wrap gap-2">
        {quick.map((q) => (
          <button
            key={q}
            type="button"
            onClick={() => onChange(String(q))}
            className="rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-muted transition hover:border-mint/40 hover:text-mint"
          >
            ₦{q.toLocaleString()}
          </button>
        ))}
      </div>
    </div>
  )
}

/** A labelled value with a one-tap copy button (account details, references). */
export function CopyRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  const [copied, setCopied] = useState(false)
  return (
    <div className="flex items-center justify-between gap-3 py-3.5">
      <div className="min-w-0">
        <div className="text-xs text-muted">{label}</div>
        <div className={`truncate text-sm font-semibold ${mono ? 'font-num tracking-wider' : ''}`}>{value}</div>
      </div>
      <button
        type="button"
        onClick={() => {
          navigator.clipboard.writeText(value)
          setCopied(true)
          setTimeout(() => setCopied(false), 1400)
        }}
        className="flex shrink-0 items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-xs text-muted transition hover:border-mint/40 hover:text-mint"
      >
        {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
        {copied ? 'Copied' : 'Copy'}
      </button>
    </div>
  )
}

type BtnProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  loading?: boolean
}

export function Button({ variant = 'primary', loading, children, className = '', ...props }: BtnProps) {
  const styles = {
    primary: 'bg-mint text-white shadow-[0_10px_24px_-14px_rgba(9,79,57,0.65)] hover:bg-mint-strong',
    secondary: 'border border-line bg-surface text-text hover:border-mint/35 hover:text-mint',
    ghost: 'bg-surface text-text border border-line hover:bg-surface-2',
    danger: 'bg-danger/10 text-danger border border-danger/25 hover:bg-danger/15',
  }[variant]
  return (
    <button
      className={`btn px-5 py-2.5 text-sm ${styles} ${className}`}
      disabled={loading || props.disabled}
      {...props}
    >
      {loading ? 'Working…' : children}
    </button>
  )
}

export function Field({
  label,
  hint,
  error,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: string; hint?: string; error?: string }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">{label}</span>
      <input className={`field w-full min-w-0 px-4 py-3 text-sm ${error ? '!border-danger' : ''}`} {...props} />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
      {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
    </label>
  )
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`card p-5 ${className}`}>{children}</div>
}

export function Pill({ tone, children }: { tone: 'mint' | 'amber' | 'muted' | 'danger'; children: ReactNode }) {
  const styles = {
    mint: 'bg-mint/10 text-mint',
    amber: 'bg-amber/12 text-amber',
    muted: 'bg-surface-2 text-muted',
    danger: 'bg-danger/10 text-danger',
  }[tone]
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${styles}`}>
      {children}
    </span>
  )
}
