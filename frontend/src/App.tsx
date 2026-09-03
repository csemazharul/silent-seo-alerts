import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AntApp, ConfigProvider } from 'antd'
import { StyleProvider } from '@ant-design/cssinjs'
import { useEffect } from 'react'
import { HashRouter, Route, Routes } from 'react-router'
import useAdminBarOffset from '@common/helpers/useAdminBarOffset'
import useWpMenuHighlight from '@common/helpers/useWpMenuHighlight'
import TopBar from '@components/TopBar'
import { buildTheme, palette } from '@config/theme'
import { useThemeStore } from '@/store/themeStore'
import DashboardPage from '@features/dashboard/DashboardPage'
import FlightLogPage from '@features/flightLog/FlightLogPage'
import IntegrationsPage from '@features/integrations/IntegrationsPage'
import SettingsPage from '@features/settings/SettingsPage'
import SiteWidePage from '@features/siteWide/SiteWidePage'
import TargetsPage from '@features/targets/TargetsPage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { refetchOnWindowFocus: false, retry: 1, staleTime: 15_000 }
  }
})

function Shell() {
  useWpMenuHighlight()

  return (
    <div className="h-full-wp flex flex-col" style={{ background: palette.canvas }}>
      <TopBar />

      <main className="scroller thin flex-1 overflow-auto">
        {/* Full width like the header, with a generous ceiling so cards do
            not stretch absurdly on a 4K display. Reading-weight pages
            (Settings, Integrations) cap their own column narrower. */}
        <div className="mx-auto w-full max-w-[1800px] px-6 py-6 lg:px-8">
          <Routes>
            <Route element={<DashboardPage />} path="/" />
            <Route element={<TargetsPage />} path="/pages" />
            <Route element={<FlightLogPage />} path="/log" />
            <Route element={<SiteWidePage />} path="/site" />
            <Route element={<IntegrationsPage />} path="/integrations" />
            <Route element={<SettingsPage />} path="/settings" />
          </Routes>
        </div>
      </main>
    </div>
  )
}

export default function App() {
  const resolved = useThemeStore(state => state.resolved)
  const compact = useThemeStore(state => state.compact)
  const syncSystem = useThemeStore(state => state.syncSystem)
  const toastTop = useAdminBarOffset()

  // Follow the OS preference live while the mode is set to "system".
  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return

    const query = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = () => syncSystem()

    query.addEventListener('change', onChange)

    return () => query.removeEventListener('change', onChange)
  }, [syncSystem])

  return (
    <StyleProvider hashPriority="high">
      <ConfigProvider theme={buildTheme(resolved === 'dark', compact)}>
        {/* Toasts sit below wp-admin's toolbar; antd sets their top inline,
            so the value has to be passed in rather than styled. */}
        <AntApp message={{ top: toastTop }}>
          <QueryClientProvider client={queryClient}>
            <HashRouter>
              <Shell />
            </HashRouter>
          </QueryClientProvider>
        </AntApp>
      </ConfigProvider>
    </StyleProvider>
  )
}
