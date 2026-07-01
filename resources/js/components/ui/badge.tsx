import type { HTMLAttributes } from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wide',
  {
    variants: {
      variant: {
        default: 'bg-mint/10 text-mint',
        warning: 'bg-amber/12 text-amber',
        danger: 'bg-danger/10 text-danger',
        muted: 'bg-surface-2 text-muted',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)

export type BadgeProps = HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>

export function Badge({ className, variant, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />
}
