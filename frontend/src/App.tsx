import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AntApp, ConfigProvider, Layout, Menu } from 'antd'
import { StyleProvider } from '@ant-design/cssinjs'
import {
  ApiOutlined,
  DashboardOutlined,
  FileSearchOutlined,
  GlobalOutlined,
  SettingOutlined,
  UnorderedListOutlined
} from '@ant-design/icons'
import { useEffect } from 'react'
import { HashRouter, Link, Route, Routes, useLocation } from 'react-router'
import { __ } from '@common/helpers/i18nWrap'
import useAdminBarOffset from '@common/helpers/useAdminBarOffset'
import useWpMenuHighlight from '@common/helpers/useWpMenuHighlight'
import ThemeSwitch from '@components/ThemeSwitch'
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

const NAV = [
  { key: '/', icon: <DashboardOutlined />, label: __('Dashboard') },
  { key: '/pages', icon: <UnorderedListOutlined />, label: __('Monitored Pages') },
  { key: '/log', icon: <FileSearchOutlined />, label: __('Flight Log') },
  { key: '/site', icon: <GlobalOutlined />, label: __('Site-wide') },
  { key: '/integrations', icon: <ApiOutlined />, label: __('Integrations') },
  { key: '/settings', icon: <SettingOutlined />, label: __('Settings') }
]

function Shell() {
  const location = useLocation()
  useWpMenuHighlight()

  return (
    <Layout className="h-full-wp">
      <Layout.Sider
        breakpoint="lg"
        collapsedWidth={64}
        width={232}
        style={{ borderRight: `1px solid ${palette.line}` }}
      >
        <div className="px-5 pb-4 pt-5">
          <div className="flex items-center gap-2">
            <span
              className="flex h-7 w-7 items-center justify-center rounded-lg text-sm"
              style={{ background: palette.primarySoft, color: palette.primary }}
            >
              <GlobalOutlined />
            </span>
            <span className="text-sm font-semibold leading-tight" style={{ color: palette.ink }}>
              {__('SEO Change Monitor')}
            </span>
          </div>
        </div>

        <Menu
          items={NAV.map(item => ({ ...item, label: <Link to={item.key}>{item.label}</Link> }))}
          mode="inline"
          selectedKeys={[location.pathname]}
          style={{ borderInlineEnd: 0 }}
        />

        <div className="mt-auto hidden px-4 pb-5 pt-4 lg:block">
          <ThemeSwitch />
        </div>
      </Layout.Sider>

      <Layout.Content className="overflow-auto">
        <div className="mx-auto w-full max-w-6xl px-8 py-7">
          <Routes>
            <Route element={<DashboardPage />} path="/" />
            <Route element={<TargetsPage />} path="/pages" />
            <Route element={<FlightLogPage />} path="/log" />
            <Route element={<SiteWidePage />} path="/site" />
            <Route element={<IntegrationsPage />} path="/integrations" />
            <Route element={<SettingsPage />} path="/settings" />
          </Routes>
        </div>
      </Layout.Content>
    </Layout>
  )
}

export default function App() {
  const resolved = useThemeStore(state => state.resolved)
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
      <ConfigProvider theme={buildTheme(resolved === 'dark')}>
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
