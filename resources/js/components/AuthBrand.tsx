import { Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { useId } from 'react'
import { cn } from '@/lib/utils'

function ShieldMark({ size = 40, className }: { size?: number; className?: string }) {
  const uid = useId().replace(/:/g, '')
  const gradId = `reton-logo-${uid}`

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fill="none"
      aria-hidden
      className={className}
    >
      <defs>
        <linearGradient id={gradId} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#0e7e5c" />
          <stop offset="1" stopColor="#094f39" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="9" fill={`url(#${gradId})`} />
      <path
        d="M16 4.8l8.4 2.9v6.7c0 5.3-3.6 9-8.4 10.8-4.8-1.8-8.4-5.5-8.4-10.8V7.7L16 4.8z"
        fill="#ffffff"
        fillOpacity="0.14"
        stroke="#ffffff"
        strokeWidth="1.8"
        strokeLinejoin="round"
      />
      <path
        d="M19.4 13.9a3.9 3.9 0 1 0 .5 4.8"
        fill="none"
        stroke="#ffffff"
        strokeWidth="2.2"
        strokeLinecap="round"
      />
      <path
        d="M19.9 10.2l.2 3.9-3.8-.4"
        fill="none"
        stroke="#ffffff"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export function AuthBrand({
  size = 'md',
  layout = 'horizontal',
  href = '/',
  animate = true,
  className,
}: {
  size?: 'sm' | 'md' | 'lg'
  layout?: 'horizontal' | 'stacked'
  href?: string
  animate?: boolean
  className?: string
}) {
  const markSize = size === 'sm' ? 36 : size === 'lg' ? 52 : 44
  const textClass =
    size === 'sm' ? 'text-lg' : size === 'lg' ? 'text-[1.65rem]' : 'text-xl'

  const content = (
    <span
      className={cn(
        'inline-flex items-center',
        layout === 'stacked' ? 'flex-col gap-2.5 text-center' : 'flex-row gap-3',
        className,
      )}
    >
      <span className="relative inline-flex shrink-0 items-center justify-center">
        <motion.span
          aria-hidden
          className="auth-brand-ring absolute inset-[-5px] rounded-[14px]"
          animate={animate ? { opacity: [0.35, 0.7, 0.35], scale: [0.98, 1.04, 0.98] } : undefined}
          transition={animate ? { duration: 4.5, repeat: Infinity, ease: 'easeInOut' } : undefined}
        />
        <motion.span
          className="relative flex items-center justify-center rounded-[13px] shadow-[0_12px_28px_-16px_rgba(9,79,57,0.55)]"
          animate={animate ? { y: [0, -2, 0] } : undefined}
          transition={animate ? { duration: 5, repeat: Infinity, ease: 'easeInOut' } : undefined}
        >
          <ShieldMark size={markSize} />
        </motion.span>
      </span>
      <span className={cn('font-display font-bold tracking-tight text-text', textClass)}>
        Reton
      </span>
    </span>
  )

  if (href && href.length > 0) {
    return (
      <Link href={href} className="inline-flex w-fit focus:outline-none" aria-label="Reton home">
        {content}
      </Link>
    )
  }

  return content
}

/** Re-export a standalone mark for inline use (icons, emails, etc.). */
export function Logo({ size = 32, className }: { size?: number; className?: string }) {
  return <ShieldMark size={size} className={className} />
}

export function Wordmark({
  light,
  size = 32,
  showText = true,
}: {
  light?: boolean
  size?: number
  showText?: boolean
}) {
  return (
    <span className="inline-flex items-center gap-2.5">
      <ShieldMark size={size} />
      {showText && (
        <span className={cn('font-display text-xl font-bold tracking-tight', light ? 'text-white' : 'text-text')}>
          Reton
        </span>
      )}
    </span>
  )
}
