import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useEffect } from 'react'
import { resolveTheme, useUiStore } from '@/stores/ui-store'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

function ThemeSync() {
  const theme = useUiStore((s) => s.theme)

  useEffect(() => {
    const resolved = resolveTheme(theme)
    document.documentElement.dataset.theme = resolved
    document.documentElement.classList.toggle('dark', resolved === 'dark')
  }, [theme])

  return null
}

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeSync />
      {children}
    </QueryClientProvider>
  )
}
