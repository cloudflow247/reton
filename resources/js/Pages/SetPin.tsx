import type { FormEvent, ReactNode } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { CheckIcon, LockIcon } from '@/components/icons'
import { FormPanel, InfoStrip, Page, PageHero } from '@/components/page-kit'
import { Button, Field, Pill } from '@/components/ui'
import type { SharedProps } from '@/types'

export default function SetPin() {
  const { auth, flash } = usePage<SharedProps>().props
  const hasPin = auth.user?.has_transaction_pin
  const form = useForm({ current_pin: '', pin: '', pin_confirmation: '' })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.post('/pin', { preserveScroll: true, onSuccess: () => form.reset() })
  }

  return (
    <Page narrow>
      <Head title="Transaction PIN" />
      <PageHero
        icon={LockIcon}
        title={hasPin ? 'Change PIN' : 'Set up PIN'}
        subtitle="4 digits — required for every payment"
        tone="mint"
      />

      <FormPanel>
        <form onSubmit={submit} className="space-y-4">
          {hasPin && (
            <>
              <Field
                label="Current PIN"
                type="password"
                inputMode="numeric"
                autoComplete="current-password"
                maxLength={4}
                placeholder="••••"
                value={form.data.current_pin}
                onChange={(e) => form.setData('current_pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                error={form.errors.current_pin}
                required
              />
            </>
          )}

          <Field
            label="New PIN"
            type="password"
            inputMode="numeric"
            autoComplete="new-password"
            maxLength={4}
            placeholder="4 digits"
            value={form.data.pin}
            onChange={(e) => form.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
            error={form.errors.pin}
            required
          />

          <Field
            label="Confirm PIN"
            type="password"
            inputMode="numeric"
            autoComplete="new-password"
            maxLength={4}
            placeholder="Re-enter PIN"
            value={form.data.pin_confirmation}
            onChange={(e) => form.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 4))}
            error={form.errors.pin_confirmation}
            required
          />

          {flash.error && (
            <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
          )}

          {flash.success && (
            <Pill tone="mint">
              <CheckIcon size={12} /> {flash.success}
            </Pill>
          )}

          <Button type="submit" loading={form.processing} className="w-full">
            {hasPin ? 'Update PIN' : 'Save PIN'}
          </Button>
        </form>
      </FormPanel>

      <InfoStrip tone="mint" title="Your PIN is private">
        Encrypted and never shared. Reton staff will never ask for it. Avoid obvious sequences like 1234.
      </InfoStrip>
    </Page>
  )
}

SetPin.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
