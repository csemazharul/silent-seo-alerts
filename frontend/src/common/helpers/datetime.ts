/**
 * Timestamp formatting for values coming off the REST API.
 *
 * Every date the backend emits is `gmdate('Y-m-d H:i:s')` — UTC, with no zone
 * marker on the string. Handing that straight to `new Date()` makes the browser
 * read it as local time, which silently shifts every timestamp by the site's
 * offset, so parsing goes through `parseUtc` here rather than inline at the
 * call sites.
 */

const locale = (): string | undefined => document.documentElement.lang || undefined

/** Under this many seconds a gap reads as "just now" rather than "in 12 seconds". */
const JUST_NOW = 45

const DIVISIONS: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
  { amount: 60, unit: 'second' },
  { amount: 60, unit: 'minute' },
  { amount: 24, unit: 'hour' },
  { amount: 7, unit: 'day' },
  { amount: 4.34524, unit: 'week' },
  { amount: 12, unit: 'month' },
  { amount: Number.POSITIVE_INFINITY, unit: 'year' }
]

export function parseUtc(value: null | string | undefined): Date | null {
  if (!value) return null

  // An ISO string that already carries a zone is trusted as-is; the bare
  // MySQL form gets pinned to UTC, which is what gmdate() wrote.
  const hasZone = /[zZ]$|[+-]\d{2}:?\d{2}$/.test(value)
  const date = new Date(hasZone ? value : `${value.replace(' ', 'T')}Z`)

  return Number.isNaN(date.getTime()) ? null : date
}

/** "2 hours ago", "in 16 hours", "just now" — or null when unparseable. */
export function relativeTime(value: null | string | undefined, now = Date.now()): null | string {
  const date = parseUtc(value)
  if (!date) return null

  let delta = (date.getTime() - now) / 1000
  if (Math.abs(delta) < JUST_NOW) return 'just now'

  const format = new Intl.RelativeTimeFormat(locale(), { numeric: 'auto' })

  for (const division of DIVISIONS) {
    if (Math.abs(delta) < division.amount) {
      return format.format(Math.round(delta), division.unit)
    }

    delta /= division.amount
  }

  return null
}

/** Full local date and time, for the tooltip behind a relative label. */
export function absoluteTime(value: null | string | undefined): null | string {
  const date = parseUtc(value)
  if (!date) return null

  return new Intl.DateTimeFormat(locale(), {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}
