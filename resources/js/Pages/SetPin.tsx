import type { FormEvent, ReactNode } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { CheckIcon, LockIcon, ShieldIcon } from '@/components/icons'
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
        subtitle="4–6 digits. Required for every payment — separate from your password."
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
                maxLength={6}
                placeholder="••••"
                value={form.data.current_pin}
                onChange={(e) => form.setData('current_pin', e.target.value.replace(/\D/g, '').slice(0, 6))}
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
            onChange={(e) => form.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 6))}
            required
          />

          <Field
            label="Confirm PIN"
            type="password"
            inputMode="numeric"
            autoComplete="new-password"
            maxLength={6}
            placeholder="Re-enter PIN"
            value={form.data.pin_confirmation}
            onChange={(e) => form.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 6))}
            required
          />
          {form.errors.pin && <p className="-mt-2 text-sm text-danger">{form.errors.pin}</p>}

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
