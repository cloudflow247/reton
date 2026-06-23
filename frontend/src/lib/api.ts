import axios, { type AxiosError } from 'axios'
import { useAuth } from '../store/auth'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = useAuth.getState().token
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error: AxiosError) => {
    if (error.response?.status === 401 && useAuth.getState().token) {
      useAuth.getState().logout()
    }
    return Promise.reject(error)
  },
)

/** The Reton envelope: { success, message, data, code, errors }. */
export type Envelope<T> = {
  success: boolean
  message: string
  data: T
  code?: string
  errors?: Record<string, string[]>
}

/** Pull a human message out of an API error, preferring field errors. */
export function apiError(err: unknown): string {
  const e = err as AxiosError<Envelope<unknown>>
  const body = e.response?.data
  if (body?.errors) {
    const first = Object.values(body.errors)[0]
    if (first?.[0]) return first[0]
  }
  return body?.message ?? 'Something went wrong. Please try again.'
}
