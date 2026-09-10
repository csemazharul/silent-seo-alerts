import {
  ColumnHeightOutlined,
  DesktopOutlined,
  DownOutlined,
  MoonOutlined,
  SunOutlined
} from '@ant-design/icons'
import { Button, Dropdown, Tooltip, theme } from 'antd'
import { __ } from '@common/helpers/i18nWrap'
import { useThemeStore } from '@/store/themeStore'
import type { ThemeMode } from '@/store/themeStore'
import type { MenuProps } from 'antd'
import type { ReactNode } from 'react'

const MODE_ICON: Record<ThemeMode, ReactNode> = {
  dark: <MoonOutlined />,
  light: <SunOutlined />,
  system: <DesktopOutlined />
}

/**
 * Label plus the dot that marks an option as on, as in antd's own switcher.
 *
 * The colour comes from the antd token rather than the CSS-variable map: the
 * menu is portalled to <body>, outside #silent-seo-alerts-root, where every
 * --scm-* variable is undefined and the dot would render transparent.
 */
function Row({ label, on }: { label: string; on: boolean }) {
  const { token } = theme.useToken()

  return (
    <span className="flex min-w-[150px] items-center justify-between gap-6">
      {label}
      <span
        aria-hidden
        className="h-1.5 w-1.5 shrink-0 rounded-full"
        style={{ background: on ? token.colorPrimary : 'transparent' }}
      />
    </span>
  )
}

export default function ThemeSwitch() {
  const mode = useThemeStore(state => state.mode)
  const compact = useThemeStore(state => state.compact)
  const setMode = useThemeStore(state => state.setMode)
  const setCompact = useThemeStore(state => state.setCompact)

  const items: MenuProps['items'] = [
    {
      key: 'system',
      icon: <DesktopOutlined />,
      label: <Row label={__('Follow system')} on={mode === 'system'} />
    },
    {
      key: 'light',
      icon: <SunOutlined />,
      label: <Row label={__('Light theme')} on={mode === 'light'} />
    },
    {
      key: 'dark',
      icon: <MoonOutlined />,
      label: <Row label={__('Dark theme')} on={mode === 'dark'} />
    },
    { type: 'divider' },
    {
      // Density is independent of light and dark, so it toggles rather than
      // joining the three above as a fourth choice.
      key: 'compact',
      icon: <ColumnHeightOutlined />,
      label: <Row label={__('Compact theme')} on={compact} />
    }
  ]

  const onClick: MenuProps['onClick'] = ({ key }) => {
    if (key === 'compact') {
      setCompact(!compact)

      return
    }

    setMode(key as ThemeMode)
  }

  return (
    <Dropdown menu={{ items, onClick }} placement="bottomRight" trigger={['click']}>
      <Tooltip placement="bottom" title={__('Theme')}>
        <Button aria-label={__('Theme')} className="scm-theme-trigger" type="text">
          <span className="flex items-center gap-1.5">
            {MODE_ICON[mode]}
            <DownOutlined className="scm-theme-caret" />
          </span>
        </Button>
      </Tooltip>
    </Dropdown>
  )
}
