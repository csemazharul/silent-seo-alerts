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
  raised: 'var(--scm-raised)',
  success: 'var(--scm-success)',
  successSoft: 'var(--scm-success-soft)',
  surface: 'var(--scm-surface)',
  warning: 'var(--scm-warning)',
  warningSoft: 'var(--scm-warning-soft)'
} as const

const LIGHT = {
  canvas: '#f5f6f8',
  critical: '#c8372a',
  info: '#175cd3',
  ink: '#111827',
  inkMuted: '#5f6875',
  line: '#e3e6ea',
  lineSoft: '#eef0f3',
  primary: '#1d4ed8',
  primarySoft: '#eef2ff',
  success: '#067647',
  surface: '#ffffff',
  warning: '#b54708'
}

const DARK = {
  canvas: '#131519',
  critical: '#f27c72',
  info: '#6cb2ff',
  ink: '#e7eaef',
  inkMuted: '#98a1ad',
  line: '#2a2f38',
  lineSoft: '#222630',
  primary: '#8aa8ff',
  primarySoft: 'rgba(138, 168, 255, 0.16)',
  success: '#4ec98e',
  surface: '#1a1d23',
  warning: '#e0a458'
}

export function buildTheme(isDark: boolean, isCompact: boolean): ThemeConfig {
  const c = isDark ? DARK : LIGHT

  // antd composes algorithms, so compact stacks on top of light or dark rather
  // than replacing either.
  const algorithm = [isDark ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm]
  if (isCompact) algorithm.push(antdTheme.compactAlgorithm)

  return {
    algorithm,
    token: {
      borderRadius: 8,
      borderRadiusLG: 10,
      borderRadiusSM: 6,
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
      // The dark primary is a light blue, so antd's default white-on-solid text
      // has almost no contrast against it; solid fills take dark ink instead.
      colorTextLightSolid: isDark ? DARK.canvas : '#ffffff',
      colorTextDescription: c.inkMuted,
      colorTextSecondary: c.inkMuted,
      colorWarning: c.warning,
      controlHeight: 34,
      controlHeightLG: 38,
      controlHeightSM: 26,
      fontFamily: "Outfit, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
      fontSize: 14,
      fontWeightStrong: 600,
      lineHeight: 1.5,
      wireframe: false
    },
    components: {
      // antd ships a drop shadow on every button, which at this size reads as a
      // toy control rather than a tool. Flat, with the border doing the work.
      Button: {
        dangerShadow: 'none',
        defaultShadow: 'none',
        fontWeight: 500,
        paddingInline: 14,
        primaryShadow: 'none'
      },
      Card: {
        headerBg: 'transparent',
        headerFontSize: 13,
        headerHeight: 44,
        paddingLG: 16
      },
      Segmented: { itemSelectedBg: c.surface, trackBg: c.lineSoft },
      Table: {
        cellPaddingBlock: 14,
        cellPaddingInline: 16,
        headerBg: 'transparent',
        headerColor: c.inkMuted,
        headerSplitColor: 'transparent',
        rowHoverBg: c.lineSoft
      },
      Tag: { borderRadiusSM: 5 }
    }
  }
}
