import type { FormEvent, ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BankIcon } from '@/components/icons'
import {
  FormPanel,
  InfoStrip,
  Page,
  PageHero,
  PageSteps,
  PinField,
  SelectField,
  SectionLabel,
  SuccessScreen,
  pageItem,
} from '@/components/page-kit'
import { AmountField, Button } from '@/components/ui'
import { deviceHeaders } from '@/lib/device'
import { ngn, shortDate, toMinor } from '@/lib/format'
import type { Payout } from '@/lib/types'
import type { PageProps } from '@/types'

type Bank = { code: string; name: string }

type Props = PageProps<{
  banks: Bank[]
  accountNameHint: string
  recentPayouts: Payout[]
  payoutsAvailable?: boolean
}>

function stepFor(bankCode: string, accountNumber: string, accountName: string, amount: string, pin: string): number {
  if (!bankCode || accountNumber.length < 10) return 1
  if (!accountName.trim()) return 2
  if (toMinor(amount) <= 0 || pin.length < 4) return 3
  return 4
}

export default function Withdraw({
  banks: banksProp,
  accountNameHint,
  recentPayouts: recentPayoutsProp,
  payoutsAvailable = true,
}: Props) {
  const { auth, flash } = usePage<Props>().props
  const wallet = auth.wallets[0]
  const done = flash.payout
  const banks = Array.isArray(banksProp) ? banksProp : []
  const recentPayouts = Array.isArray(recentPayoutsProp) ? recentPayoutsProp : []

  const [bankCode, setBankCode] = useState('')
  const [accountNumber, setAccountNumber] = useState('')
  const [accountName, setAccountName] = useState(accountNameHint)
  const [amount, setAmount] = useState('')
  const [pin, setPin] = useState('')

  const form = useForm({
    bank_code: '',
    account_number: '',
    account_name: '',
    amount: 0,
    pin: '',
  })
  const bank = useMemo(() => banks.find((b) => b.code === bankCode), [banks, bankCode])
  const minor = toMinor(amount)
  const overBalance = wallet ? minor > wallet.available_balance : false
  const step = stepFor(bankCode, accountNumber, accountName, amount, pin)

  const canSubmit =
    payoutsAvailable &&
    !!wallet &&
    minor >= 10000 &&
    !overBalance &&
    bankCode.length > 0 &&
    /^\d{10}$/.test(accountNumber) &&
    accountName.trim().length >= 3 &&
    pin.length >= 4

  function submit(e: FormEvent) {
    e.preventDefault()
    if (!wallet || !canSubmit) return

    form.transform(() => ({
      wallet_id: wallet.id,
      amount: minor,
      bank_code: bankCode,
      account_number: accountNumber,
      account_name: accountName.trim().toUpperCase(),
      pin,
    }))
    form.post('/withdraw', {
      headers: deviceHeaders(),
      preserveScroll: true,
      onSuccess: () => {
        setAmount('')
        setPin('')
      },
    })
  }

  if (done) {
    const ok = done.status === 'completed' || done.status === 'pending'
    return (
      <>
        <Head title="Withdrawal sent" />
        <SuccessScreen
          ok={ok}
          amount={done.amount}
          title={ok ? 'Withdrawal initiated' : 'Withdrawal failed'}
          subtitle={done.account_name}
          primaryLabel="Withdraw again"
          onPrimary={() => router.get('/withdraw', {}, { preserveState: false })}
          secondaryHref="/dashboard"
        >
          <div className="rounded-xl border border-line bg-surface-2/60 px-4 py-3 text-sm">
            <div className="flex justify-between gap-2">
              <span className="text-muted">Account</span>
              <span className="font-num">{done.account_number}</span>
            </div>
            <div className="mt-2 flex justify-between gap-2">
              <span className="text-muted">Status</span>
              <span className="font-medium capitalize">{done.status}</span>
            </div>
          </div>
          <p className="text-sm text-muted">
            {ok
              ? 'Funds are on the way. Most transfers arrive within a few minutes.'
              : done.failure_reason ?? 'Something went wrong - balance was not debited.'}
          </p>
        </SuccessScreen>
      </>
    )
  }

  return (
    <Page narrow>
      <Head title="Withdraw" />
      <PageHero
        icon={BankIcon}
        title="Withdraw"
        subtitle="To your bank - name must match profile"
        balance={wallet?.available_balance}
        tone="slate"
      />

      <InfoStrip tone="amber" title="Same-name only">
        Bank account must match <span className="font-semibold text-text">{auth.user?.name}</span>. Third-party
        accounts are blocked.
      </InfoStrip>

      {!payoutsAvailable && (
        <InfoStrip tone="amber" title="Withdrawals paused">
          Bank withdrawals are temporarily unavailable. Your balance is safe - try again shortly, or contact support.
        </InfoStrip>
      )}

      <PageSteps steps={['Bank', 'Name', 'Pay']} current={Math.min(step, 3)} />

      <FormPanel>
        <form onSubmit={submit} className="space-y-5">
          <SelectField
            id="bank"
            label="1 · Your bank"
            value={bankCode}
            onChange={setBankCode}
            options={banks.map((b) => ({ value: b.code, label: b.name }))}
            placeholder="Choose bank…"
            error={form.errors.bank_code}
          />

          <div>
            <label htmlFor="account" className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">
              2 · Account number
            </label>
            <input
              id="account"
              inputMode="numeric"
              maxLength={10}
              value={accountNumber}
              onChange={(e) => setAccountNumber(e.target.value.replace(/\D/g, '').slice(0, 10))}
              placeholder="10-digit NUBAN"
              className="field w-full px-4 py-3.5 font-num tracking-wide"
            />
            {form.errors.account_number && (
              <p className="mt-1.5 text-xs text-danger">{form.errors.account_number}</p>
            )}
          </div>

          <div>
            <label htmlFor="name" className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">
              3 · Account name
            </label>
            <input
              id="name"
              value={accountName}
              onChange={(e) => setAccountName(e.target.value.toUpperCase())}
              placeholder={accountNameHint}
              className="field w-full px-4 py-3.5 text-sm uppercase"
            />
            {form.errors.account_name && <p className="mt-1.5 text-xs text-danger">{form.errors.account_name}</p>}
          </div>

          <AmountField value={amount} onChange={setAmount} label="4 · Amount (min ₦100)" />
          {overBalance && <p className="text-xs text-danger">Exceeds available balance.</p>}
          {form.errors.amount && <p className="text-xs text-danger">{form.errors.amount}</p>}

          <PinField value={pin} onChange={setPin} error={form.errors.pin} label="5 · Transaction PIN" />

          {bank && accountNumber.length === 10 && (
            <motion.div
              variants={pageItem}
              initial="hidden"
              animate="show"
              className="rounded-xl border border-line bg-surface-2/50 px-4 py-3 text-sm"
            >
              <p className="text-xs font-semibold uppercase tracking-wide text-muted">Summary</p>
              <p className="mt-1 font-medium">{bank.name}</p>
              <p className="font-num text-muted">{accountNumber}</p>
              <p className="mt-2 font-num text-lg font-bold text-mint">{minor > 0 ? ngn(minor) : '-'}</p>
            </motion.div>
          )}

          <Button type="submit" className="w-full py-3.5" disabled={!canSubmit || form.processing}>
            {form.processing ? 'Processing…' : 'Withdraw to bank'}
          </Button>

          {!auth.user?.has_transaction_pin && (
            <p className="text-center text-xs text-muted">
              <Link href="/pin" className="font-semibold text-mint hover:underline">
                Set your PIN
              </Link>{' '}
              first.
            </p>
          )}
        </form>
      </FormPanel>

      {recentPayouts.length > 0 && (
        <>
          <SectionLabel>Recent withdrawals</SectionLabel>
          <div className="panel divide-y divide-line overflow-hidden p-0">
            {recentPayouts.map((p) => (
              <div key={p.id} className="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{p.account_name}</p>
                  <p className="text-xs text-muted">
                    {shortDate(p.created_at)} · <span className="capitalize">{p.status}</span>
                  </p>
                </div>
                <span className="shrink-0 font-num text-sm font-bold">−{ngn(p.amount)}</span>
              </div>
            ))}
          </div>
        </>
      )}
    </Page>
  )
}

Withdraw.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
