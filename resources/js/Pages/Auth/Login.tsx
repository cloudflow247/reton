import type { FormEvent } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { AuthLayout } from '@/components/AuthLayout'
import { Button, Field } from '@/components/ui'
import { deviceHeaders } from '@/lib/device'

export default function Login() {
  const form = useForm({ email: '', password: '' })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.post('/login', { headers: deviceHeaders(), onFinish: () => form.reset('password') })
  }

  return (
    <AuthLayout title="Welcome back" sub="Sign in to your Reton wallet.">
      <Head title="Sign in" />
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
