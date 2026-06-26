import type { FormEvent } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { AuthLayout } from '@/components/AuthLayout'
import { Button, Field } from '@/components/ui'
import { deviceHeaders } from '@/lib/device'

export default function Register() {
  const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' })

  function submit(e: FormEvent) {
    e.preventDefault()
    // Mirror the password into the confirmation the backend's RegisterRequest expects.
    // transform() returns void in @inertiajs/react — set it, then post.
    form.transform((data) => ({ ...data, password_confirmation: data.password }))
    form.post('/register', {
      headers: deviceHeaders(),
      onFinish: () => form.reset('password', 'password_confirmation'),
    })
  }

  return (
    <AuthLayout title="Create your wallet" sub="A safer way to move money in Africa.">
      <Head title="Create account" />
      <form onSubmit={submit} className="space-y-4">
        <Field
          label="Full name"
          value={form.data.name}
          onChange={(e) => form.setData('name', e.target.value)}
          required
        />
        {form.errors.name && <p className="text-sm text-danger">{form.errors.name}</p>}
        <Field
          label="Email"
          type="email"
          value={form.data.email}
          onChange={(e) => form.setData('email', e.target.value)}
          required
        />
        {form.errors.email && <p className="text-sm text-danger">{form.errors.email}</p>}
        <Field
          label="Phone"
          placeholder="+2348012345678"
          value={form.data.phone}
          onChange={(e) => form.setData('phone', e.target.value)}
          required
        />
        {form.errors.phone && <p className="text-sm text-danger">{form.errors.phone}</p>}
        <Field
          label="Password"
          type="password"
          hint="At least 8 characters, with letters and numbers."
          value={form.data.password}
          onChange={(e) => form.setData('password', e.target.value)}
          required
        />
        {form.errors.password && <p className="text-sm text-danger">{form.errors.password}</p>}
        <Button type="submit" loading={form.processing} className="w-full">
          Create account
        </Button>
      </form>
      <p className="mt-5 text-center text-sm text-muted">
        Already have an account?{' '}
        <Link href="/login" className="text-mint hover:underline">
          Sign in
        </Link>
      </p>
    </AuthLayout>
  )
}
