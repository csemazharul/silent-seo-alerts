import { theme as antdTheme } from 'antd'
import type { ThemeConfig } from 'antd'

/**
 * Components reference the palette through CSS variables, so a theme switch is
 * a single class change on the root, with no re-render and no prop drilling.
 * The literal values below feed antd, which needs real colours to derive its
 * own scales.
 */
export const palette = {
  canvas: 'var(--scm-canvas)',
  critical: 'var(--scm-critical)',
  criticalSoft: 'var(--scm-critical-soft)',
  info: 'var(--scm-info)',
  infoSoft: 'var(--scm-info-soft)',
  ink: 'var(--scm-ink)',
  inkFaint: 'var(--scm-ink-faint)',
  inkMuted: 'var(--scm-ink-muted)',
  line: 'var(--scm-line)',
  lineSoft: 'var(--scm-line-soft)',
  primary: 'var(--scm-primary)',
  primarySoft: 'var(--scm-primary-soft)',
  success: 'var(--scm-success)',
  successSoft: 'var(--scm-success-soft)',
  surface: 'var(--scm-surface)',
  warning: 'var(--scm-warning)',
  warningSoft: 'var(--scm-warning-soft)'
} as const

const LIGHT = {
  canvas: '#f4f5f7',
  critical: '#d92d20',
  info: '#175cd3',
  ink: '#111827',
  inkMuted: '#6b7280',
  line: '#e6e8eb',
  lineSoft: '#eff1f3',
  primary: '#3b5bdb',
  primarySoft: '#eef2ff',
  success: '#067647',
  surface: '#ffffff',
  warning: '#b54708'
}

const DARK = {
  canvas: '#14161b',
  critical: '#ff7b72',
  info: '#6cb2ff',
  ink: '#e7eaef',
  inkMuted: '#9aa2ae',
  line: '#2b303a',
  lineSoft: '#232831',
  primary: '#8098ff',
  primarySoft: 'rgba(128, 152, 255, 0.16)',
  success: '#4ec98e',
  surface: '#1c1f26',
  warning: '#e0a458'
}

export function buildTheme(isDark: boolean): ThemeConfig {
  const c = isDark ? DARK : LIGHT

  return {
    algorithm: isDark ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
    token: {
      borderRadius: 10,
      borderRadiusLG: 12,
      borderRadiusSM: 8,
      colorBgContainer: c.surface,
      colorBgElevated: c.surface,
      colorBgLayout: c.canvas,
      colorBorder: c.line,
      colorBorderSecondary: c.lineSoft,
      colorError: c.critical,
      colorInfo: c.info,
      colorPrimary: c.primary,
      colorSuccess: c.success,
      colorText: c.ink,
      colorTextDescription: c.inkMuted,
      colorTextSecondary: c.inkMuted,
      colorWarning: c.warning,
      controlHeight: 38,
      fontFamily: "Outfit, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
      fontSize: 14,
      lineHeight: 1.55,
      wireframe: false
    },
    components: {
      Button: { fontWeight: 500, paddingInline: 18 },
      Card: {
        headerBg: 'transparent',
        headerFontSize: 14,
        headerHeight: 52,
        paddingLG: 20
      },
      Layout: { bodyBg: c.canvas, siderBg: c.surface },
      Menu: {
        activeBarWidth: 0,
        itemActiveBg: c.primarySoft,
        itemBorderRadius: 10,
        itemHeight: 40,
        itemMarginInline: 10,
        itemSelectedBg: c.primarySoft,
        itemSelectedColor: c.primary
      },
      Table: {
        headerBg: 'transparent',
        headerColor: c.inkMuted,
        headerSplitColor: 'transparent',
        rowHoverBg: c.lineSoft
      },
      Tag: { borderRadiusSM: 6 }
    }
  }
}
