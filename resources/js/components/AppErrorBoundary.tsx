import { Component, type ErrorInfo, type ReactNode } from 'react'

type Props = { children: ReactNode }
type State = { error: Error | null }

/**
 * Prevents a single page crash from wiping the entire Inertia shell
 * (blank white/gray screen with no recovery UI).
 */
export class AppErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('Reton UI crash', error, info.componentStack)
  }

  render() {
    if (this.state.error) {
      const detail =
        import.meta.env.DEV || import.meta.env.VITE_SHOW_ERROR_DETAIL === 'true'
          ? this.state.error.message
          : null

      return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-[#f2f6f3] px-6 text-center">
          <p className="font-display text-xl font-bold text-[#122a22]">Something went wrong</p>
          <p className="max-w-sm text-sm text-[#5d726b]">
            The page failed to load. Try refreshing — if it keeps happening, open Home again.
          </p>
          {detail && (
            <p className="max-w-md rounded-xl border border-[#e1eae5] bg-white px-3 py-2 font-mono text-xs text-[#5d726b]">
              {detail}
            </p>
          )}
          <div className="flex flex-wrap items-center justify-center gap-3">
            <button
              type="button"
              onClick={() => {
                this.setState({ error: null })
                window.location.reload()
              }}
              className="rounded-xl bg-[#0b7a57] px-4 py-2.5 text-sm font-semibold text-white"
            >
              Try again
            </button>
            <a
              href="/dashboard"
              className="rounded-xl border border-[#e1eae5] bg-white px-4 py-2.5 text-sm font-semibold text-[#122a22]"
            >
              Go to Home
            </a>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}
