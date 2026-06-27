import type { Biller } from '@/lib/billers'

/** A brand-coloured biller tile (MTN, Glo, DStv, …). */
export function BillerLogo({ biller, size = 44 }: { biller: Biller; size?: number }) {
  return (
    <span
      className="flex shrink-0 items-center justify-center rounded-xl font-display font-bold leading-none shadow-sm"
      style={{
        width: size,
        height: size,
        background: biller.bg,
        color: biller.fg,
        fontSize: Math.max(10, size * (biller.short.length > 3 ? 0.26 : 0.34)),
        letterSpacing: '-0.02em',
      }}
      aria-hidden
    >
      {biller.short}
    </span>
  )
}
