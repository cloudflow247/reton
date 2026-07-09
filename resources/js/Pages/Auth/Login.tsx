import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { AuthLayout } from '@/components/AuthLayout'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon, SparkleIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { loginSchema, type LoginFormValues } from '@/lib/schemas/auth'
import type { SharedProps } from '@/types'

const slide = {
  initial: { opacity: 0, x: 24 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -24 },
}

export default function Login() {
  const { demo } = usePage<SharedProps>().props
  const [step, setStep] = useState(0)
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    trigger,
    getValues,
    setError,
    resetField,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)

  const postLogin = (values: LoginFormValues) => {
    setProcessing(true)
    setServerErrors({})
    router.post('/login', values, {
      headers: deviceHeaders(),
      preserveScroll: true,
      onError: (errs) => setServerErrors(errs as Record<string, string>),
      onFinish: () => {
        setProcessing(false)
        resetField('password')
      },
    })
  }

  const signInAs = (email: string) => {
    if (!demo) return
    postLogin({ email, password: demo.password })
  }

  async function nextStep() {
    const ok = await trigger('email')
    if (ok) setStep(1)
  }

  const titles = ['Welcome back', 'Enter your password']
  const subs = ['Sign in to your Reton wallet.', `Signing in as ${getValues('email') || 'you'}.`]

  return (
    <AuthLayout title={titles[step]} sub={subs[step]} step={step} totalSteps={2}>
      <Head title="Sign in" />

      {demo && step === 0 && (
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-6 overflow-hidden rounded-2xl border border-mint/25 bg-mint/[0.06] p-4"
        >
          <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-mint">
            <SparkleIcon size={14} /> Try it instantly
          </div>
          <p className="mt-1.5 text-xs leading-relaxed text-muted">
            Tap a demo account — PIN for payments is{' '}
            <span className="font-num font-semibold text-text">{demo.pin}</span>.
          </p>
          <div className="mt-3 space-y-2">
            {demo.accounts.map((a) => (
              <button
                key={a.email}
                type="button"
                onClick={() => signInAs(a.email)}
                disabled={processing}
                className="group flex w-full items-center justify-between gap-3 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-left transition hover:border-mint/40 hover:shadow-sm disabled:opacity-60"
              >
                <span className="min-w-0">
                  <span className="block text-sm font-semibold text-text">{a.name}</span>
                  <span className="block truncate text-xs text-muted">{a.email}</span>
                </span>
                <ArrowRightIcon size={14} className="shrink-0 text-mint transition group-hover:translate-x-0.5" />
              </button>
            ))}
          </div>
        </motion.div>
      )}

      <form onSubmit={handleSubmit(postLogin)} className="space-y-4" noValidate>
        <AnimatePresence mode="wait">
          {step === 0 ? (
            <motion.div key="email" {...slide} transition={{ duration: 0.22 }} className="space-y-4">
              <RhfField
                label="Email"
                type="email"
                placeholder="you@example.com"
                autoComplete="email"
                autoFocus
                error={fieldErrorMessage(errors.email, serverErrors.email)}
                {...register('email')}
              />
              <Button type="button" onClick={nextStep} className="group mt-1 w-full">
                <span className="inline-flex items-center justify-center gap-2">
                  Continue
                  <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                </span>
              </Button>
            </motion.div>
          ) : (
            <motion.div key="password" {...slide} transition={{ duration: 0.22 }} className="space-y-4">
              <RhfField
                label="Password"
                type="password"
                placeholder="••••••••"
                autoComplete="current-password"
                autoFocus
                error={fieldErrorMessage(errors.password, serverErrors.password)}
                {...register('password')}
              />
              <Button type="submit" loading={processing} className="group mt-1 w-full">
                <span className="inline-flex items-center justify-center gap-2">
                  Sign in
                  <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                </span>
              </Button>
              <button
                type="button"
                onClick={() => setStep(0)}
                className="w-full text-center text-sm text-muted transition hover:text-mint"
              >
                ← Use a different email
              </button>
            </motion.div>
          )}
        </AnimatePresence>
      </form>

      <p className="mt-6 text-center text-sm text-muted">
        New to Reton?{' '}
        <Link href="/register" className="font-semibold text-mint hover:underline">
          Create an account
        </Link>
      </p>
    </AuthLayout>
  )
}
