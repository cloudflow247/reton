import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { AmountField, Button, Card, CopyRow, Pill } from '@/components/ui'
import { BankIcon, BoltIcon, LockIcon, PlusIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import type { SharedProps } from '@/types'

export default function AddMoney() {
  const { auth, flash } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]
  const deposit = flash.deposit

  const [amount, setAmount] = useState('')
  const [dismissed, setDismissed] = useState(false)
  const form = useForm({ wallet_id: wallet?.id ?? '', amount: 0 })
  const minor = toMinor(amount)

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({ ...data, amount: toMinor(amount) }))
    form.post('/deposits', { preserveScroll: true })
  }

  if (deposit && !dismissed) {
    const va = deposit.virtual_account
    return (
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
        className="mx-auto max-w-lg space-y-5"
      >
        <Head title="Add money" />

        {/* Emerald hero — the transfer instruction reads with confidence. */}
        <div className="brand-card sheen relative overflow-hidden p-6 text-white shield-glow">
          <div aria-hidden className="pointer-events-none absolute inset-0">
            <div className="blob absolute -right-12 -top-16 h-44 w-44 bg-white/10 blur-2xl" />
          </div>
          <div className="relative flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-sm">
                <BankIcon size={22} />
              </span>
              <div>
                <h2 className="font-display text-lg font-bold tracking-tight">Transfer to fund</h2>
                <p className="text-sm text-white/75">Add money to your wallet</p>
              </div>
            </div>
            <Pill tone="amber">Awaiting transfer</Pill>
          </div>
        </div>

        <Card className="space-y-5">
          <p className="flex items-start gap-2 text-sm leading-relaxed text-muted">
            <BoltIcon size={16} className="mt-0.5 shrink-0 text-mint" />
            Send a bank transfer to the one-time account below. Your wallet is credited automatically the moment it
            arrives.
          </p>
          <div className="divide-y divide-line rounded-2xl border border-line bg-surface-2/50 px-4">
            <CopyRow label="Bank" value={va?.bank_name ?? '—'} />
            <CopyRow label="Account number" value={va?.account_number ?? '—'} mono />
            <CopyRow label="Account name" value={va?.account_name ?? '—'} />
          </div>
          <p className="flex items-center gap-1.5 text-xs text-muted">
            <LockIcon size={13} /> One-time account · expires after this deposit · Ref {deposit.reference}
          </p>
          <Button
            variant="ghost"
            className="w-full"
            onClick={() => {
              setDismissed(true)
              setAmount('')
              // Drop the flashed deposit so a reload doesn't resurface it.
              router.get('/add-money', {}, { preserveState: false })
            }}
          >
            Fund a different amount
          </Button>
        </Card>
      </motion.div>
    )
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Add money" />

      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight">Add money</h1>
        <p className="mt-1 text-sm text-muted">We’ll spin up a one-time account to fund your wallet.</p>
      </div>

      <Card className="space-y-6">
        <form onSubmit={submit} className="space-y-5">
          <div>
            <AmountField value={amount} onChange={setAmount} invalid={!!form.errors.amount} />
            {minor > 0 && (
              <p className="mt-2 text-xs text-muted">
                You’re funding <span className="font-num font-semibold text-text">{ngn(minor)}</span>
              </p>
            )}
          </div>

          {form.errors.amount && <p className="text-sm text-danger">{form.errors.amount}</p>}
          {flash.error && <p className="text-sm text-danger">{flash.error}</p>}

          <Button
            type="submit"
            loading={form.processing}
            disabled={minor <= 0}
            className="flex w-full items-center justify-center gap-2"
          >
            <PlusIcon size={18} /> Generate account
          </Button>
        </form>

        <div className="grid grid-cols-1 gap-2.5 border-t border-line pt-5">
          <p className="flex items-center gap-2 text-xs text-muted">
            <BoltIcon size={14} className="shrink-0 text-mint" /> Credited automatically — usually within seconds of
            your transfer.
          </p>
          <p className="flex items-center gap-2 text-xs text-muted">
            <ShieldIcon size={14} className="shrink-0 text-mint" /> Deposits are reconciled and ledger-backed — every
            kobo is accounted for.
          </p>
        </div>
      </Card>
    </motion.div>
  )
}

AddMoney.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
