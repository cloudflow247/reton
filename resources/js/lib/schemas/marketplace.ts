import { z } from 'zod'

const listingBase = {
  title: z.string().min(3, 'Title is too short').max(120),
  description: z.string().min(10, 'Add at least a sentence about what the buyer gets').max(2000),
  price: z.coerce
    .number({ invalid_type_error: 'Enter a price in Naira' })
    .min(1, 'Minimum price is ₦1'),
  listing_accurate: z
    .boolean()
    .refine((v) => v === true, { message: 'Confirm your listing is honest and complete' }),
}

export const createDigitalListingSchema = z.object({
  item_type: z.literal('digital'),
  ...listingBase,
  delivery_payload: z
    .string()
    .min(3, 'Add the key, link, or code the buyer will receive')
    .max(5000, 'Delivery content is too long'),
})

export const createPhysicalListingSchema = z.object({
  item_type: z.literal('physical'),
  ...listingBase,
  description: z.string().min(80, 'Physical items need a detailed description (80+ characters)').max(2000),
  condition: z.enum(['new', 'like_new', 'good', 'fair']),
  weight_grams: z.coerce.number().min(100, 'Enter package weight in grams').max(50000),
  spec_brand: z.string().min(2, 'Brand or maker is required').max(80),
  spec_detail: z.string().min(2, 'Add size, colour, or model').max(120),
  handling_notes: z.string().max(500).optional(),
})

export const createListingSchema = z.discriminatedUnion('item_type', [
  createDigitalListingSchema,
  createPhysicalListingSchema,
])

export type CreateListingValues = z.infer<typeof createListingSchema>

export const purchaseDigitalListingSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
})

export const purchasePhysicalListingSchema = purchaseDigitalListingSchema.extend({
  buyer_accepts_description: z
    .boolean()
    .refine((v) => v === true, { message: 'Confirm the item description before paying' }),
  shipping_line1: z.string().min(5, 'Street address is required').max(120),
  shipping_line2: z.string().max(120).optional(),
  shipping_city: z.string().min(2, 'City is required').max(80),
  shipping_state: z.string().min(2, 'State is required').max(80),
  shipping_phone: z.string().min(10, 'Phone number is required').max(20),
})

export type PurchaseListingValues = z.infer<typeof purchaseDigitalListingSchema>
export type PurchasePhysicalListingValues = z.infer<typeof purchasePhysicalListingSchema>

export const confirmOrderSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
})

export type ConfirmOrderValues = z.infer<typeof confirmOrderSchema>

export const disputeOrderSchema = z.object({
  pin: z.string().min(4).max(6).regex(/^\d+$/, 'PIN must be numbers only'),
  category: z.enum([
    'not_delivered',
    'not_as_described',
    'invalid_item',
    'damaged_in_transit',
    'wrong_item',
  ]),
  details: z.string().min(10, 'Explain what went wrong').max(1000),
})

export type DisputeOrderValues = z.infer<typeof disputeOrderSchema>

export const deliverOrderSchema = z.object({
  attest_matches_listing: z.literal(true, {
    errorMap: () => ({ message: 'Confirm your delivery matches the listing' }),
  }),
})

export type DeliverOrderValues = z.infer<typeof deliverOrderSchema>

export const shipOrderSchema = z.object({
  pickup_line1: z.string().min(5, 'Pickup address is required').max(120),
  pickup_city: z.string().min(2, 'City is required').max(80),
  pickup_state: z.string().min(2, 'State is required').max(80),
  pickup_phone: z.string().min(10, 'Phone is required').max(20),
  attest_matches_listing: z.literal(true, {
    errorMap: () => ({ message: 'Confirm the package matches your listing description' }),
  }),
})

export type ShipOrderValues = z.infer<typeof shipOrderSchema>

// Backwards-compatible aliases
export const purchaseListingSchema = purchaseDigitalListingSchema
