import { usePage } from '@inertiajs/react'
import type { PageProps } from '@/types'

/** Base path for the platform admin panel (e.g. /control-x7k9). */
export function useAdminBase(): string {
  const { adminPath } = usePage<PageProps>().props
  return adminPath ?? '/admin'
}

export function adminUrl(path = ''): string {
  const base = useAdminBase()
  if (!path) return base
  return `${base}/${path.replace(/^\//, '')}`
}
