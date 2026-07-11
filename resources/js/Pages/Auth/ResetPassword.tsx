import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { AuthAlert } from '@/components/AuthAlert'
import { AuthLayout } from '@/components/AuthLayout'
import { PasswordStrength } from '@/components/PasswordStrength'
import { HoneypotFields } from '@/components/forms/HoneypotFields'
import { RhfPasswordField } from '@/components/forms/RhfPasswordField'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { Button } from '@/components/ui'
import { ArrowRightIcon } from '@/components/icons'
import { resetPasswordSchema, type ResetPasswordFormValues } from '@/lib/schemas/auth'
import type { PageProps } from '@/types'

type ResetPasswordProps = PageProps<{
  email: string
  token: string
}>

export default function ResetPassword() {
  const { email, token } = usePage<ResetPasswordProps>().props
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors },
  } = useForm<ResetPasswordFormValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { email, token, password: '', password_confirmation: '', website: '' },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)
  const password = watch('password')

  const formError =
    serverErrors.email ??
    serverErrors.password ??
    serverErrors.password_confirmation

  const onSubmit = handleSubmit((values) => {
    setProcessing(true)
    setServerErrors({})
    router.post('/reset-password', values, {
      preserveScroll: true,
      onError: (errs) => setServerErrors(errs as Record<string, string>),
      onFinish: () => setProcessing(false),
    })
  })

  return (
    <AuthLayout
      title="Choose a new password"
      sub={`Create a fresh password for ${email}.`}
      step={1}
      totalSteps={2}
    >
      <Head title="Reset password" />

      <AuthAlert message={formError} />

      <form onSubmit={onSubmit} className="relative space-y-4" noValidate>
        <HoneypotFields websiteProps={register('website')} />
        <input type="hidden" {...register('token')} />
        <input type="hidden" {...register('email')} />

        <RhfPasswordField
          label="New password"
          name="password"
          placeholder="••••••••"
          autoComplete="new-password"
          autoFocus
          hint="At least 8 characters, with letters and numbers."
          error={fieldErrorMessage(errors.password, serverErrors.password)}
          {...register('password')}
        />
        <PasswordStrength password={password ?? ''} />
        <RhfPasswordField
          label="Confirm new password"
          name="password_confirmation"
          placeholder="••••••••"
          autoComplete="new-password"
          error={fieldErrorMessage(errors.password_confirmation, serverErrors.password_confirmation)}
          {...register('password_confirmation')}
        />
        <Button type="submit" loading={processing} className="group mt-1 w-full">
          <span className="inline-flex items-center justify-center gap-2">
            Update password
            <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
          </span>
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-muted">
        <Link href="/login" className="font-semibold text-mint hover:underline">
          Back to sign in
        </Link>
      </p>
    </AuthLayout>
  )
}
