import { create } from 'zustand'

export type ThemeMode = 'dark' | 'light' | 'system'

const STORAGE_KEY = 'silent-seo-alerts:theme'
const DENSITY_KEY = 'silent-seo-alerts:compact'
const ROOT_ID = 'silent-seo-alerts-root'

const isMode = (value: unknown): value is ThemeMode =>
  value === 'light' || value === 'dark' || value === 'system'

/** Reading storage can throw in private windows; never let that break the app. */
export const readStoredMode = (): ThemeMode => {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)

    return isMode(stored) ? stored : 'system'
  } catch {
    return 'system'
  }
}

export const readStoredCompact = (): boolean => {
  try {
    return window.localStorage.getItem(DENSITY_KEY) === '1'
  } catch {
    return false
  }
}

const prefersDark = () =>
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(prefers-color-scheme: dark)').matches

export const resolveMode = (mode: ThemeMode) =>
  mode === 'system' ? (prefersDark() ? 'dark' : 'light') : mode

/**
 * Applies the resolved theme to the app root. Exported so main.tsx can call it
 * before the first render and avoid a flash of the wrong theme.
 */
export const applyTheme = (resolved: 'dark' | 'light', compact: boolean) => {
  const root = document.getElementById(ROOT_ID)
  if (!root) return

  root.classList.toggle('dark', resolved === 'dark')
  root.classList.toggle('compact', compact)
  root.style.colorScheme = resolved

  // Mirrored onto <html> so the palette variables, which are also declared at
  // :root, switch for antd's portalled surfaces (drawers, modals, dropdowns,
  // tooltips) — those render outside the app root and cannot see its classes.
  document.documentElement.classList.toggle('scm-dark', resolved === 'dark')
  document.documentElement.classList.toggle('scm-compact', compact)
}

interface ThemeState {
  mode: ThemeMode
  resolved: 'dark' | 'light'
  compact: boolean
  setMode: (mode: ThemeMode) => void
  setCompact: (compact: boolean) => void
  /** Re-evaluates 'system' when the OS preference changes. */
  syncSystem: () => void
}

const initialMode = readStoredMode()
const initialCompact = readStoredCompact()

const store = (key: string, value: string) => {
  try {
    window.localStorage.setItem(key, value)
  } catch {
    // A viewer with storage blocked still gets the choice for this session.
  }
}

export const useThemeStore = create<ThemeState>((set, get) => ({
  mode: initialMode,
  resolved: resolveMode(initialMode),
  compact: initialCompact,
  setMode: mode => {
    const resolved = resolveMode(mode)

    store(STORAGE_KEY, mode)
    applyTheme(resolved, get().compact)
    set({ mode, resolved })
  },
  setCompact: compact => {
    store(DENSITY_KEY, compact ? '1' : '0')
    applyTheme(get().resolved, compact)
    set({ compact })
  },
  syncSystem: () => {
    if (get().mode !== 'system') return

    const resolved = resolveMode('system')
    applyTheme(resolved, get().compact)
    set({ resolved })
  }
}))
