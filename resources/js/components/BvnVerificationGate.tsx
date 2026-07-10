import type { FormEvent } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { CheckIcon, LockIcon, ShieldIcon } from './icons'
import { Button, Field, Pill } from './ui'
import type { SharedProps } from '@/types'

type BvnGateProps = {
  returnTo?: string
}

export function BvnVerificationGate({ returnTo = '/add-money' }: BvnGateProps) {
  const { flash } = usePage<SharedProps>().props
  const form = useForm({
    bvn: '',
    date_of_birth: '',
    identity_consent: false,
    return_to: returnTo,
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.clearErrors()
    form.post('/profile/kyc/tier-2', { preserveScroll: true })
  }

  const bvnDigits = form.data.bvn.replace(/\D/g, '').length
  const canSubmit =
    bvnDigits === 11 && form.data.date_of_birth !== '' && form.data.identity_consent && !form.processing

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="overflow-hidden rounded-3xl border border-mint/25 bg-gradient-to-b from-mint/[0.06] to-surface shadow-lg shadow-mint/5"
    >
      <div className="border-b border-line/80 bg-surface-2/40 px-6 py-5 sm:px-7">
        <div className="flex items-start gap-4">
          <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-mint/15 text-mint ring-1 ring-mint/20">
            <ShieldIcon size={24} />
          </span>
          <div className="min-w-0 flex-1">
            <div className="mb-2">
              <Pill tone="mint">
                <LockIcon size={12} /> Required for funding
              </Pill>
            </div>
            <h3 className="font-display text-xl font-bold tracking-tight">Verify your BVN to continue</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-muted">
              ALATPay requires a verified Bank Verification Number before you can fund your wallet. Your BVN is
              encrypted at rest and only shared with ALATPay for compliance.
            </p>
          </div>
        </div>
      </div>

      <form onSubmit={submit} className="space-y-5 px-6 py-6 sm:px-7">
        {(flash.error || form.errors.bvn) && (
          <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-3 text-sm text-danger">
            {form.errors.bvn ?? flash.error}
          </p>
        )}

        {flash.success && (
          <p className="flex items-center gap-2 rounded-xl border border-mint/25 bg-mint/5 px-4 py-3 text-sm text-mint">
            <CheckIcon size={16} /> {flash.success}
          </p>
        )}

        <Field
          label="11-digit BVN"
          placeholder="22334455667"
          inputMode="numeric"
          autoComplete="off"
          maxLength={11}
          value={form.data.bvn}
          onChange={(e) => form.setData('bvn', e.target.value.replace(/\D/g, '').slice(0, 11))}
          error={form.errors.bvn && !flash.error ? form.errors.bvn : undefined}
          hint={bvnDigits > 0 && bvnDigits < 11 ? `${bvnDigits}/11 digits` : undefined}
        />

        <Field
          label="Date of birth"
          type="date"
          value={form.data.date_of_birth}
          onChange={(e) => form.setData('date_of_birth', e.target.value)}
          error={form.errors.date_of_birth}
          hint="Must match your BVN records exactly"
        />

        <label
          className={`flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition ${
            form.data.identity_consent ? 'border-mint/35 bg-mint/5' : 'border-line bg-surface-2/40 hover:border-mint/20'
          }`}
        >
          <input
            type="checkbox"
            checked={form.data.identity_consent}
            onChange={(e) => form.setData('identity_consent', e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-line text-mint focus:ring-mint/30"
          />
          <span className="text-xs leading-relaxed text-muted">
            I consent to Reton verifying my BVN with our licensed identity partner (Dojah) under NDPR. My BVN is
            encrypted at rest and never stored in plain text.
          </span>
        </label>
        {form.errors.identity_consent && <p className="text-sm text-danger">{form.errors.identity_consent}</p>}

        <Button type="submit" loading={form.processing} disabled={!canSubmit} className="w-full">
          Verify BVN & unlock funding
        </Button>

        <p className="text-center text-[11px] leading-relaxed text-muted">
          Demo: BVN <span className="font-num font-medium text-text">22334455667</span> · DOB{' '}
          <span className="font-num font-medium text-text">1990-05-15</span> · name must match your profile
        </p>
      </form>
    </motion.div>
  )
}
