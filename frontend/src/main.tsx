import { createRoot } from 'react-dom/client'
import App from './App'
import { applyTheme, readStoredCompact, readStoredMode, resolveMode } from './store/themeStore'
import './resource/styles/variables.css'
import './resource/styles/global.css'

const elm = document.querySelector('#silent-seo-alerts-root')
if (elm) {
  // Before the first paint, so the saved theme never flashes the wrong way.
  applyTheme(resolveMode(readStoredMode()), readStoredCompact())
  createRoot(elm).render(<App />)
}
