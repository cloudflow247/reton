import { z } from 'zod'

export const createListingSchema = z.object({
  title: z.string().min(3, 'Title is too short').max(120),
  description: z.string().min(10, 'Add at least a sentence about what the buyer gets').max(2000),
  price: z.coerce
    .number({ invalid_type_error: 'Enter a price in Naira' })
    .min(1, 'Minimum price is ₦1'),
  delivery_payload: z
    .string()
    .min(3, 'Add the key, link, or code the buyer will receive')
    .max(5000, 'Delivery content is too long'),
  listing_accurate: z
    .boolean()
    .refine((v) => v === true, { message: 'Confirm your listing is honest and complete' }),
})

export type CreateListingValues = z.infer<typeof createListingSchema>

export const purchaseListingSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
})

export type PurchaseListingValues = z.infer<typeof purchaseListingSchema>

export const confirmOrderSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
})

export type ConfirmOrderValues = z.infer<typeof confirmOrderSchema>

export const disputeOrderSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
  category: z.enum(['not_delivered', 'not_as_described', 'invalid_item']),
  details: z.string().min(10, 'Explain what went wrong').max(1000),
})

export type DisputeOrderValues = z.infer<typeof disputeOrderSchema>

export const deliverOrderSchema = z.object({
  attest_matches_listing: z.literal(true, {
    errorMap: () => ({ message: 'Confirm your delivery matches the listing' }),
  }),
})

export type DeliverOrderValues = z.infer<typeof deliverOrderSchema>
