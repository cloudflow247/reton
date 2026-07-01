import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
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

export default function Login() {
  const { demo } = usePage<SharedProps>().props
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)

  const {
    register,
    handleSubmit,
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

  return (
    <AuthLayout title="Welcome back" sub="Sign in to your Reton wallet.">
      <Head title="Sign in" />

      {demo && (
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-6 overflow-hidden rounded-2xl border border-mint/25 bg-mint/[0.06] p-4"
        >
          <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-mint">
            <SparkleIcon size={14} /> Try it instantly
          </div>
          <p className="mt-1.5 text-xs leading-relaxed text-muted">
            Tap a demo account to sign in — no signup needed. Transaction PIN for payments is{' '}
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
                <span className="flex shrink-0 items-center gap-1 text-sm font-semibold text-mint">
                  Use
                  <ArrowRightIcon size={14} className="transition-transform group-hover:translate-x-0.5" />
                </span>
              </button>
            ))}
          </div>
        </motion.div>
      )}

      {demo && (
        <div className="mb-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-muted">
          <span className="h-px flex-1 bg-line" />
          or sign in with email
          <span className="h-px flex-1 bg-line" />
        </div>
      )}

      <form onSubmit={handleSubmit(postLogin)} className="space-y-4" noValidate>
        <RhfField
          label="Email"
          type="email"
          placeholder="you@example.com"
          autoComplete="email"
          error={fieldErrorMessage(errors.email, serverErrors.email)}
          {...register('email')}
        />
        <RhfField
          label="Password"
          type="password"
          placeholder="••••••••"
          autoComplete="current-password"
          error={fieldErrorMessage(errors.password, serverErrors.password)}
          {...register('password')}
        />
        <Button type="submit" loading={processing} className="group mt-1 w-full">
          <span className="inline-flex items-center justify-center gap-2">
            Sign in
            <ArrowRightIcon size={16} className="transition-transform group-hover:translate-x-0.5" />
          </span>
        </Button>
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
