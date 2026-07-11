import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AuthStepIndicator } from '@/components/AuthStepIndicator'
import { AuthBrand } from '@/components/AuthBrand'
import { Button, Field } from '@/components/ui'
import {
  ArrowRightIcon,
  CheckIcon,
  LockIcon,
  PlusIcon,
  ShieldIcon,
  SparkleIcon,
} from '@/components/icons'
import { PoweredByAlatInline } from '@/components/PoweredByAlat'
import type { PageProps } from '@/types'

type OnboardingProps = PageProps<{
  initialStep: number
}>

const slide = {
  initial: { opacity: 0, y: 16 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -12 },
}

export default function Onboarding() {
  const { auth, flash, initialStep } = usePage<OnboardingProps>().props
  const firstName = (auth.user?.name ?? 'there').split(' ')[0]
  const [step, setStep] = useState(initialStep ?? 0)
  const pinForm = useForm({ pin: '', pin_confirmation: '' })

  useEffect(() => {
    setStep(initialStep ?? 0)
  }, [initialStep])

  function submitPin(e: FormEvent) {
    e.preventDefault()
    pinForm.clearErrors()
    pinForm.transform((data) => ({ ...data, from_onboarding: true }))
    pinForm.post('/pin', {
      preserveScroll: true,
      onError: () => {
        /* errors render inline via pinForm.errors */
      },
    })
  }

  return (
    <div className="relative min-h-dvh overflow-hidden">
      <div className="aurora" aria-hidden />
      <div className="relative mx-auto flex min-h-dvh max-w-lg flex-col px-4 py-8 pb-10 sm:px-6 sm:py-12">
        <Head title="Welcome to Reton" />

        <div className="flex flex-col items-center text-center">
          <AuthBrand size="md" layout="stacked" />
        </div>

        <div className="card shield-glow mt-6 flex-1 p-5 sm:mt-8 sm:p-7">
          <AuthStepIndicator step={step} total={3} />

          {flash.success && step !== 2 && (
            <p className="mb-4 mt-4 rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
          )}

          {flash.error && (
            <p className="mb-4 mt-4 rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
          )}

          <AnimatePresence mode="wait">
            {step === 0 && (
              <motion.div key="welcome" {...slide} transition={{ duration: 0.25 }}>
                <div className="inline-flex items-center gap-1.5 rounded-full border border-mint/20 bg-mint/[0.07] px-3 py-1.5 text-xs font-semibold text-mint">
                  <SparkleIcon size={14} /> You're in, {firstName}
                </div>
                <h1 className="mt-4 font-display text-2xl font-bold tracking-tight">Welcome to trust-first payments</h1>
                <p className="mt-2 text-sm leading-relaxed text-muted">
                  Reton protects every transfer with Callback Protection, wrong-transfer recovery, and live fraud checks.
                  Two quick steps and your wallet is ready.
                </p>
                <ul className="mt-6 space-y-3 text-left">
                  {[
                    { Icon: ShieldIcon, t: 'Callback Protection', d: 'Hold funds until you confirm delivery.' },
                    { Icon: LockIcon, t: 'Transaction PIN', d: 'Separate from your password — required for every payment.' },
                    { Icon: PlusIcon, t: 'Fund your wallet', d: 'Add money via bank transfer or checkout.' },
                  ].map(({ Icon, t, d }) => (
                    <li key={t} className="flex gap-3 rounded-xl border border-line p-3">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
                        <Icon size={18} />
                      </span>
                      <span>
                        <span className="block text-sm font-semibold">{t}</span>
                        <span className="block text-xs text-muted">{d}</span>
                      </span>
                    </li>
                  ))}
                </ul>
                <Button type="button" onClick={() => setStep(1)} className="group mt-6 w-full">
                  <span className="inline-flex items-center gap-2">
                    Set up my PIN
                    <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                  </span>
                </Button>
              </motion.div>
            )}

            {step === 1 && (
              <motion.div key="pin" {...slide} transition={{ duration: 0.25 }}>
                <h1 className="font-display text-2xl font-bold tracking-tight">Create your transaction PIN</h1>
                <p className="mt-2 text-sm text-muted">4 digits. You'll enter this for sends, bills, and withdrawals.</p>
                <form onSubmit={submitPin} className="mt-6 space-y-4">
                  <Field
                    label="PIN"
                    type="password"
                    inputMode="numeric"
                    autoComplete="new-password"
                    maxLength={4}
                    placeholder="4 digits"
                    value={pinForm.data.pin}
                    onChange={(e) => pinForm.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                    error={pinForm.errors.pin}
                    required
                  />
                  <Field
                    label="Confirm PIN"
                    type="password"
                    inputMode="numeric"
                    autoComplete="new-password"
                    maxLength={4}
                    placeholder="Repeat PIN"
                    value={pinForm.data.pin_confirmation}
                    onChange={(e) =>
                      pinForm.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 4))
                    }
                    error={pinForm.errors.pin_confirmation}
                    required
                  />
                  <Button type="submit" loading={pinForm.processing} className="w-full">
                    Save PIN
                  </Button>
                  <button
                    type="button"
                    onClick={() => setStep(0)}
                    className="w-full text-sm text-muted transition hover:text-mint"
                  >
                    ← Back
                  </button>
                </form>
              </motion.div>
            )}

            {step === 2 && (
              <motion.div key="fund" {...slide} transition={{ duration: 0.25 }} className="mt-5 text-center sm:mt-6">
                <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-mint/15 text-mint ring-1 ring-mint/20">
                  <CheckIcon size={28} />
                </span>
                <h1 className="mt-4 font-display text-xl font-bold tracking-tight sm:text-2xl">You&apos;re all set</h1>
                <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
                  Your wallet is secured with a 4-digit PIN. Add money now or explore the dashboard — you can fund anytime.
                </p>
                <div className="mt-6 flex flex-col gap-3">
                  <Link href="/add-money" className="btn w-full bg-mint py-3 text-sm font-semibold text-white">
                    Add money now
                  </Link>
                  <Link
                    href="/dashboard"
                    className="btn w-full border border-line bg-surface py-3 text-sm font-semibold text-text transition hover:border-mint/35"
                  >
                    Go to dashboard
                  </Link>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        <div className="mt-6 flex justify-center sm:mt-8">
          <PoweredByAlatInline />
        </div>
      </div>
    </div>
  )
}
