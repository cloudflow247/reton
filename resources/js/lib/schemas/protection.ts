import { z } from 'zod'

export function protectionActionSchema(options: { needReason: boolean; needPin: boolean }) {
  return z.object({
    pin: options.needPin
      ? z
          .string()
          .min(4, 'PIN must be 4 digits')
          .max(6, 'PIN must be at most 6 digits')
          .regex(/^\d+$/, 'PIN must be numbers only')
      : z.string().optional(),
    reason: options.needReason
      ? z.string().min(3, 'Please add a short note').max(500)
      : z.string().optional(),
  })
}

export type ProtectionActionValues = z.infer<ReturnType<typeof protectionActionSchema>>
