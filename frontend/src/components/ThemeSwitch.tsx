import { BulbOutlined, DesktopOutlined, MoonFilled } from '@ant-design/icons'
import { Segmented, Tooltip } from 'antd'
import { __ } from '@common/helpers/i18nWrap'
import { useThemeStore } from '@/store/themeStore'
import type { ThemeMode } from '@/store/themeStore'

const OPTIONS: { icon: JSX.Element; title: string; value: ThemeMode }[] = [
  { icon: <BulbOutlined />, title: __('Light'), value: 'light' },
  { icon: <MoonFilled />, title: __('Dark'), value: 'dark' },
  { icon: <DesktopOutlined />, title: __('Match my system'), value: 'system' }
]

export default function ThemeSwitch() {
  const mode = useThemeStore(state => state.mode)
  const setMode = useThemeStore(state => state.setMode)

  return (
    <Segmented<ThemeMode>
      block
      size="small"
      value={mode}
      onChange={setMode}
      options={OPTIONS.map(option => ({
        label: (
          <Tooltip title={option.title}>
            <span className="flex items-center justify-center py-0.5">{option.icon}</span>
          </Tooltip>
        ),
        value: option.value
      }))}
    />
  )
}
