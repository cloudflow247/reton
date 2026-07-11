import type { CountryDialCode } from '@/types'
import { cn } from '@/lib/utils'
import type { FieldError } from 'react-hook-form'
import { FieldError as FieldErrorText } from '@/components/forms/RhfField'

type Props = {
  countries: CountryDialCode[]
  countryIso: string
  phoneNational: string
  onCountryChange: (iso: string, dial: string) => void
  onNationalChange: (value: string) => void
  nationalRegister: object
  error?: FieldError | string
  serverError?: string
  valid?: boolean
  autoFocus?: boolean
}

export function PhoneWithCountryField({
  countries,
  countryIso,
  phoneNational,
  onCountryChange,
  onNationalChange,
  nationalRegister,
  error,
  valid,
  autoFocus,
}: Props) {
  const selected = countries.find((c) => c.iso === countryIso) ?? countries[0]

  return (
    <div className="block">
      <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Mobile number</span>
      <div
        className={cn(
          'field flex items-stretch overflow-hidden p-0 transition-all duration-200',
          error && 'border-danger',
          valid && !error && 'field-valid',
        )}
      >
        <label className="sr-only" htmlFor="register-country">
          Country code
        </label>
        <select
          id="register-country"
          value={selected?.iso ?? 'NG'}
          onChange={(e) => {
            const next = countries.find((c) => c.iso === e.target.value)
            if (next) {
              onCountryChange(next.iso, next.dial)
            }
          }}
          className="max-w-[7.5rem] shrink-0 border-0 border-r border-line bg-surface-2/50 px-2 py-3 text-sm outline-none sm:max-w-[9.5rem]"
          aria-label="Country calling code"
        >
          {countries.map((c) => (
            <option key={c.iso} value={c.iso}>
              {c.iso} +{c.dial}
            </option>
          ))}
        </select>
        <input
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          autoFocus={autoFocus}
          placeholder={selected?.iso === 'NG' ? '801 234 5678' : 'Mobile number'}
          className="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-sm outline-none"
          aria-invalid={!!error}
          {...nationalRegister}
          value={phoneNational}
          onChange={(e) => {
            const digits = e.target.value.replace(/[^\d\s\-]/g, '')
            onNationalChange(digits)
          }}
        />
      </div>
      <span className="mt-1 block text-xs text-muted">
        Use the number linked to your identity documents (CBN KYC). Stored as +{selected?.dial}
        {phoneNational.replace(/\D/g, '').replace(/^0/, '') || '…'}.
      </span>
      <FieldErrorText error={error} />
    </div>
  )
}
