import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, apiError } from '../lib/api'
import { useAuth } from '../store/auth'
import { Button, Field } from '../components/ui'
import { AuthLayout } from './AuthLayout'

export function Login() {
  const navigate = useNavigate()
  const setAuth = useAuth((s) => s.setAuth)
  const [email, setEmail] = useState('demo@reton.ng')
  const [password, setPassword] = useState('Sup3r-Secret!')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { data } = await api.post('/auth/login', { email, password })
      setAuth(data.data.token, data.data.user)
      navigate('/')
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout title="Welcome back" sub="Sign in to your Reton wallet.">
      <form onSubmit={submit} className="space-y-4">
        <Field label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        <Field
          label="Password"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />
        {error && <p className="text-sm text-danger">{error}</p>}
        <Button type="submit" loading={loading} className="w-full">
          Sign in
        </Button>
      </form>
      <p className="mt-5 text-center text-sm text-muted">
        New to Reton?{' '}
        <Link to="/register" className="text-mint hover:underline">
          Create an account
        </Link>
      </p>
    </AuthLayout>
  )
}
