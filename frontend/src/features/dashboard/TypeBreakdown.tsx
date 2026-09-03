import { useState } from 'react'
import { __, sprintf } from '@common/helpers/i18nWrap'
import { useFindings } from '@/api/queries'
import { changeLabel } from '@components/changeLabels'
import { palette } from '@config/theme'
import { recentDays } from './recentWindow'

const SIZE = 168
const CENTRE = SIZE / 2
const OUTER = 80
const INNER = 58

/*
 * The reference categorical order (blue, orange, aqua, yellow), validated
 * against both app surfaces. Everything past the fourth type folds into a
 * gray "Other" slot rather than a generated fifth hue, and that gray also
 * sits between yellow and blue where the ring wraps, so the one hue pair the
 * validator never checked (the wrap) never touches.
 */
const SLOTS = ['var(--scm-cat-1)', 'var(--scm-cat-2)', 'var(--scm-cat-3)', 'var(--scm-cat-4)']

interface Slice {
  label: string
  count: number
  tone: string
}

const polar = (r: number, angle: number) => ({
  x: CENTRE + r * Math.cos(angle),
  y: CENTRE + r * Math.sin(angle)
})

/** Annular sector from startAngle to endAngle (radians, clockwise from 12 o'clock). */
const slicePath = (start: number, end: number) => {
  const large = end - start > Math.PI ? 1 : 0
  const o1 = polar(OUTER, start)
  const o2 = polar(OUTER, end)
  const i1 = polar(INNER, end)
  const i2 = polar(INNER, start)

  return [
    `M${o1.x},${o1.y}`,
    `A${OUTER},${OUTER} 0 ${large} 1 ${o2.x},${o2.y}`,
    `L${i1.x},${i1.y}`,
    `A${INNER},${INNER} 0 ${large} 0 ${i2.x},${i2.y}`,
    'Z'
  ].join(' ')
}

export default function TypeBreakdown() {
  const [days] = useState(recentDays)
  const [hovered, setHovered] = useState<number | null>(null)
  const { data } = useFindings({ date_from: days[0], per_page: 100 })

  const countByType = new Map<string, number>()
  for (const finding of data?.items ?? []) {
    countByType.set(finding.change_type, (countByType.get(finding.change_type) ?? 0) + 1)
  }

  const ranked = [...countByType.entries()].sort((a, b) => b[1] - a[1])
  const top = ranked.slice(0, SLOTS.length)
  const rest = ranked.slice(SLOTS.length).reduce((sum, [, n]) => sum + n, 0)

  const slices: Slice[] = top.map(([type, count], i) => ({
    label: changeLabel(type),
    count,
    tone: SLOTS[i]
  }))
  if (rest > 0) slices.push({ label: __('Other'), count: rest, tone: palette.inkFaint })

  const total = slices.reduce((sum, s) => sum + s.count, 0)

  // Arc geometry ahead of the JSX, so the render below never mutates state.
  let acc = -Math.PI / 2
  const arcs = slices.map(slice => {
    const start = acc
    acc += (slice.count / total) * Math.PI * 2

    return { ...slice, path: slicePath(start, acc) }
  })

  return (
    <div className="flex flex-wrap items-center gap-5">
      <svg
        aria-label={__('Changes by type, last 14 days')}
        height={SIZE}
        role="img"
        viewBox={`0 0 ${SIZE} ${SIZE}`}
        width={SIZE}
        onMouseLeave={() => setHovered(null)}
      >
        {slices.length === 0 ? (
          // No data: the ring stays as a neutral track around the zero.
          <circle
            cx={CENTRE}
            cy={CENTRE}
            fill="none"
            r={(OUTER + INNER) / 2}
            stroke={palette.lineSoft}
            strokeWidth={OUTER - INNER}
          />
        ) : slices.length === 1 ? (
          <circle
            cx={CENTRE}
            cy={CENTRE}
            fill="none"
            r={(OUTER + INNER) / 2}
            stroke={slices[0].tone}
            strokeWidth={OUTER - INNER}
          />
        ) : (
          arcs.map((slice, i) => (
            <path
              key={slice.label}
              aria-label={sprintf(__('%s: %d of %d'), slice.label, slice.count, total)}
              d={slice.path}
              fill={slice.tone}
              opacity={hovered === null || hovered === i ? 1 : 0.45}
              // The 2px surface stroke is the gap between touching slices.
              stroke={palette.surface}
              strokeWidth={2}
              tabIndex={0}
              onBlur={() => setHovered(null)}
              onFocus={() => setHovered(i)}
              onMouseEnter={() => setHovered(i)}
            />
          ))
        )}

        <text
          fill="var(--scm-ink)"
          fontSize={26}
          fontWeight={600}
          textAnchor="middle"
          x={CENTRE}
          y={CENTRE + 2}
        >
          {total}
        </text>
        <text
          fill="var(--scm-ink-muted)"
          fontSize={11}
          textAnchor="middle"
          x={CENTRE}
          y={CENTRE + 18}
        >
          {total === 1 ? __('change') : __('changes')}
        </text>
      </svg>

      {total === 0 ? (
        <p
          className="m-0 min-w-0 flex-1 text-xs leading-relaxed"
          style={{ color: palette.inkMuted }}
        >
          {__('No changes recorded in the last 14 days.')}{' '}
          {__('When something on your pages changes, the breakdown by type appears here.')}
        </p>
      ) : (
        /* Values live here permanently, so nothing depends on hovering a slice. */
        <ul className="m-0 flex min-w-0 flex-1 list-none flex-col gap-1.5 p-0">
          {slices.map((slice, i) => (
            <li
              key={slice.label}
              className="flex items-center gap-2.5 rounded-md px-1.5 py-1"
              style={{ background: hovered === i ? palette.lineSoft : 'transparent' }}
              onMouseEnter={() => setHovered(i)}
              onMouseLeave={() => setHovered(null)}
            >
              <span
                className="h-2.5 w-2.5 shrink-0 rounded-sm"
                style={{ background: slice.tone }}
              />
              <span className="min-w-0 flex-1 truncate text-xs" style={{ color: palette.ink }}>
                {slice.label}
              </span>
              <span
                className="shrink-0 text-xs font-medium tabular-nums"
                style={{ color: palette.ink }}
              >
                {slice.count}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
