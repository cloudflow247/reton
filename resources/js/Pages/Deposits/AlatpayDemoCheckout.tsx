import type { ReactNode } from 'react'
import { Head, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { AlatMark } from '@/components/PoweredByAlat'
import { Button, Pill } from '@/components/ui'
import { BankIcon, BoltIcon, CardIcon, PhoneIcon, ShieldIcon } from '@/components/icons'
import { ngn } from '@/lib/format'
import type { Deposit, PageProps } from '@/types'

type Props = PageProps<{
  deposit: Deposit
  cardOnly: boolean
}>

const allChannels = [
  { id: 'card', label: 'Card', sub: 'Visa · Mastercard · Verve', icon: CardIcon },
  { id: 'transfer', label: 'Bank transfer', sub: 'Any Nigerian bank', icon: BankIcon },
  { id: 'ussd', label: 'USSD', sub: 'Pay with phone code', icon: PhoneIcon },
  { id: 'alat', label: 'Wema / ALAT', sub: 'Direct debit + OTP', icon: BankIcon },
] as const

export default function AlatpayDemoCheckout() {
  const { deposit, cardOnly } = usePage<Props>().props
  const channels = cardOnly ? allChannels.filter((c) => c.id === 'card') : allChannels

  function simulatePay() {
    router.post(`/deposits/${deposit.id}/simulate-pay`)
  }

  return (
    <AppShell>
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        className="mx-auto max-w-md space-y-4"
      >
        <Head title="ALATPay checkout (demo)" />

        <div className="overflow-hidden rounded-3xl border border-line bg-surface shadow-lg">
          <div className="bg-gradient-to-br from-[#0e7e5c] to-[#094f39] px-5 py-5 text-white">
            <div className="flex items-center gap-3">
              <AlatMark size={40} />
              <div>
                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/70">ALATPay</p>
                <p className="font-display text-lg font-bold">Secure checkout</p>
              </div>
            </div>
            <p className="mt-4 font-num text-3xl font-bold">{ngn(deposit.amount)}</p>
            <p className="mt-1 text-sm text-white/75">Fund Reton wallet · {deposit.reference}</p>
          </div>

          <div className="space-y-4 p-5">
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm font-semibold">Choose payment method</p>
              <Pill tone="amber">Demo mode</Pill>
            </div>

            <p className="rounded-xl border border-amber/30 bg-amber/5 px-3 py-2 text-xs text-muted">
              Local demo checkout - simulates ALATPay&apos;s hosted page. In production you&apos;ll be redirected to
              pay.alatpay.ng instead.
            </p>

            <ul className="space-y-2">
              {channels.map((channel) => {
                const Icon = channel.icon
                return (
                  <li
                    key={channel.id}
                    className="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/60 px-3.5 py-3"
                  >
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-mint/10 text-mint">
                      <Icon size={18} />
                    </span>
                    <span className="min-w-0 flex-1">
                      <p className="text-sm font-medium">{channel.label}</p>
                      <p className="text-[11px] text-muted">{channel.sub}</p>
                    </span>
                  </li>
                )
              })}
            </ul>

            <Button className="flex w-full items-center justify-center gap-2" onClick={simulatePay}>
              <BoltIcon size={18} />
              Simulate successful payment
            </Button>

            <p className="flex items-center gap-1.5 text-[11px] text-muted">
              <ShieldIcon size={13} className="text-mint" />
              Powered by ALAT by Wema · PCI-DSS secure
            </p>
          </div>
        </div>

        <button
          type="button"
          onClick={() => router.get('/add-money', { reference: deposit.reference })}
          className="block w-full text-center text-sm text-muted hover:text-text"
        >
          ← Back to Reton
        </button>
      </motion.div>
    </AppShell>
  )
}

AlatpayDemoCheckout.layout = (page: ReactNode) => page
