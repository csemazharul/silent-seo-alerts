import { useEffect, useRef, useState } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { useFindings } from '@/api/queries'
import { palette } from '@config/theme'
import { WINDOW_DAYS as DAYS, recentDays } from './recentWindow'
import type { Finding, Severity } from '@/api/types'

const HEIGHT = 200
const MARGIN = { top: 8, right: 8, bottom: 22, left: 30 }
const MAX_COLUMN = 24
const SEGMENT_GAP = 2

/*
 * Stack order is deliberate: blue between amber and red. As adjacent marks the
 * status red and amber fail colorblind separation (deutan dE 2.1), so they must
 * never touch; with info between them every adjacent pair validates.
 */
const STACK: Severity[] = ['warning', 'info', 'critical']

/** Display order for the legend, tooltip and aria text; STACK orders the marks. */
export const CHART_SEVERITIES: { key: Severity; label: string; tone: string }[] = [
  { key: 'critical', label: __('Critical'), tone: 'var(--scm-viz-critical)' },
  { key: 'warning', label: __('Warning'), tone: 'var(--scm-viz-warning)' },
  { key: 'info', label: __('Info'), tone: 'var(--scm-viz-info)' }
]

const BY_KEY = new Map(CHART_SEVERITIES.map(s => [s.key, s]))

const dayLabel = (iso: string) =>
  new Date(`${iso}T00:00:00Z`).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC'
  })

type DayBucket = { day: string } & Record<Severity, number>

function bucketByDay(items: Finding[], days: string[]): DayBucket[] {
  const buckets = new Map<string, DayBucket>(
    days.map(day => [day, { day, critical: 0, warning: 0, info: 0 }])
  )

  for (const finding of items) {
    const bucket = buckets.get((finding.created_at ?? '').slice(0, 10))
    if (bucket) bucket[finding.severity] += 1
  }

  return days.map(day => buckets.get(day)!)
}

/** Top segment gets the 4px rounded data-end; the baseline stays square. */
const roundedTopRect = (x: number, y: number, w: number, h: number, r: number) => {
  const radius = Math.min(r, h, w / 2)

  return `M${x},${y + h} V${y + radius} Q${x},${y} ${x + radius},${y} H${x + w - radius} Q${x + w},${y} ${x + w},${y + radius} V${y + h} Z`
}

export default function ChangesChart() {
  const wrapRef = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)
  const [hovered, setHovered] = useState<number | null>(null)
  const [days] = useState(recentDays)

  const { data } = useFindings({ date_from: days[0], per_page: 100 })

  useEffect(() => {
    const wrap = wrapRef.current
    if (!wrap) return

    const observer = new ResizeObserver(entries => setWidth(entries[0].contentRect.width))
    observer.observe(wrap)

    return () => observer.disconnect()
  }, [])

  const buckets = bucketByDay(data?.items ?? [], days)
  const totals = buckets.map(b => b.critical + b.warning + b.info)
  const grandTotal = totals.reduce((sum, n) => sum + n, 0)
  const maxTotal = Math.max(...totals, 4)
  const yMax = Math.ceil(maxTotal / 2) * 2
  const ticks = [0, yMax / 2, yMax]

  const innerW = Math.max(width - MARGIN.left - MARGIN.right, 0)
  const innerH = HEIGHT - MARGIN.top - MARGIN.bottom
  const band = innerW / DAYS
  const column = Math.min(MAX_COLUMN, band * 0.55)
  const yOf = (value: number) => MARGIN.top + innerH - (value / yMax) * innerH

  const hoveredBucket = hovered !== null ? buckets[hovered] : null

  return (
    <div ref={wrapRef} className="relative">
      {grandTotal === 0 && data && (
        <p
          className="absolute inset-0 z-10 m-0 flex items-center justify-center pb-4 text-xs"
          style={{ color: palette.inkMuted }}
        >
          {__('No changes recorded in the last 14 days.')}
        </p>
      )}

      {width > 0 && (
        <svg
          aria-label={__('Changes per day by severity, last 14 days')}
          height={HEIGHT}
          role="img"
          width={width}
          onMouseLeave={() => setHovered(null)}
        >
          {ticks.map(tick => (
            <g key={tick}>
              <line
                stroke="var(--scm-line-soft)"
                strokeWidth={1}
                x1={MARGIN.left}
                x2={width - MARGIN.right}
                y1={yOf(tick)}
                y2={yOf(tick)}
              />
              <text
                className="tabular-nums"
                fill="var(--scm-ink-muted)"
                fontSize={11}
                textAnchor="end"
                x={MARGIN.left - 8}
                y={yOf(tick) + 4}
              >
                {tick}
              </text>
            </g>
          ))}

          {buckets.map((bucket, i) => {
            const xBand = MARGIN.left + i * band
            const x = xBand + (band - column) / 2
            // Anchor labels to the newest day and step back evenly, so the
            // right edge never carries two labels side by side.
            const showLabel = (DAYS - 1 - i) % 3 === 0

            // Non-zero counts keep a 2px floor so a single change stays visible.
            const heights = STACK.map(s =>
              bucket[s] === 0 ? 0 : Math.max((bucket[s] / yMax) * innerH, 2)
            )
            let y = yOf(0)

            return (
              <g key={bucket.day}>
                {hovered === i && (
                  <rect
                    fill="var(--scm-line-soft)"
                    height={innerH}
                    opacity={0.6}
                    width={band}
                    x={xBand}
                    y={MARGIN.top}
                  />
                )}

                {STACK.map((severity, s) => {
                  const h = heights[s]
                  if (h === 0) return null

                  y -= h
                  const top = y
                  const isTopmost = heights.slice(s + 1).every(rest => rest === 0)
                  y -= SEGMENT_GAP

                  return isTopmost ? (
                    <path
                      key={severity}
                      d={roundedTopRect(x, top, column, h, 4)}
                      fill={BY_KEY.get(severity)!.tone}
                    />
                  ) : (
                    <rect
                      key={severity}
                      fill={BY_KEY.get(severity)!.tone}
                      height={h}
                      width={column}
                      x={x}
                      y={top}
                    />
                  )
                })}

                {showLabel && (
                  <text
                    fill="var(--scm-ink-muted)"
                    fontSize={11}
                    textAnchor="middle"
                    x={xBand + band / 2}
                    y={HEIGHT - 6}
                  >
                    {dayLabel(bucket.day)}
                  </text>
                )}

                {/* Hit target: the whole day band, not just the painted column. */}
                <rect
                  aria-label={`${dayLabel(bucket.day)}: ${CHART_SEVERITIES.map(
                    ({ key, label }) => `${bucket[key]} ${label}`
                  ).join(', ')}`}
                  fill="transparent"
                  height={innerH}
                  tabIndex={0}
                  width={band}
                  x={xBand}
                  y={MARGIN.top}
                  onBlur={() => setHovered(null)}
                  onFocus={() => setHovered(i)}
                  onMouseEnter={() => setHovered(i)}
                />
              </g>
            )
          })}
        </svg>
      )}

      {hoveredBucket && (
        <div
          className="pointer-events-none absolute z-20 rounded-md border border-solid px-3 py-2 shadow-sm"
          style={{
            background: palette.surface,
            borderColor: palette.line,
            left: Math.min(
              Math.max(MARGIN.left + (hovered! + 0.5) * band - 60, 0),
              Math.max(width - 130, 0)
            ),
            top: 0
          }}
        >
          <p className="m-0 mb-1 text-xs font-medium" style={{ color: palette.ink }}>
            {dayLabel(hoveredBucket.day)}
          </p>
          {CHART_SEVERITIES.map(({ key, label, tone }) => (
            <p key={key} className="m-0 flex items-center gap-2 text-xs">
              <span className="h-2.5 w-1 rounded-sm" style={{ background: tone }} />
              <span className="font-semibold tabular-nums" style={{ color: palette.ink }}>
                {hoveredBucket[key]}
              </span>
              <span style={{ color: palette.inkMuted }}>{label}</span>
            </p>
          ))}
        </div>
      )}
    </div>
  )
}
