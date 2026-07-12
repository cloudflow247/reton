import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { AuthAlert } from '@/components/AuthAlert'
import { AuthLayout } from '@/components/AuthLayout'
import { HoneypotFields } from '@/components/forms/HoneypotFields'
import { RhfField } from '@/components/forms/RhfField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { forgotPasswordSchema, type ForgotPasswordFormValues } from '@/lib/schemas/auth'
import type { PageProps } from '@/types'

type ForgotPasswordProps = PageProps<{
  status?: string | null
}>

export default function ForgotPassword() {
  const { status } = usePage<ForgotPasswordProps>().props
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors },
  } = useForm<ForgotPasswordFormValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '', website: '' },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)
  const email = watch('email')

  const onSubmit = handleSubmit((values) => {
    setProcessing(true)
    setServerErrors({})
    router.post('/forgot-password', values, {
      preserveScroll: true,
      onError: (errs) => setServerErrors(errs as Record<string, string>),
      onFinish: () => setProcessing(false),
    })
  })

  return (
    <AuthLayout
      title="Reset your password"
      sub="Enter the email on your account and we'll send a secure reset link."
      step={0}
      totalSteps={2}
    >
      <Head title="Forgot password" />

      <AuthAlert tone="success" message={status ? 'If an account exists for that email, a reset link is on its way.' : undefined} />
      <AuthAlert
        tone="error"
        message={
          !status
            ? (serverErrors.email ?? (errors.email?.message as string | undefined))
            : undefined
        }
      />

      {status ? (
        <div className="space-y-4">
          <p className="text-center text-sm leading-relaxed text-muted">
            If an account exists for that email, a reset link is on its way. Check your inbox and spam folder.
          </p>
          <Link href="/login" className="btn flex w-full justify-center bg-mint px-5 py-2.5 text-sm text-white hover:bg-mint-strong">
            Back to sign in
          </Link>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="relative space-y-4" noValidate>
          <HoneypotFields websiteProps={register('website')} />
          <RhfField
            label="Email"
            type="email"
            placeholder="you@example.com"
            autoComplete="email"
            autoFocus
            valid={!!email && !errors.email && !serverErrors.email}
            error={fieldErrorMessage(errors.email, serverErrors.email)}
            {...register('email')}
          />
          <Button type="submit" loading={processing} className="group mt-1 w-full">
            <span className="inline-flex items-center justify-center gap-2">
              Send reset link
              <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
            </span>
          </Button>
        </form>
      )}

      <p className="mt-6 text-center text-sm text-muted">
        Remember your password?{' '}
        <Link href="/login" className="font-semibold text-mint hover:underline">
          Sign in
        </Link>
      </p>
    </AuthLayout>
  )
}
