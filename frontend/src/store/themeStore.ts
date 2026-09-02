import { create } from 'zustand'

export type ThemeMode = 'dark' | 'light' | 'system'

const STORAGE_KEY = 'seo-change-monitor:theme'
const ROOT_ID = 'seo-change-monitor-root'

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

const prefersDark = () =>
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(prefers-color-scheme: dark)').matches

export const resolveMode = (mode: ThemeMode) => (mode === 'system' ? (prefersDark() ? 'dark' : 'light') : mode)

/**
 * Applies the resolved theme to the app root. Exported so main.tsx can call it
 * before the first render and avoid a flash of the wrong theme.
 */
export const applyTheme = (resolved: 'dark' | 'light') => {
  const root = document.getElementById(ROOT_ID)
  if (!root) return

  root.classList.toggle('dark', resolved === 'dark')
  root.style.colorScheme = resolved
}

interface ThemeState {
  mode: ThemeMode
  resolved: 'dark' | 'light'
  setMode: (mode: ThemeMode) => void
  /** Re-evaluates 'system' when the OS preference changes. */
  syncSystem: () => void
}

const initialMode = readStoredMode()

export const useThemeStore = create<ThemeState>((set, get) => ({
  mode: initialMode,
  resolved: resolveMode(initialMode),
  setMode: mode => {
    const resolved = resolveMode(mode)

    try {
      window.localStorage.setItem(STORAGE_KEY, mode)
    } catch {
      // A viewer with storage blocked still gets the theme for this session.
    }

    applyTheme(resolved)
    set({ mode, resolved })
  },
  syncSystem: () => {
    if (get().mode !== 'system') return

    const resolved = resolveMode('system')
    applyTheme(resolved)
    set({ resolved })
  }
}))
