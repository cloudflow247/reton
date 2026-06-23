import { useQuery } from '@tanstack/react-query'
import { api } from './api'
import type { Callback, Deposit, Recovery, StatementEntry, Transfer, User, Wallet } from './types'

export function useMe() {
  return useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      const { data } = await api.get('/auth/me')
      return data.data as { user: User; wallets: Wallet[] }
    },
  })
}

export function useActivity(walletId: string | undefined) {
  return useQuery({
    queryKey: ['activity', walletId],
    enabled: !!walletId,
    queryFn: async () => {
      const { data } = await api.get(`/wallets/${walletId}/transactions`)
      return data.data as StatementEntry[]
    },
  })
}

export function useTransfers() {
  return useQuery({
    queryKey: ['transfers'],
    queryFn: async () => {
      const { data } = await api.get('/transfers')
      return data.data as Transfer[]
    },
  })
}

export function useDeposits() {
  return useQuery({
    queryKey: ['deposits'],
    queryFn: async () => {
      const { data } = await api.get('/deposits')
      return data.data as Deposit[]
    },
  })
}

export function useCallbacks() {
  return useQuery({
    queryKey: ['callbacks'],
    queryFn: async () => {
      const { data } = await api.get('/callbacks')
      return data.data as Callback[]
    },
  })
}

export function useRecoveries() {
  return useQuery({
    queryKey: ['recoveries'],
    queryFn: async () => {
      const { data } = await api.get('/recoveries')
      return data.data as Recovery[]
    },
  })
}
