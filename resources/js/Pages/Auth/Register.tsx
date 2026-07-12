import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion, AnimatePresence } from 'framer-motion'
import { useMemo, useState } from 'react'
import { useForm, type UseFormSetValue } from 'react-hook-form'
import { AuthAlert } from '@/components/AuthAlert'
import { AuthLayout } from '@/components/AuthLayout'
import { PasswordStrength } from '@/components/PasswordStrength'
import { HoneypotFields } from '@/components/forms/HoneypotFields'
import { PhoneWithCountryField } from '@/components/forms/PhoneWithCountryField'
import { RhfField } from '@/components/forms/RhfField'
import { RhfPasswordField } from '@/components/forms/RhfPasswordField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { registerSchema, type RegisterFormValues } from '@/lib/schemas/auth'
import { cn } from '@/lib/utils'
import type { CountryDialCode, PageProps } from '@/types'

const slide = {
  initial: { opacity: 0, x: 20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -20 },
}

const stepMeta = [
  { title: 'Create your wallet', sub: 'Legal name and email — as on your ID (CBN KYC).' },
  { title: 'Your mobile number', sub: 'Used for alerts, OTP, and account recovery.' },
  { title: 'Secure your account', sub: 'Choose a strong password to protect your wallet.' },
] as const

const FALLBACK_COUNTRIES: CountryDialCode[] = [
  { iso: 'NG', name: 'Nigeria', dial: '234' },
  { iso: 'GH', name: 'Ghana', dial: '233' },
  { iso: 'KE', name: 'Kenya', dial: '254' },
  { iso: 'ZA', name: 'South Africa', dial: '27' },
  { iso: 'GB', name: 'United Kingdom', dial: '44' },
  { iso: 'US', name: 'United States', dial: '1' },
]

function syncFromDom(setValue: UseFormSetValue<RegisterFormValues>) {
  const form = document.getElementById('register-form')
  if (!(form instanceof HTMLFormElement)) return

  const fd = new FormData(form)
  for (const key of [
    'first_name',
    'middle_name',
    'last_name',
    'email',
    'phone_national',
    'password',
    'password_confirmation',
    'website',
  ] as const) {
    const value = fd.get(key)
    if (typeof value === 'string') {
      setValue(key, value, { shouldDirty: true })
    }
  }
}

type RegisterPageProps = PageProps<{
  redirect?: string | null
  countries?: CountryDialCode[]
}>

export default function Register() {
  const { countries: countriesProp } = usePage<RegisterPageProps>().props
  const countries = useMemo(
    () => (Array.isArray(countriesProp) && countriesProp.length > 0 ? countriesProp : FALLBACK_COUNTRIES),
    [countriesProp],
  )

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
    defaultValues: {
      first_name: '',
      middle_name: '',
      last_name: '',
      email: '',
      country_iso: 'NG',
      country_code: '234',
      phone_national: '',
      password: '',
      password_confirmation: '',
      website: '',
    },
    mode: 'onBlur',
    shouldUnregister: false,
  })

  useServerErrors(serverErrors, setError)

  const firstName = watch('first_name')
  const lastName = watch('last_name')
  const email = watch('email')
  const phoneNational = watch('phone_national')
  const countryIso = watch('country_iso')
  const password = watch('password')

  const onSubmit = handleSubmit((values) => {
    syncFromDom(setValue)
    const current = { ...getValues(), ...values }
    const selected = countries.find((c) => c.iso === current.country_iso)
    const payload = {
      first_name: current.first_name,
      middle_name: current.middle_name || '',
      last_name: current.last_name,
      email: current.email,
      country_iso: current.country_iso,
      country_code: selected?.dial ?? current.country_code,
      phone_national: current.phone_national,
      password: current.password,
      password_confirmation: current.password_confirmation,
      website: current.website || '',
    }

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
      const ok = await trigger(['first_name', 'middle_name', 'last_name', 'email'])
      if (ok) setStep(1)
      return
    }

    if (from === 1) {
      const ok = await trigger(['country_iso', 'country_code', 'phone_national'])
      if (ok) setStep(2)
    }
  }

  const stepError =
    serverErrors.first_name ||
    serverErrors.middle_name ||
    serverErrors.last_name ||
    serverErrors.name ||
    serverErrors.email ||
    serverErrors.phone ||
    serverErrors.phone_national ||
    serverErrors.password ||
    serverErrors.password_confirmation

  const phoneRegister = register('phone_national')

  return (
    <AuthLayout title={stepMeta[step].title} sub={stepMeta[step].sub} step={step} totalSteps={3}>
      <Head title="Create account" />

      <AuthAlert message={stepError} />

      <form id="register-form" onSubmit={onSubmit} className="relative space-y-4" noValidate>
        <HoneypotFields websiteProps={register('website')} />

        <AnimatePresence mode="wait">
          <motion.div
            key={step}
            {...slide}
            transition={{ duration: 0.24, ease: [0.22, 1, 0.36, 1] }}
            className="space-y-4"
          >
            <div className={cn(step !== 0 && 'hidden')} aria-hidden={step !== 0}>
              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <RhfField
                    label="First name"
                    placeholder="Ada"
                    autoComplete="given-name"
                    autoFocus={step === 0}
                    valid={!!firstName && !errors.first_name && !serverErrors.first_name}
                    error={fieldErrorMessage(errors.first_name, serverErrors.first_name)}
                    {...register('first_name')}
                  />
                  <RhfField
                    label="Last name"
                    placeholder="Okafor"
                    autoComplete="family-name"
                    valid={!!lastName && !errors.last_name && !serverErrors.last_name}
                    error={fieldErrorMessage(errors.last_name, serverErrors.last_name)}
                    {...register('last_name')}
                  />
                </div>
                <RhfField
                  label="Middle name"
                  placeholder="Optional"
                  autoComplete="additional-name"
                  hint="Optional — include if it appears on your BVN or government ID."
                  error={fieldErrorMessage(errors.middle_name, serverErrors.middle_name)}
                  {...register('middle_name')}
                />
                <RhfField
                  label="Email"
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
                <PhoneWithCountryField
                  countries={countries}
                  countryIso={countryIso}
                  phoneNational={phoneNational}
                  autoFocus={step === 1}
                  valid={!!phoneNational && !errors.phone_national && !serverErrors.phone && !serverErrors.phone_national}
                  error={fieldErrorMessage(
                    errors.phone_national,
                    serverErrors.phone_national ?? serverErrors.phone,
                  )}
                  onCountryChange={(iso, dial) => {
                    setValue('country_iso', iso, { shouldDirty: true, shouldValidate: true })
                    setValue('country_code', dial, { shouldDirty: true, shouldValidate: true })
                  }}
                  onNationalChange={(value) =>
                    setValue('phone_national', value, { shouldDirty: true, shouldValidate: true })
                  }
                  nationalRegister={phoneRegister}
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
                <RhfPasswordField
                  label="Password"
                  placeholder="••••••••"
                  autoComplete="new-password"
                  autoFocus={step === 2}
                  hint="At least 8 characters, with letters and numbers."
                  error={fieldErrorMessage(errors.password, serverErrors.password)}
                  {...register('password')}
                />
                <PasswordStrength password={password ?? ''} />
                <RhfPasswordField
                  label="Confirm password"
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
