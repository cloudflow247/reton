import { useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AuthLayout } from '@/components/AuthLayout'
import { MailIcon } from '@/components/icons'
import { Button } from '@/components/ui'
import type { PageProps } from '@/types'

type VerifyEmailProps = PageProps<{
  status?: string | null
}>

export default function VerifyEmail() {
  const { auth, status } = usePage<VerifyEmailProps>().props
  const email = auth.user?.email ?? 'your inbox'
  const [sending, setSending] = useState(false)

  function resend() {
    setSending(true)
    router.post('/email/verification-notification', {}, {
      preserveScroll: true,
      onFinish: () => setSending(false),
    })
  }

  return (
    <AuthLayout title="Check your email" sub="One quick step before your wallet goes live.">
      <Head title="Verify email" />

      <div className="flex flex-col items-center rounded-2xl border border-mint/20 bg-mint/[0.05] px-5 py-8 text-center">
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-mint/15 text-mint">
          <MailIcon size={28} />
        </span>
        <p className="mt-4 text-sm leading-relaxed text-muted">
          We sent a verification link to
          <br />
          <strong className="text-text">{email}</strong>
        </p>
        <p className="mt-2 text-xs text-muted">Open the email and tap <strong>Verify email address</strong>. Check spam if you don't see it within a minute.</p>
      </div>

      {status === 'verification-link-sent' && (
        <p className="mt-4 rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-center text-sm text-mint">
          A fresh verification link is on its way.
        </p>
      )}

      <div className="mt-6 space-y-3">
        <Button type="button" variant="secondary" loading={sending} onClick={resend} className="w-full">
          Resend verification email
        </Button>
        <Link
          href="/logout"
          method="post"
          as="button"
          className="btn w-full border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-muted transition hover:text-text"
        >
          Sign out
        </Link>
      </div>
    </AuthLayout>
  )
}
