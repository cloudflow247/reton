import type { FormEvent } from 'react'
import { Head, Link, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AuthLayout } from '@/components/AuthLayout'
import { Button, Field } from '@/components/ui'
import { ArrowRightIcon, SparkleIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import type { SharedProps } from '@/types'

export default function Login() {
  const { demo } = usePage<SharedProps>().props
  const form = useForm({ email: '', password: '' })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.post('/login', { headers: deviceHeaders(), onFinish: () => form.reset('password') })
  }

  function signInAs(email: string) {
    if (!demo) return
    // transform() returns void in @inertiajs/react — set it, then post.
    form.transform(() => ({ email, password: demo.password }))
    form.post('/login', { headers: deviceHeaders() })
  }

  return (
    <AuthLayout title="Welcome back" sub="Sign in to your Reton wallet.">
      <Head title="Sign in" />

      {demo && (
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-6 rounded-2xl border border-mint/25 bg-mint/[0.06] p-4"
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
                disabled={form.processing}
                className="group flex w-full items-center justify-between rounded-xl border border-line bg-surface px-3.5 py-2.5 text-left transition hover:border-mint/40 hover:shadow-sm disabled:opacity-60"
              >
                <span className="min-w-0">
                  <span className="block text-sm font-semibold text-text">{a.name}</span>
                  <span className="block truncate text-xs text-muted">{a.email}</span>
                </span>
                <span className="flex items-center gap-1 text-sm font-medium text-mint">
                  Use
                  <ArrowRightIcon size={14} className="transition-transform group-hover:translate-x-0.5" />
                </span>
              </button>
            ))}
          </div>
        </motion.div>
      )}

      <form onSubmit={submit} className="space-y-4">
        <Field
          label="Email"
          type="email"
          placeholder="you@example.com"
          autoComplete="email"
          value={form.data.email}
          onChange={(e) => form.setData('email', e.target.value)}
          required
        />
        <Field
          label="Password"
          type="password"
          placeholder="••••••••"
          autoComplete="current-password"
          value={form.data.password}
          onChange={(e) => form.setData('password', e.target.value)}
          required
        />
        {form.errors.email && <p className="text-sm text-danger">{form.errors.email}</p>}
        {form.errors.password && <p className="text-sm text-danger">{form.errors.password}</p>}
        <Button type="submit" loading={form.processing} className="w-full">
          Sign in
        </Button>
      </form>
      <p className="mt-5 text-center text-sm text-muted">
        New to Reton?{' '}
        <Link href="/register" className="text-mint hover:underline">
          Create an account
        </Link>
      </p>
    </AuthLayout>
  )
}
