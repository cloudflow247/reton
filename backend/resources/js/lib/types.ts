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
  hold?: { status: string; expires_at: string | null } | null
  created_at: string
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
