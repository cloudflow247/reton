import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, apiError } from '../lib/api'
import { useMe } from '../lib/queries'
import { useAuth } from '../store/auth'
import { Button, Card, Field, Pill } from '../components/ui'
import { LockIcon } from '../components/icons'

export function SetPin() {
  const { data, refetch } = useMe()
  const hasPin = data?.user.has_transaction_pin
  const qc = useQueryClient()
  const setUser = useAuth((s) => s.setUser)

  const [currentPin, setCurrentPin] = useState('')
  const [pin, setPin] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState('')
  const [ok, setOk] = useState(false)
  const [loading, setLoading] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setOk(false)
    setLoading(true)
    try {
      await api.post('/auth/pin', {
        ...(hasPin ? { current_pin: currentPin } : {}),
        pin,
        pin_confirmation: confirm,
      })
      setOk(true)
      setCurrentPin('')
      setPin('')
      setConfirm('')
      const me = await refetch()
      if (me.data?.user) setUser(me.data.user)
      qc.invalidateQueries({ queryKey: ['me'] })
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <div className="flex items-center gap-3">
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint/10 text-mint">
          <LockIcon size={20} />
        </span>
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Transaction PIN</h1>
          <p className="mt-0.5 text-sm text-muted">
            A 4–6 digit PIN authorises every payment, separate from your password.
          </p>
        </div>
      </div>
      <Card>
        <form onSubmit={submit} className="space-y-4">
          {hasPin && (
            <Field
              label="Current PIN"
              type="password"
              inputMode="numeric"
              value={currentPin}
              onChange={(e) => setCurrentPin(e.target.value)}
              required
            />
          )}
          <Field
            label="New PIN"
            type="password"
            inputMode="numeric"
            placeholder="4–6 digits"
            value={pin}
            onChange={(e) => setPin(e.target.value)}
            required
          />
          <Field
            label="Confirm PIN"
            type="password"
            inputMode="numeric"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            required
          />
          {error && <p className="text-sm text-danger">{error}</p>}
          {ok && <Pill tone="mint">PIN updated</Pill>}
          <Button type="submit" loading={loading} className="w-full">
            {hasPin ? 'Change PIN' : 'Set PIN'}
          </Button>
        </form>
      </Card>
    </div>
  )
}
