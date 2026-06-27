import type { FormEvent, ReactNode } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { Button, Card, Field, Pill } from '@/components/ui'
import { CheckIcon, LockIcon, ShieldIcon } from '@/components/icons'
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
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
      className="mx-auto max-w-lg space-y-6"
    >
      <Head title="Transaction PIN" />

      {/* Hero */}
      <div className="flex flex-col items-center pt-2 text-center">
        <div className="shield-glow flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-mint to-mint-strong text-white">
          <LockIcon size={28} />
        </div>
        <h1 className="mt-4 font-display text-2xl font-bold tracking-tight">
          {hasPin ? 'Change your PIN' : 'Set up your PIN'}
        </h1>
        <p className="mt-1.5 max-w-xs text-sm text-muted">
          A 4–6 digit PIN authorises every payment — separate from your password and never shared.
        </p>
      </div>

      <Card>
        <form onSubmit={submit} className="space-y-4">
          {hasPin && (
            <>
              <Field
                label="Current PIN"
                type="password"
                inputMode="numeric"
                autoComplete="current-password"
                maxLength={6}
                placeholder="••••"
                value={form.data.current_pin}
                onChange={(e) => form.setData('current_pin', e.target.value)}
                required
              />
              {form.errors.current_pin && <p className="-mt-2 text-sm text-danger">{form.errors.current_pin}</p>}
            </>
          )}

          <Field
            label="New PIN"
            type="password"
            inputMode="numeric"
            autoComplete="new-password"
            maxLength={6}
            placeholder="4–6 digits"
            value={form.data.pin}
            onChange={(e) => form.setData('pin', e.target.value)}
            required
          />

          <Field
            label="Confirm new PIN"
            type="password"
            inputMode="numeric"
            autoComplete="new-password"
            maxLength={6}
            placeholder="Re-enter PIN"
            value={form.data.pin_confirmation}
            onChange={(e) => form.setData('pin_confirmation', e.target.value)}
            required
          />
          {form.errors.pin && <p className="-mt-2 text-sm text-danger">{form.errors.pin}</p>}

          {flash.success && (
            <Pill tone="mint">
              <CheckIcon size={12} /> {flash.success}
            </Pill>
          )}

          <Button type="submit" loading={form.processing} className="w-full">
            {hasPin ? 'Change PIN' : 'Set PIN'}
          </Button>
        </form>
      </Card>

      {/* Reassurance */}
      <div className="space-y-2 px-1">
        <Reassure>Your PIN is encrypted and stored separately from your account.</Reassure>
        <Reassure>Reton staff will never ask you for your PIN.</Reassure>
        <Reassure>Avoid obvious sequences like 1234 or your date of birth.</Reassure>
      </div>
    </motion.div>
  )
}

SetPin.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function Reassure({ children }: { children: ReactNode }) {
  return (
    <p className="flex items-start gap-2 text-xs text-muted">
      <ShieldIcon size={14} className="mt-0.5 shrink-0 text-mint" />
      <span>{children}</span>
    </p>
  )
}
