import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Wordmark } from '../components/ui'
import { LockIcon } from '../components/icons'

export function AuthLayout({ children, title, sub }: { children: ReactNode; title: string; sub: string }) {
  return (
    <div className="mx-auto grid min-h-full max-w-6xl items-center gap-10 px-6 py-10 lg:grid-cols-2">
      {/* Thesis: the most characteristic thing about Reton is that money can come back. */}
      <section className="hidden lg:block">
        <Link to="/">
          <Wordmark />
        </Link>
        <h1 className="mt-10 font-display text-5xl font-bold leading-[1.05] tracking-tight">
          The first wallet with an
          <span className="text-mint"> undo button</span> for money.
        </h1>
        <p className="mt-5 max-w-md text-[15px] leading-relaxed text-muted">
          Send with callback protection, recover wrong transfers, and let real-time fraud checks watch every
          move. If something goes wrong, Reton can bring your money back.
        </p>
        <div className="mt-10 flex gap-3">
          {[
            ['Callback', 'Hold funds until you confirm'],
            ['Recovery', 'Claw back a wrong transfer'],
            ['Fraud', 'Blocked before it settles'],
          ].map(([k, v]) => (
            <div key={k} className="card flex-1 p-4">
              <div className="font-display text-sm font-semibold text-mint">{k}</div>
              <div className="mt-1 text-xs leading-snug text-muted">{v}</div>
            </div>
          ))}
        </div>
      </section>

      <section className="card mx-auto w-full max-w-md p-7 shield-glow">
        <Link to="/" className="lg:hidden">
          <Wordmark />
        </Link>
        <h2 className="mt-6 font-display text-2xl font-bold tracking-tight lg:mt-0">{title}</h2>
        <p className="mt-1 text-sm text-muted">{sub}</p>
        <div className="mt-6">{children}</div>
        <p className="mt-6 flex items-center justify-center gap-1.5 border-t border-line pt-4 text-xs text-muted">
          <LockIcon size={13} /> Bank-grade encryption · your money is protected
        </p>
      </section>
    </div>
  )
}
