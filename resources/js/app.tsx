import { createInertiaApp } from '@inertiajs/react'
import type { ResolvedComponent } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createElement, type ReactNode } from 'react'
import { createRoot } from 'react-dom/client'
import { AppErrorBoundary } from '@/components/AppErrorBoundary'
import { ToastHost } from '@/components/ToastHost'
import { setupEcho } from '@/lib/broadcasting'
import { AppProviders } from '@/providers/AppProviders'
import '../css/app.css'

setupEcho()

const appName = import.meta.env.VITE_APP_NAME ?? 'Reton'

function renderWithLayout(
  Component: ResolvedComponent,
  pageProps: Record<string, unknown>,
  key: number | null,
): ReactNode {
  const page = createElement(Component, { key: key ?? undefined, ...pageProps })
  const layout = (Component as ResolvedComponent & {
    layout?: ((page: ReactNode) => ReactNode) | Array<typeof Component>
  }).layout

  if (typeof layout === 'function') {
    return (layout as (node: ReactNode) => ReactNode)(page)
  }

  return page
}

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : `${appName} — payments you can take back`),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),
  setup({ el, App, props }) {
    createRoot(el).render(
      <AppErrorBoundary>
        <AppProviders>
          <App {...props}>
            {({ Component, props: pageProps, key }) => (
              <>
                <ToastHost />
                {renderWithLayout(Component, pageProps as Record<string, unknown>, key)}
              </>
            )}
          </App>
        </AppProviders>
      </AppErrorBoundary>,
    )
  },
  progress: {
    color: '#0b7a57',
    delay: 0,
    includeCSS: true,
    showSpinner: false,
  },
})
