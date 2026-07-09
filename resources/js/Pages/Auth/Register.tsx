import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router } from '@inertiajs/react'
import { motion, AnimatePresence } from 'framer-motion'
import { useState } from 'react'
import { useForm, type UseFormSetValue } from 'react-hook-form'
import { AuthAlert } from '@/components/AuthAlert'
import { AuthLayout } from '@/components/AuthLayout'
import { PasswordStrength } from '@/components/PasswordStrength'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { registerSchema, type RegisterFormValues } from '@/lib/schemas/auth'
import { cn } from '@/lib/utils'

const slide = {
  initial: { opacity: 0, x: 20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -20 },
}

const stepMeta = [
  { title: 'Create your wallet', sub: 'Start with the basics — takes 30 seconds.' },
  { title: 'Your phone number', sub: 'Used for alerts and account recovery.' },
  { title: 'Secure your account', sub: 'Choose a strong password to protect your wallet.' },
] as const

function syncFromDom(setValue: UseFormSetValue<RegisterFormValues>) {
  const form = document.getElementById('register-form')
  if (!(form instanceof HTMLFormElement)) return

  const fd = new FormData(form)
  for (const key of ['name', 'email', 'phone', 'password', 'password_confirmation'] as const) {
    const value = fd.get(key)
    if (typeof value === 'string') {
      setValue(key, value, { shouldDirty: true })
    }
  }
}

export default function Register() {
  const [step, setStep] = useState(0)
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    trigger,
    setValue,
    setError,
    resetField,
    watch,
    getValues,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', phone: '', password: '', password_confirmation: '' },
    mode: 'onBlur',
    shouldUnregister: false,
  })

  useServerErrors(serverErrors, setError)

  const name = watch('name')
  const email = watch('email')
  const phone = watch('phone')
  const password = watch('password')

  const onSubmit = handleSubmit((values) => {
    syncFromDom(setValue)
    const payload = { ...getValues(), ...values }

    setProcessing(true)
    setServerErrors({})
    router.post('/register', payload, {
      headers: deviceHeaders(),
      preserveScroll: true,
      onError: (errs) => setServerErrors(errs as Record<string, string>),
      onFinish: () => {
        setProcessing(false)
        resetField('password')
        resetField('password_confirmation')
      },
    })
  })

  async function advance(from: number) {
    syncFromDom(setValue)

    if (from === 0) {
      const ok = await trigger(['name', 'email'])
      if (ok) setStep(1)
      return
    }

    if (from === 1) {
      const ok = await trigger('phone')
      if (ok) setStep(2)
    }
  }

  const stepError =
    serverErrors.name ||
    serverErrors.email ||
    serverErrors.phone ||
    serverErrors.password ||
    serverErrors.password_confirmation

  return (
    <AuthLayout title={stepMeta[step].title} sub={stepMeta[step].sub} step={step} totalSteps={3}>
      <Head title="Create account" />

      <AuthAlert message={stepError} />

      <form id="register-form" onSubmit={onSubmit} className="space-y-4" noValidate>
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
                  label="Full name"
                  name="name"
                  id="register-name"
                  placeholder="Ada Okafor"
                  autoComplete="name"
                  autoFocus={step === 0}
                  valid={!!name && !errors.name && !serverErrors.name}
                  error={fieldErrorMessage(errors.name, serverErrors.name)}
                  {...register('name')}
                />
                <RhfField
                  label="Email"
                  name="email"
                  type="email"
                  placeholder="you@example.com"
                  autoComplete="email"
                  valid={!!email && !errors.email && !serverErrors.email}
                  error={fieldErrorMessage(errors.email, serverErrors.email)}
                  {...register('email')}
                />
                <Button type="button" onClick={() => advance(0)} className="group mt-1 w-full">
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
                  label="Phone"
                  name="phone"
                  type="tel"
                  placeholder="+2348012345678"
                  autoComplete="tel"
                  autoFocus={step === 1}
                  hint="Include country code. Nigeria numbers start with +234."
                  valid={!!phone && !errors.phone && !serverErrors.phone}
                  error={fieldErrorMessage(errors.phone, serverErrors.phone)}
                  {...register('phone')}
                />
                <Button type="button" onClick={() => advance(1)} className="group mt-1 w-full">
                  <span className="inline-flex items-center justify-center gap-2">
                    Continue
                    <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                  </span>
                </Button>
                <button
                  type="button"
                  onClick={() => setStep(0)}
                  className="w-full text-center text-sm text-muted transition hover:text-mint"
                >
                  ← Back
                </button>
              </div>
            </div>

            <div className={cn(step !== 2 && 'hidden')} aria-hidden={step !== 2}>
              <div className="space-y-4">
                <RhfField
                  label="Password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  autoComplete="new-password"
                  autoFocus={step === 2}
                  hint="At least 8 characters, with letters and numbers."
                  error={fieldErrorMessage(errors.password, serverErrors.password)}
                  {...register('password')}
                />
                <PasswordStrength password={password ?? ''} />
                <RhfField
                  label="Confirm password"
                  name="password_confirmation"
                  type="password"
                  placeholder="••••••••"
                  autoComplete="new-password"
                  error={fieldErrorMessage(errors.password_confirmation, serverErrors.password_confirmation)}
                  {...register('password_confirmation')}
                />
                <Button type="submit" loading={processing} className="group mt-1 w-full">
                  <span className="inline-flex items-center justify-center gap-2">
                    Create account
                    <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                  </span>
                </Button>
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="w-full text-center text-sm text-muted transition hover:text-mint"
                >
                  ← Back
                </button>
              </div>
            </div>
          </motion.div>
        </AnimatePresence>
      </form>

      <p className="mt-6 text-center text-sm text-muted">
        Already have an account?{' '}
        <Link href="/login" className="font-semibold text-mint hover:underline">
          Sign in
        </Link>
      </p>
    </AuthLayout>
  )
}
