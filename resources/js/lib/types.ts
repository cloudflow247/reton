export type User = {
  id: string
  name: string
  email: string
  phone: string | null
  country: string
  status: string
  email_verified: boolean
  phone_verified: boolean
  has_transaction_pin: boolean
}

export type Wallet = {
  id: string
  account_number: string | null
  currency: string
  balance: number
  held_balance: number
  available_balance: number
  status: string
}

export type StatementEntry = {
  id: string
  direction: 'debit' | 'credit'
  amount: number
  currency: string
  created_at: string
  transaction: {
    reference: string
    type: string
    status: string
    description?: string
  } | null
}

export type Transfer = {
  id: string
  reference: string
  type: 'normal' | 'protected'
  status: string
  currency: string
  amount: number
  note: string | null
  sender_wallet_id: string
  receiver_wallet_id: string
  metadata?: {
    purpose?: string
    order_id?: string
    listing_id?: string
    listing_title?: string
  } | null
  hold?: { status: string; expires_at: string | null } | null
  created_at: string
}

export type DigitalListing = {
  id: string
  seller_id: string
  seller_name?: string
  title: string
  description: string
  price: number
  currency: string
  status: string
  created_at: string
  share_url?: string
  app_url?: string
  is_owner?: boolean
  can_purchase?: boolean
}

export type DigitalOrder = {
  id: string
  listing_id: string
  buyer_id: string
  seller_id: string
  transfer_id: string | null
  status: 'paid_held' | 'delivered' | 'completed' | 'disputed' | 'refunded'
  delivered_at: string | null
  completed_at: string | null
  delivery_deadline_at: string | null
  buyer_satisfied: boolean | null
  dispute_category: string | null
  created_at: string
  listing?: DigitalListing
  role?: 'buyer' | 'seller' | null
  delivery?: {
    title?: string
    content?: string
    description?: string
    delivered_at?: string
    integrity_verified?: boolean
  } | null
  escrow?: {
    step: number
    step_label: string
    next_action: string | null
    allowed_disputes: { value: string; label: string; hint: string }[]
    delivery_deadline_at: string | null
    confirm_deadline_at: string | null
    dispute_grace_ends_at: string | null
    can_dispute_not_delivered: boolean
    can_dispute_quality: boolean
    auto_refund_at: string | null
    seller_trust_score: number
    listing_description: string | null
  } | null
}

export type ProtectionEvent = {
  id: string
  action: string
  notes: string | null
  actor_type: string
  created_at: string
}

export type Callback = {
  id: string
  reference: string
  transfer_id: string
  status: 'pending' | 'escalated' | 'released' | 'refunded'
  reason: string | null
  resolution: string | null
  responds_by: string | null
  created_at: string
  events?: ProtectionEvent[]
}

export type Recovery = {
  id: string
  reference: string
  transfer_id: string
  status: 'held' | 'escalated' | 'returned' | 'declined'
  reason: string | null
  resolution: string | null
  amount: number
  fee: number
  currency: string
  expires_at: string | null
  created_at: string
  events?: ProtectionEvent[]
}

export type Deposit = {
  id: string
  reference: string
  status: string
  amount: number
  currency: string
  virtual_account: {
    account_number: string
    bank_name: string
    account_name: string
  } | null
}

export type BillCategory = 'airtime' | 'data' | 'electricity' | 'cable_tv' | 'rrr'

export type BillCategoryOption = {
  value: BillCategory
  label: string
  fixed_amount: boolean
}

export type Bill = {
  id: string
  reference: string
  provider: string
  status: 'pending' | 'completed' | 'failed'
  category: BillCategory
  category_label: string
  biller_code: string
  biller_name: string
  customer_reference: string
  amount: number
  currency: string
  failure_reason: string | null
  processed_at: string | null
  created_at: string
}
