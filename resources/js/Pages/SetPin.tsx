import type { FormEvent, ReactNode } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { Button, Card, Field, Pill } from '@/components/ui'
import { LockIcon } from '@/components/icons'
import type { SharedProps } from '@/types'

export default function SetPin() {
  const { auth, flash } = usePage<SharedProps>().props
  const hasPin = auth.user?.has_transaction_pin

  const form = useForm({ current_pin: '', pin: '', pin_confirmation: '' })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.post('/pin', {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <Head title="Transaction PIN" />
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
              value={form.data.current_pin}
              onChange={(e) => form.setData('current_pin', e.target.value)}
              required
            />
          )}
          {form.errors.current_pin && <p className="text-sm text-danger">{form.errors.current_pin}</p>}
          <Field
            label="New PIN"
            type="password"
            inputMode="numeric"
            placeholder="4–6 digits"
            value={form.data.pin}
            onChange={(e) => form.setData('pin', e.target.value)}
            required
          />
          <Field
            label="Confirm PIN"
            type="password"
            inputMode="numeric"
            value={form.data.pin_confirmation}
            onChange={(e) => form.setData('pin_confirmation', e.target.value)}
            required
          />
          {form.errors.pin && <p className="text-sm text-danger">{form.errors.pin}</p>}
          {flash.success && <Pill tone="mint">{flash.success}</Pill>}
          <Button type="submit" loading={form.processing} className="w-full">
            {hasPin ? 'Change PIN' : 'Set PIN'}
          </Button>
        </form>
      </Card>
    </div>
  )
}

SetPin.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
