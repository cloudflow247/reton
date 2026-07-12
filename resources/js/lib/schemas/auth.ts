import { z } from 'zod'

const trimmed = (msg: string) => z.string().trim().min(1, msg)

const namePart = z
  .string()
  .trim()
  .max(60, 'Must be 60 characters or fewer')
  .regex(/^[\p{L}\p{M}\s'\-.]+$/u, 'Letters, spaces, hyphens, and apostrophes only')

export const loginSchema = z.object({
  email: trimmed('Email is required').email('Enter a valid email address'),
  password: trimmed('Password is required'),
  remember: z.boolean(),
  website: z.string().max(0).optional().or(z.literal('')),
})

export type LoginFormValues = z.infer<typeof loginSchema>

const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters')
  .regex(/[a-zA-Z]/, 'Password must contain letters and numbers')
  .regex(/\d/, 'Password must contain letters and numbers')

export const registerSchema = z
  .object({
    first_name: namePart.min(1, 'First name is required'),
    middle_name: z
      .string()
      .trim()
      .max(60, 'Must be 60 characters or fewer')
      .regex(/^[\p{L}\p{M}\s'\-.]*$/u, 'Letters, spaces, hyphens, and apostrophes only')
      .optional()
      .or(z.literal('')),
    last_name: namePart.min(1, 'Last name is required'),
    email: trimmed('Email is required')
      .email('Enter a valid email address')
      .max(255, 'Email is too long'),
    country_iso: z.string().length(2),
    country_code: z.string().min(1).max(6),
    phone_national: trimmed('Phone number is required')
      .max(15, 'Phone number is too long')
      .regex(/^[0-9\s\-]+$/, 'Enter digits only'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Please confirm your password'),
    website: z.string().max(0).optional().or(z.literal('')),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export type RegisterFormValues = z.infer<typeof registerSchema>

export const forgotPasswordSchema = z.object({
  email: trimmed('Email is required').email('Enter a valid email address'),
  website: z.string().max(0).optional().or(z.literal('')),
})

export type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

export const resetPasswordSchema = z
  .object({
    email: trimmed('Email is required').email('Enter a valid email address'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Please confirm your password'),
    token: z.string().min(1),
    website: z.string().max(0).optional().or(z.literal('')),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>
