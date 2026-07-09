import type { Biller } from '@/lib/billers'
import { BillerBrandIcon } from '@/components/biller-icons'

/** Brand-accurate biller tile with SVG mark (MTN, T2, DStv, discos, …). */
export function BillerLogo({
  biller,
  size = 44,
  round,
}: {
  biller: Pick<Biller, 'brand' | 'code' | 'bg'> | { brand?: string; code: string; bg?: string }
  size?: number
  round?: boolean
}) {
  const brand = biller.brand ?? (biller.code === '9mobile' ? 't2' : biller.code)

  return (
    <span
      className={`inline-flex shrink-0 transition-transform ${round ? 'rounded-full' : 'rounded-xl'}`}
      style={{
        filter: `drop-shadow(0 4px 12px ${(biller.bg ?? '#64748b')}55)`,
      }}
    >
      <BillerBrandIcon brand={brand} size={size} round={round} />
    </span>
  )
}
