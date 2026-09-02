import {
  ApiOutlined,
  DashboardOutlined,
  EyeOutlined,
  FileSearchOutlined,
  GlobalOutlined,
  LoadingOutlined,
  SettingOutlined,
  UnorderedListOutlined
} from '@ant-design/icons'
import { Tooltip } from 'antd'
import { useEffect, useRef } from 'react'
import { Link, useLocation } from 'react-router'
import classNames from '@common/helpers/classNames'
import { __, sprintf } from '@common/helpers/i18nWrap'
import { absoluteTime, relativeTime } from '@common/helpers/datetime'
import ThemeSwitch from '@components/ThemeSwitch'
import { useDashboardSummary, useRunStatus } from '@/api/queries'
import { palette } from '@config/theme'
import type { ReactNode } from 'react'

const NAV = [
  { key: '/', icon: <DashboardOutlined />, label: __('Dashboard') },
  {
    key: '/pages',
    icon: <UnorderedListOutlined />,
    label: __('Monitored Pages')
  },
  { key: '/log', icon: <FileSearchOutlined />, label: __('Flight Log') },
  { key: '/site', icon: <GlobalOutlined />, label: __('Site-wide') },
  { key: '/integrations', icon: <ApiOutlined />, label: __('Integrations') },
  { key: '/settings', icon: <SettingOutlined />, label: __('Settings') }
]

interface Health {
  detail: string
  icon?: ReactNode
  label: string
  tone: string
}

/**
 * The health chip is the one piece of state worth carrying on every screen: it
 * answers "is the site fine right now" without a trip back to the dashboard.
 * Both queries are already cached by the dashboard, so this costs one request
 * on the screens that would not otherwise ask.
 */
function useHealth(): Health {
  const { data: summary } = useDashboardSummary()
  const { data: run } = useRunStatus()

  const pages = summary?.active_targets ?? 0
  const critical = summary?.open_counts.critical ?? 0
  const detail = pages === 1 ? __('1 page') : sprintf(__('%d pages'), pages)

  if (run?.status === 'running') {
    return { detail, icon: <LoadingOutlined spin />, label: __('Checking now'), tone: palette.info }
  }

  if (summary?.impaired || run?.status === 'impaired') {
    return { detail, label: __('Monitoring impaired'), tone: palette.warning }
  }

  if (critical > 0) {
    return {
      detail,
      label: critical === 1 ? __('1 critical issue') : sprintf(__('%d critical issues'), critical),
      tone: palette.critical
    }
  }

  return { detail, label: __('All clear'), tone: palette.success }
}

export default function TopBar() {
  const location = useLocation()
  const health = useHealth()
  const { data: summary } = useDashboardSummary()
  const navRef = useRef<HTMLElement>(null)

  const lastCheckedAt = summary?.last_run?.finished_at
  const checked = relativeTime(lastCheckedAt)
  const checkedTitle = checked
    ? sprintf(__('Last checked %s'), absoluteTime(lastCheckedAt) ?? checked)
    : __('No check has run yet')

  /*
   * Once the strip is narrow enough to scroll, the current tab can sit outside
   * it, and the screen then shows no active tab at all. Only the strip's own
   * scrollLeft is touched, so this can never scroll the page itself the way
   * scrollIntoView would.
   */
  useEffect(() => {
    const strip = navRef.current
    if (!strip) return

    const align = () => {
      const current = strip.querySelector('.scm-tab.is-current')
      if (!current) return

      const stripBox = strip.getBoundingClientRect()
      const tabBox = current.getBoundingClientRect()
      const margin = 12

      if (tabBox.left < stripBox.left) {
        strip.scrollLeft -= stripBox.left - tabBox.left + margin
      } else if (tabBox.right > stripBox.right) {
        strip.scrollLeft += tabBox.right - stripBox.right + margin
      }
    }

    // The strip is still resizing after the first paint: the health chip beside
    // it grows once its query resolves, and the window can be resized later. An
    // observer catches both, and fires once on observe for the initial run.
    const observer = new ResizeObserver(align)
    observer.observe(strip)

    return () => observer.disconnect()
  }, [location.pathname])

  return (
    <header
      className="shrink-0"
      style={{
        background: palette.surface,
        borderBottom: `1px solid ${palette.line}`
      }}
    >
      {/* Full-bleed row: logo against the left edge, status chip and theme
          switch against the right. Both side groups are flex-1 so they take an
          equal share of the leftover space, which puts the menu on the true
          centre of the header rather than the centre of whatever is left over
          — the two ends are ~100px apart in width. The menu is the only part
          that shrinks and scrolls. */}
      <div className="flex w-full items-center gap-4 px-6 py-3 lg:px-8">
        <div className="flex flex-1 shrink-0 items-center gap-2.5">
          <span
            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-sm text-white"
            style={{ background: palette.primary }}
          >
            <EyeOutlined />
          </span>
          {/* The full name costs ~130px that the menu needs on a narrower
              screen, so below 2xl the mark carries the identity on its own. */}
          <span
            className="hidden whitespace-nowrap text-sm font-semibold tracking-tight 2xl:block"
            style={{ color: palette.ink }}
          >
            {__('SEO Change Monitor')}
          </span>
        </div>

        <nav ref={navRef} className="scm-tabs-scroll min-w-0 shrink overflow-x-auto">
          <ul className="scm-tabs inline-flex list-none gap-1">
            {NAV.map(item => {
              const isCurrent = location.pathname === item.key

              return (
                <li key={item.key}>
                  <Link
                    aria-current={isCurrent ? 'page' : undefined}
                    className={classNames(
                      'scm-tab link-reset flex items-center gap-2 whitespace-nowrap px-3.5 py-2 text-[15px] no-underline transition-colors',
                      isCurrent && 'is-current'
                    )}
                    to={item.key}
                  >
                    {item.icon}
                    {item.label}
                  </Link>
                </li>
              )
            })}
          </ul>
        </nav>

        <div className="flex flex-1 shrink-0 items-center justify-end gap-3">
          <Tooltip placement="bottom" title={checkedTitle}>
            <span
              className="hidden items-center gap-2 whitespace-nowrap rounded-full py-1.5 pl-3 pr-3.5 text-[13px] font-medium sm:inline-flex"
              style={{ background: palette.lineSoft, color: palette.ink }}
            >
              <span className="flex items-center" style={{ color: health.tone }}>
                {health.icon ?? <span className="block h-1.5 w-1.5 rounded-full bg-current" />}
              </span>
              {health.label}
              <span className="hidden 2xl:inline" style={{ color: palette.inkFaint }}>
                ·
              </span>
              <span className="hidden 2xl:inline" style={{ color: palette.inkMuted }}>
                {health.detail}
              </span>
            </span>
          </Tooltip>

          <ThemeSwitch />
        </div>
      </div>
    </header>
  )
}
