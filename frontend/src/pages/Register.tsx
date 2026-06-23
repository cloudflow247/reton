import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, apiError } from '../lib/api'
import { useAuth } from '../store/auth'
import { Button, Field } from '../components/ui'
import { AuthLayout } from './AuthLayout'

export function Register() {
  const navigate = useNavigate()
  const setAuth = useAuth((s) => s.setAuth)
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { data } = await api.post('/auth/register', {
        ...form,
        password_confirmation: form.password,
      })
      setAuth(data.data.token, data.data.user)
      navigate('/')
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout title="Create your wallet" sub="A safer way to move money in Africa.">
      <form onSubmit={submit} className="space-y-4">
        <Field label="Full name" value={form.name} onChange={set('name')} required />
        <Field label="Email" type="email" value={form.email} onChange={set('email')} required />
        <Field label="Phone" placeholder="+2348012345678" value={form.phone} onChange={set('phone')} required />
        <Field
          label="Password"
          type="password"
          hint="At least 8 characters, with letters and numbers."
          value={form.password}
          onChange={set('password')}
          required
        />
        {error && <p className="text-sm text-danger">{error}</p>}
        <Button type="submit" loading={loading} className="w-full">
          Create account
        </Button>
      </form>
      <p className="mt-5 text-center text-sm text-muted">
        Already have an account?{' '}
        <Link to="/login" className="text-mint hover:underline">
          Sign in
        </Link>
      </p>
    </AuthLayout>
  )
}
