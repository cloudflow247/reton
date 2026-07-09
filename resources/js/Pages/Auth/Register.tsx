import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { AuthLayout } from '@/components/AuthLayout'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { registerSchema, type RegisterFormValues } from '@/lib/schemas/auth'

const slide = {
  initial: { opacity: 0, x: 24 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -24 },
}

const stepMeta = [
  { title: 'Create your wallet', sub: 'Start with the basics — takes 30 seconds.' },
  { title: 'Your phone number', sub: 'Used for alerts and account recovery.' },
  { title: 'Secure your account', sub: 'Choose a strong password to protect your wallet.' },
] as const

export default function Register() {
  const [step, setStep] = useState(0)
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    trigger,
    setError,
    resetField,
    watch,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', phone: '', password: '', password_confirmation: '' },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)

  const onSubmit = handleSubmit((values) => {
    setProcessing(true)
    setServerErrors({})
    router.post(
      '/register',
      { ...values, password_confirmation: values.password_confirmation },
      {
        headers: deviceHeaders(),
        preserveScroll: true,
        onError: (errs) => setServerErrors(errs as Record<string, string>),
        onFinish: () => {
          setProcessing(false)
          resetField('password')
          resetField('password_confirmation')
        },
      },
    )
  })

  async function advance(from: number) {
    if (from === 0) {
      const ok = await trigger(['name', 'email'])
      if (ok) setStep(1)
    } else if (from === 1) {
      const ok = await trigger('phone')
      if (ok) setStep(2)
    }
  }

  const password = watch('password')

  return (
    <AuthLayout title={stepMeta[step].title} sub={stepMeta[step].sub} step={step} totalSteps={3}>
      <Head title="Create account" />

      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <AnimatePresence mode="wait">
          {step === 0 && (
            <motion.div key="identity" {...slide} transition={{ duration: 0.22 }} className="space-y-4">
              <RhfField
                label="Full name"
                placeholder="Ada Okafor"
                autoComplete="name"
                autoFocus
                error={fieldErrorMessage(errors.name, serverErrors.name)}
                {...register('name')}
              />
              <RhfField
                label="Email"
                type="email"
                placeholder="you@example.com"
                autoComplete="email"
                error={fieldErrorMessage(errors.email, serverErrors.email)}
                {...register('email')}
              />
              <Button type="button" onClick={() => advance(0)} className="group mt-1 w-full">
                <span className="inline-flex items-center justify-center gap-2">
                  Continue
                  <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
                </span>
              </Button>
            </motion.div>
          )}

          {step === 1 && (
            <motion.div key="phone" {...slide} transition={{ duration: 0.22 }} className="space-y-4">
              <RhfField
                label="Phone"
                type="tel"
                placeholder="+2348012345678"
                autoComplete="tel"
                autoFocus
                hint="Include country code. Nigeria numbers start with +234."
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
            </motion.div>
          )}

          {step === 2 && (
            <motion.div key="security" {...slide} transition={{ duration: 0.22 }} className="space-y-4">
              <RhfField
                label="Password"
                type="password"
                placeholder="••••••••"
                autoComplete="new-password"
                autoFocus
                hint="At least 8 characters, with letters and numbers."
                error={fieldErrorMessage(errors.password, serverErrors.password)}
                {...register('password')}
              />
              <RhfField
                label="Confirm password"
                type="password"
                placeholder="••••••••"
                autoComplete="new-password"
                error={fieldErrorMessage(errors.password_confirmation, serverErrors.password_confirmation)}
                {...register('password_confirmation')}
              />
              {password && password.length >= 8 && (
                <p className="text-xs text-mint">Password strength looks good.</p>
              )}
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
            </motion.div>
          )}
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
