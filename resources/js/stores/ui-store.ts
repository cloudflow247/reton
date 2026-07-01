import { create } from 'zustand'
import { persist } from 'zustand/middleware'

type Theme = 'light' | 'dark' | 'system'

type UiState = {
  theme: Theme
  balanceHidden: boolean
  setTheme: (theme: Theme) => void
  toggleBalanceHidden: () => void
  setBalanceHidden: (hidden: boolean) => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      theme: 'light',
      balanceHidden: false,
      setTheme: (theme) => set({ theme }),
      toggleBalanceHidden: () => set((s) => ({ balanceHidden: !s.balanceHidden })),
      setBalanceHidden: (balanceHidden) => set({ balanceHidden }),
    }),
    { name: 'reton-ui' },
  ),
)

export function resolveTheme(theme: Theme): 'light' | 'dark' {
  if (theme === 'system') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  return theme
}
