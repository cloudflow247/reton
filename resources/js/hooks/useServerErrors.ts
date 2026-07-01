import { useEffect } from 'react'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

/** Map Inertia validation errors onto react-hook-form field errors. */
export function useServerErrors<T extends FieldValues>(
  serverErrors: Record<string, string | string[] | undefined>,
  setError: UseFormSetError<T>,
) {
  useEffect(() => {
    Object.entries(serverErrors).forEach(([key, value]) => {
      if (!value) return
      const message = Array.isArray(value) ? value[0] : value
      setError(key as Path<T>, { type: 'server', message })
    })
  }, [serverErrors, setError])
}

export function fieldErrorMessage(
  client?: { message?: string },
  server?: string,
): string | undefined {
  return server ?? client?.message
}
