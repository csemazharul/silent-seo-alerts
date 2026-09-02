import { useEffect } from 'react'
import { useLocation } from 'react-router'
import config from '@config/config'

/**
 * Keeps the WordPress submenu highlight in step with the in-app route.
 *
 * Every submenu entry points at the same admin page with a different hash, so
 * WordPress always marks the first one current; it cannot see the hash.
 */
export default function useWpMenuHighlight() {
  const location = useLocation()

  useEffect(() => {
    const items = document.querySelectorAll<HTMLAnchorElement>(
      `#adminmenu a[href*="page=${config.PLUGIN_SLUG}"]`
    )

    if (items.length === 0) return

    const route = location.pathname === '/' ? '' : `#${location.pathname}`

    items.forEach(anchor => {
      const parent = anchor.parentElement
      if (!parent || parent.tagName !== 'LI') return

      const hashIndex = anchor.getAttribute('href')?.indexOf('#') ?? -1
      const anchorRoute = hashIndex === -1 ? '' : (anchor.getAttribute('href') ?? '').slice(hashIndex)
      const isCurrent = anchorRoute === route

      parent.classList.toggle('current', isCurrent)
      anchor.classList.toggle('current', isCurrent)

      if (isCurrent) {
        anchor.setAttribute('aria-current', 'page')
      } else {
        anchor.removeAttribute('aria-current')
      }
    })
  }, [location.pathname])
}
