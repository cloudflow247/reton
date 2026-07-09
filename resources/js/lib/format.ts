/** Format integer minor units (kobo) as Naira. */
export function ngn(minor: number): string {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 2,
  }).format(minor / 100)
}

/** Format integer minor units (cents) as US Dollars. */
export function usd(minor: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(minor / 100)
}

export function money(minor: number, currency: string): string {
  return currency === 'USD' ? usd(minor) : ngn(minor)
}

/** Parse a Naira input string into integer minor units. */
export function toMinor(value: string): number {
  return Math.round(parseFloat(value || '0') * 100)
}

export function shortDate(iso?: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-NG', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}
