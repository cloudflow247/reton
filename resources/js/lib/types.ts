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
  is_admin?: boolean
  notify_email?: boolean
  notify_sms?: boolean
}

export type KycProfile = {
  tier: 1 | 2 | 3
  tier_label: string
  bvn_verified: boolean
  bvn_last4: string | null
  nin_verified: boolean
  nin_last4: string | null
  date_of_birth: string | null
  address_line1: string | null
  city: string | null
  state: string | null
  limits: {
    single_transaction_max: number
    daily_inflow_max: number
    wallet_balance_max: number
    static_wallet_type: 'individual' | 'collection'
  }
  next_tier: 2 | 3 | null
}

export type StaticAccount = {
  id: string
  wallet_id: string
  wallet_type: 'individual' | 'collection'
  status: 'pending_otp' | 'active' | 'failed'
  account_number: string | null
  account_name: string | null
  bank_name: string | null
  needs_otp?: boolean
  created_at: string
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
    id?: string
    reference: string
    type: string
    status: string
    description?: string
    amount?: number
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
  sender?: { name: string | null; reton_id: string | null } | null
  receiver?: { name: string | null; reton_id: string | null } | null
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
  item_code?: string
  seller_id: string
  seller_name?: string
  item_type: 'digital' | 'physical'
  title: string
  description: string
  condition?: string | null
  condition_label?: string | null
  weight_grams?: number | null
  specs?: Record<string, string> | null
  handling_notes?: string | null
  verification_status?: string | null
  verification_score?: number | null
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
  status: 'paid_held' | 'awaiting_verification' | 'shipped' | 'delivered' | 'completed' | 'disputed' | 'refunded'
  listing_snapshot?: Record<string, unknown> | null
  verification_status?: string | null
  verification_score?: number | null
  shipping_fee?: number
  shipped_at: string | null
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
    specs?: Record<string, string>
    condition?: string | null
    delivered_at?: string
    integrity_verified?: boolean
    shipment?: {
      tracking_number: string
      carrier: string
      pod_reference?: string | null
    } | null
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
    verification_score?: number | null
    item_type?: 'digital' | 'physical'
    listing_description: string | null
    listing_snapshot?: Record<string, unknown> | null
    shipment?: {
      tracking_number: string
      dropoff_code?: string | null
      status: string
      status_label: string
      carrier: string
      hub_name?: string | null
      hub_address?: { line1?: string; city?: string; state?: string; phone?: string } | null
      hub_verification_status?: string | null
      hub_verification_score?: number | null
      hub_verification_report?: Record<string, unknown> | null
      events: { status: string; at: string; note: string }[]
      estimated_delivery_at?: string | null
    } | null
  } | null
}

export type ProtectionEvent = {
  id: string
  action: string
  notes: string | null
  actor_type: string
  created_at: string
}

export type CallbackFairness = {
  sender_score: number
  receiver_score: number
  category: string
  evidence_score: number | null
  resolution: string
  reasons: string[]
  hold_hours?: number | null
  response_hours?: number | null
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
  fairness?: CallbackFairness | null
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
  provider_reference?: string | null
  status: string
  amount: number
  currency: string
  method?: 'bank_transfer' | 'alatpay_checkout' | 'alatpay_card'
  virtual_account: {
    account_number: string
    bank_name: string
    account_name: string
  } | null
  payment_link_url?: string | null
  description?: string | null
  bank_transfer?: {
    narration?: string | null
    payer_name?: string | null
    bank_name?: string | null
    channel?: string | null
    provider_reference?: string | null
    provider_paid_at?: string | null
  } | null
  paid_at?: string | null
}

export type BillCategory = 'airtime' | 'data' | 'electricity' | 'cable_tv' | 'betting' | 'rrr'

export type BillCategoryOption = {
  value: BillCategory
  label: string
  fixed_amount: boolean
}

export type Payout = {
  id: string
  reference: string
  provider: string
  provider_reference: string | null
  status: 'pending' | 'completed' | 'failed'
  amount: number
  currency: string
  bank_code: string
  account_number: string
  account_name: string
  failure_reason: string | null
  processed_at: string | null
  created_at: string
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
