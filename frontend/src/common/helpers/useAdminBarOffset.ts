import { useEffect, useState } from 'react'

/** antd's own gap between the viewport edge and a toast. */
const GAP = 8

/**
 * How far down toasts must start to clear wp-admin's toolbar.
 *
 * antd writes the toast container's `top` as an inline style, so CSS cannot
 * move it. Measuring the real toolbar also beats hardcoding 32/46px: admin
 * colour schemes and future WordPress versions can change its height.
 */
function measure() {
  const bar = document.getElementById('wpadminbar')
  if (!bar) return GAP

  // Below 600px wp-admin lets the toolbar scroll away, so nothing overlaps.
  if (window.getComputedStyle(bar).position !== 'fixed') return GAP

  return Math.round(bar.getBoundingClientRect().height) + GAP
}

export default function useAdminBarOffset() {
  const [offset, setOffset] = useState(measure)

  useEffect(() => {
    const onResize = () => setOffset(measure())

    window.addEventListener('resize', onResize)

    return () => window.removeEventListener('resize', onResize)
  }, [])

  return offset
}
