import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { AuthLayout } from '@/components/AuthLayout'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { registerSchema, type RegisterFormValues } from '@/lib/schemas/auth'

export default function Register() {
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    setError,
    resetField,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', phone: '', password: '' },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)

  const onSubmit = handleSubmit((values) => {
    setProcessing(true)
    setServerErrors({})
    router.post(
      '/register',
      { ...values, password_confirmation: values.password },
      {
        headers: deviceHeaders(),
        preserveScroll: true,
        onError: (errs) => setServerErrors(errs as Record<string, string>),
        onFinish: () => {
          setProcessing(false)
          resetField('password')
        },
      },
    )
  })

  return (
    <AuthLayout title="Create your wallet" sub="A safer way to move money in Africa.">
      <Head title="Create account" />
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <RhfField
          label="Full name"
          placeholder="Ada Okafor"
          autoComplete="name"
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
        <RhfField
          label="Phone"
          type="tel"
          placeholder="+2348012345678"
          autoComplete="tel"
          error={fieldErrorMessage(errors.phone, serverErrors.phone)}
          {...register('phone')}
        />
        <RhfField
          label="Password"
          type="password"
          placeholder="••••••••"
          autoComplete="new-password"
          hint="At least 8 characters, with letters and numbers."
          error={fieldErrorMessage(errors.password, serverErrors.password)}
          {...register('password')}
        />
        <Button type="submit" loading={processing} className="group mt-1 w-full">
          <span className="inline-flex items-center justify-center gap-2">
            Create account
            <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
          </span>
        </Button>
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
