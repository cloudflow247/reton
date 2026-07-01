import { z } from 'zod'
import { toMinor } from '@/lib/format'

export const transferTypeSchema = z.enum(['normal', 'protected'])

export const sendTransferSchema = z.object({
  from_wallet_id: z.string().uuid('Wallet unavailable'),
  to_wallet_id: z.string().uuid('Resolve the recipient account first'),
  account: z
    .string()
    .min(1, 'Account number is required')
    .regex(/^\d{10}$/, 'Enter a 10-digit Reton account number'),
  amount: z
    .string()
    .min(1, 'Enter an amount')
    .refine((value) => toMinor(value) >= 1, 'Amount must be at least ₦0.01'),
  pin: z
    .string()
    .min(4, 'PIN must be at least 4 digits')
    .max(6, 'PIN must be at most 6 digits')
    .regex(/^\d+$/, 'PIN must be numeric'),
  type: transferTypeSchema,
})

export type SendTransferFormValues = z.infer<typeof sendTransferSchema>

export function sendTransferRefinements(
  values: SendTransferFormValues,
  availableBalance: number,
): Partial<Record<keyof SendTransferFormValues, string>> {
  const errors: Partial<Record<keyof SendTransferFormValues, string>> = {}
  const minor = toMinor(values.amount)

  if (minor > availableBalance) {
    errors.amount = 'That’s more than your available balance.'
  }

  return errors
}
