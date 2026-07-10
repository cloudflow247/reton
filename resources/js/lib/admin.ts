import { usePage } from '@inertiajs/react'
import type { PageProps } from '@/types'

/** Base path for the platform admin panel (e.g. /control-x7k9). */
export function useAdminBase(): string {
  const { adminPath } = usePage<PageProps>().props
  return adminPath ?? '/admin'
}

/** Build an admin URL from a known base path (safe inside event handlers). */
export function buildAdminUrl(base: string, path = ''): string {
  if (!path) return base
  return `${base}/${path.replace(/^\//, '')}`
}

/** @deprecated Prefer `useAdminBase()` + `buildAdminUrl()` — only call during render. */
export function adminUrl(path = ''): string {
  const base = useAdminBase()
  return buildAdminUrl(base, path)
}
