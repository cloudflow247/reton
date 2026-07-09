import { z } from 'zod'

export const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters')
  .regex(/[a-zA-Z]/, 'Password must contain letters and numbers')
  .regex(/\d/, 'Password must contain letters and numbers')

export const registerSchema = z
  .object({
    name: z.string().min(1, 'Name is required').max(120, 'Name must be 120 characters or fewer'),
    email: z
      .string()
      .min(1, 'Email is required')
      .email('Enter a valid email address')
      .max(255, 'Email is too long'),
    phone: z.string().min(1, 'Phone is required').max(20, 'Phone must be 20 characters or fewer'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Please confirm your password'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export type RegisterFormValues = z.infer<typeof registerSchema>
