import { z } from 'zod'

const trimmed = (msg: string) => z.string().trim().min(1, msg)

export const loginSchema = z.object({
  email: trimmed('Email is required').email('Enter a valid email address'),
  password: trimmed('Password is required'),
  remember: z.boolean().optional().default(false),
})

export type LoginFormValues = z.infer<typeof loginSchema>

const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters')
  .regex(/[a-zA-Z]/, 'Password must contain letters and numbers')
  .regex(/\d/, 'Password must contain letters and numbers')

export const registerSchema = z
  .object({
    name: trimmed('Name is required').max(120, 'Name must be 120 characters or fewer'),
    email: trimmed('Email is required')
      .email('Enter a valid email address')
      .max(255, 'Email is too long'),
    phone: trimmed('Phone is required').max(20, 'Phone must be 20 characters or fewer'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Please confirm your password'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export type RegisterFormValues = z.infer<typeof registerSchema>

export const forgotPasswordSchema = z.object({
  email: trimmed('Email is required').email('Enter a valid email address'),
})

export type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

export const resetPasswordSchema = z
  .object({
    email: trimmed('Email is required').email('Enter a valid email address'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Please confirm your password'),
    token: z.string().min(1),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>
