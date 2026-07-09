import type { FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { ShieldIcon } from './icons'
import { Button, Field } from './ui'

export function BvnVerificationGate() {
  const form = useForm({
    bvn: '',
    date_of_birth: '',
    identity_consent: false,
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.post('/profile/kyc/tier-2', { preserveScroll: true })
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="card shield-glow overflow-hidden border-mint/20 p-6 sm:p-7"
    >
      <div className="flex items-start gap-4">
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-mint/10 text-mint">
          <ShieldIcon size={24} />
        </span>
        <div className="min-w-0">
          <h3 className="font-display text-lg font-bold tracking-tight">Verify your BVN to continue</h3>
          <p className="mt-1.5 text-sm leading-relaxed text-muted">
            ALATPay requires a verified Bank Verification Number before you can fund your wallet or open a deposit
            account. Your BVN is encrypted and only shared with ALATPay for compliance.
          </p>
        </div>
      </div>

      <form onSubmit={submit} className="mt-6 space-y-4">
        <Field
          label="11-digit BVN"
          placeholder="22334455667"
          inputMode="numeric"
          maxLength={11}
          value={form.data.bvn}
          onChange={(e) => form.setData('bvn', e.target.value.replace(/\D/g, '').slice(0, 11))}
        />
        {form.errors.bvn && <p className="text-sm text-danger">{form.errors.bvn}</p>}

        <Field
          label="Date of birth"
          type="date"
          value={form.data.date_of_birth}
          onChange={(e) => form.setData('date_of_birth', e.target.value)}
        />
        {form.errors.date_of_birth && <p className="text-sm text-danger">{form.errors.date_of_birth}</p>}

        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4">
          <input
            type="checkbox"
            checked={form.data.identity_consent}
            onChange={(e) => form.setData('identity_consent', e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-line text-mint focus:ring-mint/30"
          />
          <span className="text-xs leading-relaxed text-muted">
            I consent to Reton verifying my BVN with our licensed identity partner (Dojah) under NDPR. My BVN is
            encrypted at rest.
          </span>
        </label>
        {form.errors.identity_consent && <p className="text-sm text-danger">{form.errors.identity_consent}</p>}

        <Button type="submit" loading={form.processing} className="w-full">
          Verify BVN & unlock funding
        </Button>
      </form>
    </motion.div>
  )
}
