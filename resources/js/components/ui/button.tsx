import { cva, type VariantProps } from 'class-variance-authority'
import type { ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint/40 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-mint text-white hover:bg-mint-strong',
        secondary: 'border border-line bg-surface text-text hover:border-mint/30 hover:text-mint',
        ghost: 'text-muted hover:bg-surface-2 hover:text-text',
        danger: 'bg-danger text-white hover:bg-danger/90',
      },
      size: {
        default: 'px-4 py-2.5 text-sm',
        sm: 'px-3 py-1.5 text-xs',
        lg: 'px-5 py-3 text-base',
        icon: 'h-9 w-9 p-0',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
  VariantProps<typeof buttonVariants>

export function Button({ className, variant, size, ...props }: ButtonProps) {
  return <button className={cn(buttonVariants({ variant, size }), className)} {...props} />
}

export { buttonVariants }
