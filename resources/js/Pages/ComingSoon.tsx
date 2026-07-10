import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BankIcon, BillIcon, CardIcon, SparkleIcon } from '@/components/icons'
import { pageItem, Page } from '@/components/page-kit'
import type { PageProps } from '@/types'

type Feature = 'withdraw' | 'bills' | 'cards'

type Props = PageProps<{
  feature: Feature
  title: string
  description: string
  ctaLabel: string
  ctaHref: string
}>

const icons: Record<Feature, (p: { size?: number; className?: string }) => JSX.Element> = {
  withdraw: BankIcon,
  bills: BillIcon,
  cards: CardIcon,
}

export default function ComingSoon({ feature, title, description, ctaLabel, ctaHref }: Props) {
  const Icon = icons[feature] ?? SparkleIcon

  return (
    <Page narrow>
      <Head title={`${title} · Coming soon`} />
      <motion.div
        variants={pageItem}
        initial="hidden"
        animate="show"
        className="flex flex-col items-center px-2 py-16 text-center sm:py-20"
      >
        <span className="relative flex h-16 w-16 items-center justify-center rounded-3xl bg-mint/10 text-mint">
          <Icon size={28} />
          <span className="absolute -right-1 -top-1 rounded-full bg-amber px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">
            Soon
          </span>
        </span>
        <p className="mt-5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Coming soon</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight text-text sm:text-3xl">{title}</h1>
        <p className="mt-3 max-w-sm text-sm leading-relaxed text-muted">{description}</p>
        <Link
          href={ctaHref}
          className="btn mt-8 bg-mint px-5 py-2.5 text-sm text-white shadow-[0_10px_24px_-14px_rgba(9,79,57,0.65)] hover:bg-mint-strong"
        >
          {ctaLabel}
        </Link>
      </motion.div>
    </Page>
  )
}

ComingSoon.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
