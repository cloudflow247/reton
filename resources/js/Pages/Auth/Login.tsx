import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import { useForm, type FieldErrors } from 'react-hook-form'
import { AuthAlert } from '@/components/AuthAlert'
import { AuthLayout } from '@/components/AuthLayout'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon, SparkleIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { loginSchema, type LoginFormValues } from '@/lib/schemas/auth'
import type { SharedProps } from '@/types'
import { cn } from '@/lib/utils'

const slide = {
  initial: { opacity: 0, x: 20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -20 },
}

function firstError(value?: string | string[]): string | undefined {
  if (!value) return undefined
  return Array.isArray(value) ? value[0] : value
}

function syncLoginEmail(form: HTMLFormElement, setValue: (v: string) => void) {
  const email = form.elements.namedItem('email')
  if (email instanceof HTMLInputElement && email.value) {
    setValue(email.value)
  }
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
    setValue,
    watch,
    setError,
    resetField,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
    mode: 'onBlur',
    shouldUnregister: false,
  })

  useServerErrors(serverErrors, setError)

  const email = watch('email')

  const postLogin = (values: LoginFormValues) => {
    const form = document.getElementById('login-form')
    if (form instanceof HTMLFormElement) {
      syncLoginEmail(form, (v) => setValue('email', v, { shouldDirty: true }))
    }

    const payload = {
      email: values.email || email || getValues('email'),
      password: values.password,
    }

    setProcessing(true)
    setServerErrors({})
    router.post('/login', payload, {
      headers: deviceHeaders(),
      preserveScroll: true,
      onError: (errs) => setServerErrors(errs as Record<string, string>),
      onFinish: () => {
        setProcessing(false)
        resetField('password')
      },
    })
  }

  const onInvalid = (formErrors: FieldErrors<LoginFormValues>) => {
    if (formErrors.email && step === 1) setStep(0)
  }

  const signInAs = (demoEmail: string) => {
    if (!demo) return
    postLogin({ email: demoEmail, password: demo.password })
  }

  async function nextStep() {
    const form = document.getElementById('login-form')
    if (form instanceof HTMLFormElement) {
      syncLoginEmail(form, (v) => setValue('email', v, { shouldDirty: true }))
    }
    const ok = await trigger('email')
    if (ok) setStep(1)
  }

  const titles = ['Welcome back', 'Enter your password']
  const subs = ['Sign in to your Reton wallet.', `Signing in as ${email || 'you'}.`]
  const authError =
    firstError(serverErrors.password) ??
    firstError(serverErrors.email) ??
    (step === 1 ? errors.email?.message : undefined)

  return (
    <AuthLayout title={titles[step]} sub={subs[step]} step={step} totalSteps={2}>
      <Head title="Sign in" />

      <AuthAlert message={authError} />

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

      <form id="login-form" onSubmit={handleSubmit(postLogin, onInvalid)} className="space-y-4" noValidate>
        <AnimatePresence mode="wait">
          <motion.div
            key={step}
            {...slide}
            transition={{ duration: 0.24, ease: [0.22, 1, 0.36, 1] }}
            className="space-y-4"
          >
            <div className={cn(step !== 0 && 'hidden')} aria-hidden={step !== 0}>
              <div className="space-y-4">
                <RhfField
                  label="Email"
                  name="email"
                  type="email"
                  placeholder="you@example.com"
                  autoComplete="email"
                  autoFocus={step === 0}
                  valid={!!email && !errors.email && !serverErrors.email}
                  error={fieldErrorMessage(errors.email, serverErrors.email)}
                  {...register('email')}
                />
                <Button type="button" onClick={nextStep} className="group mt-1 w-full">
                  <span className="inline-flex items-center justify-center gap-2">
                    Continue
                    <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                  </span>
                </Button>
              </div>
            </div>

            <div className={cn(step !== 1 && 'hidden')} aria-hidden={step !== 1}>
              <div className="space-y-4">
                <RhfField
                  label="Password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  autoComplete="current-password"
                  autoFocus={step === 1}
                  error={fieldErrorMessage(errors.password, serverErrors.password)}
                  {...register('password')}
                />
                <div className="flex justify-end">
                  <Link href="/forgot-password" className="text-xs font-semibold text-mint hover:underline">
                    Forgot password?
                  </Link>
                </div>
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
              </div>
            </div>
          </motion.div>
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
