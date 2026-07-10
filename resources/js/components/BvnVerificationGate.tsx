import type { FormEvent } from 'react'
import { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { CheckIcon, LockIcon, ShieldIcon } from './icons'
import { Button, Field, Pill } from './ui'
import type { SharedProps } from '@/types'

type BvnGateProps = {
  returnTo?: string
  pendingOtp?: boolean
  otpHint?: string | null
  provider?: string
}

export function BvnVerificationGate({
  returnTo = '/add-money',
  pendingOtp = false,
  otpHint = null,
  provider = 'alatpay',
}: BvnGateProps) {
  const { flash } = usePage<SharedProps>().props
  const [step, setStep] = useState<'bvn' | 'otp'>(pendingOtp ? 'otp' : 'bvn')

  const bvnForm = useForm({
    bvn: '',
    date_of_birth: '',
    identity_consent: false,
    return_to: returnTo,
  })

  const otpForm = useForm({
    otp: '',
    return_to: returnTo,
  })

  function submitBvn(e: FormEvent) {
    e.preventDefault()
    bvnForm.clearErrors()
    bvnForm.post('/profile/kyc/tier-2', {
      preserveScroll: true,
      onSuccess: () => setStep('otp'),
    })
  }

  function submitOtp(e: FormEvent) {
    e.preventDefault()
    otpForm.clearErrors()
    otpForm.post('/profile/kyc/tier-2/confirm', { preserveScroll: true })
  }

  const bvnDigits = bvnForm.data.bvn.replace(/\D/g, '').length
  const canSubmitBvn =
    bvnDigits === 11 && bvnForm.data.date_of_birth !== '' && bvnForm.data.identity_consent && !bvnForm.processing
  const canSubmitOtp = otpForm.data.otp.length >= 4 && !otpForm.processing
  const usesAlatpay = provider === 'alatpay'

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="overflow-hidden rounded-3xl border border-mint/25 bg-gradient-to-b from-mint/[0.06] to-surface shadow-lg shadow-mint/5"
    >
      <div className="border-b border-line/80 bg-surface-2/40 px-5 py-5 sm:px-7">
        <div className="flex items-start gap-3.5 sm:gap-4">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-mint/15 text-mint ring-1 ring-mint/20 sm:h-12 sm:w-12">
            <ShieldIcon size={22} />
          </span>
          <div className="min-w-0 flex-1">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <Pill tone="mint">
                <LockIcon size={12} /> Required for funding
              </Pill>
              {step === 'otp' && (
                <Pill tone="amber">Step 2 of 2</Pill>
              )}
            </div>
            <h3 className="font-display text-lg font-bold tracking-tight sm:text-xl">
              {step === 'otp' ? 'Enter your verification code' : 'Verify your BVN to continue'}
            </h3>
            <p className="mt-1.5 text-sm leading-relaxed text-muted">
              {step === 'otp'
                ? otpHint ??
                  'ALATPay sent a one-time code to the phone linked to your BVN. Enter it below to unlock wallet funding.'
                : usesAlatpay
                  ? 'ALATPay verifies your BVN and sends a one-time code to your registered phone. Your BVN is encrypted at rest.'
                  : 'Verify your identity before funding your wallet. Your BVN is encrypted at rest.'}
            </p>
          </div>
        </div>
      </div>

      <AnimatePresence mode="wait">
        {step === 'bvn' ? (
          <motion.form
            key="bvn"
            initial={{ opacity: 0, x: -8 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: 8 }}
            onSubmit={submitBvn}
            className="space-y-4 px-5 py-5 sm:space-y-5 sm:px-7 sm:py-6"
          >
            {(flash.error || bvnForm.errors.bvn) && (
              <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-3 text-sm text-danger">
                {bvnForm.errors.bvn ?? flash.error}
              </p>
            )}

            {flash.success && !pendingOtp && (
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
              value={bvnForm.data.bvn}
              onChange={(e) => bvnForm.setData('bvn', e.target.value.replace(/\D/g, '').slice(0, 11))}
              error={bvnForm.errors.bvn && !flash.error ? bvnForm.errors.bvn : undefined}
              hint={bvnDigits > 0 && bvnDigits < 11 ? `${bvnDigits}/11 digits` : undefined}
            />

            <Field
              label="Date of birth"
              type="date"
              value={bvnForm.data.date_of_birth}
              onChange={(e) => bvnForm.setData('date_of_birth', e.target.value)}
              error={bvnForm.errors.date_of_birth}
              hint="For compliance records"
            />

            <label
              className={`flex cursor-pointer items-start gap-3 rounded-2xl border p-3.5 transition sm:p-4 ${
                bvnForm.data.identity_consent
                  ? 'border-mint/35 bg-mint/5'
                  : 'border-line bg-surface-2/40 hover:border-mint/20'
              }`}
            >
              <input
                type="checkbox"
                checked={bvnForm.data.identity_consent}
                onChange={(e) => bvnForm.setData('identity_consent', e.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-line text-mint focus:ring-mint/30"
              />
              <span className="text-xs leading-relaxed text-muted">
                I consent to Reton verifying my BVN with {usesAlatpay ? 'ALATPay' : 'our licensed identity partner'}{' '}
                under NDPR. My BVN is encrypted at rest and never stored in plain text.
              </span>
            </label>
            {bvnForm.errors.identity_consent && (
              <p className="text-sm text-danger">{bvnForm.errors.identity_consent}</p>
            )}

            <Button type="submit" loading={bvnForm.processing} disabled={!canSubmitBvn} className="w-full">
              {usesAlatpay ? 'Send verification code' : 'Verify BVN & unlock funding'}
            </Button>

            {usesAlatpay && (
              <p className="text-center text-[11px] leading-relaxed text-muted">
                Demo OTP: <span className="font-num font-medium text-text">123456</span>
              </p>
            )}
          </motion.form>
        ) : (
          <motion.form
            key="otp"
            initial={{ opacity: 0, x: 8 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -8 }}
            onSubmit={submitOtp}
            className="space-y-4 px-5 py-5 sm:space-y-5 sm:px-7 sm:py-6"
          >
            {flash.success && (
              <p className="flex items-center gap-2 rounded-xl border border-mint/25 bg-mint/5 px-4 py-3 text-sm text-mint">
                <CheckIcon size={16} /> {flash.success}
              </p>
            )}

            {(flash.error || otpForm.errors.otp) && (
              <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-3 text-sm text-danger">
                {otpForm.errors.otp ?? flash.error}
              </p>
            )}

            <Field
              label="Verification code"
              placeholder="123456"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={8}
              value={otpForm.data.otp}
              onChange={(e) => otpForm.setData('otp', e.target.value.replace(/\D/g, '').slice(0, 8))}
              error={otpForm.errors.otp}
              hint="Check the SMS from ALATPay"
            />

            <Button type="submit" loading={otpForm.processing} disabled={!canSubmitOtp} className="w-full">
              Confirm BVN & unlock funding
            </Button>

            <button
              type="button"
              onClick={() => setStep('bvn')}
              className="w-full text-sm text-muted transition hover:text-mint"
            >
              ← Use a different BVN
            </button>
          </motion.form>
        )}
      </AnimatePresence>
    </motion.div>
  )
}
