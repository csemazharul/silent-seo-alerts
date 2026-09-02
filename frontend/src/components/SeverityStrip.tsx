import classNames from '@common/helpers/classNames'
import { __ } from '@common/helpers/i18nWrap'
import { palette } from '@config/theme'
import type { Severity } from '@/api/types'

const TONE: Record<Severity, { caption: string; label: string; tone: string }> = {
  critical: {
    caption: __('Affects whether pages can be found'),
    label: __('Critical'),
    tone: palette.critical
  },
  warning: {
    caption: __('Worth a look when you have a moment'),
    label: __('Warning'),
    tone: palette.warning
  },
  info: {
    caption: __('Recorded for the history, no action needed'),
    label: __('Info'),
    tone: palette.info
  }
}

const ORDER: Severity[] = ['critical', 'warning', 'info']

interface Props {
  counts: Record<Severity, number>
  onSelect: (severity: Severity) => void
}

/**
 * One card split into three cells rather than three floating cards: the counts
 * are a single reading, and hairline dividers carry that without the padding
 * and drop shadows that three separate surfaces would cost.
 */
export default function SeverityStrip({ counts, onSelect }: Props) {
  return (
    <div
      className="grid grid-cols-1 overflow-hidden rounded-lg border border-solid sm:grid-cols-3"
      style={{ background: palette.surface, borderColor: palette.line }}
    >
      {ORDER.map((severity, index) => {
        const { caption, label, tone } = TONE[severity]
        const count = counts[severity] ?? 0

        return (
          <button
            key={severity}
            className={classNames(
              'scm-hover flex cursor-pointer items-center gap-4 border-0 border-solid bg-transparent px-5 py-4 text-left transition-colors',
              // Dividers sit on the leading edge so the card's own border is
              // never doubled, and flip from horizontal to vertical with the
              // grid. An inline style cannot carry a breakpoint, so the widths
              // are classes and only the colour comes from the palette.
              index > 0 && 'border-t sm:border-l sm:border-t-0'
            )}
            type="button"
            onClick={() => onSelect(severity)}
            style={{ borderColor: palette.lineSoft }}
          >
            <span
              className="text-3xl font-semibold tabular-nums leading-none"
              style={{
                color: count > 0 ? tone : palette.inkFaint,
                minWidth: '1.2ch'
              }}
            >
              {count}
            </span>
            <span className="min-w-0">
              <span
                className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide"
                style={{ color: count > 0 ? tone : palette.inkMuted }}
              >
                <span className="block h-1.5 w-1.5 rounded-full bg-current" />
                {label}
              </span>
              <span className="mt-0.5 block text-xs" style={{ color: palette.inkMuted }}>
                {caption}
              </span>
            </span>
          </button>
        )
      })}
    </div>
  )
}
