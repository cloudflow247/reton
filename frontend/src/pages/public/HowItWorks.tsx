import { Link } from 'react-router-dom'
import { ArrowRightIcon, ScanIcon, ShieldIcon, UndoIcon } from '../../components/icons'

const flows = [
  {
    Icon: ShieldIcon,
    name: 'Callback protection',
    steps: [
      'Pick Protected when you send. The money moves into escrow, not to the recipient.',
      'They see a pending payment. You can release it — or raise a callback to recall it.',
      'If you recall, they accept (you’re refunded) or reject (a Reton agent reviews and decides).',
    ],
  },
  {
    Icon: UndoIcon,
    name: 'Wrong-transfer recovery',
    steps: [
      'Sent a normal transfer to the wrong account? Report it from the Protection tab.',
      'If eligible, we freeze that amount in the recipient’s wallet and notify them.',
      'They return it, or dispute — and if they go quiet, it escalates for review.',
    ],
  },
  {
    Icon: ScanIcon,
    name: 'Adding & withdrawing money',
    steps: [
      'Add money by transferring to a one-time virtual account; your wallet credits on confirmation.',
      'Withdraw to any bank — funds are reserved, then settle once the bank confirms.',
      'Missed a webhook? Automatic reconciliation settles or reverses it safely.',
    ],
  },
]

export function HowItWorks() {
  return (
    <div className="mx-auto max-w-5xl px-5 py-20">
      <h1 className="font-display text-4xl font-bold tracking-tight sm:text-5xl">How Reton works</h1>
      <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted">
        Three flows do the heavy lifting. Each one is fully logged, reversible where it should be, and final
        where it must be.
      </p>

      <div className="mt-14 space-y-10">
        {flows.map((flow) => (
          <section key={flow.name} className="card p-7">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <flow.Icon size={22} />
              </span>
              <h2 className="font-display text-xl font-bold tracking-tight">{flow.name}</h2>
            </div>
            <div className="mt-6 grid gap-6 sm:grid-cols-3">
              {flow.steps.map((step, i) => (
                <div key={i} className="border-t border-line pt-4">
                  <span className="font-num text-sm font-semibold text-mint">0{i + 1}</span>
                  <p className="mt-2 text-sm leading-relaxed text-muted">{step}</p>
                </div>
              ))}
            </div>
          </section>
        ))}
      </div>

      <div className="mt-14">
        <Link
          to="/register"
          className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
        >
          Try it yourself <ArrowRightIcon size={17} />
        </Link>
      </div>
    </div>
  )
}
