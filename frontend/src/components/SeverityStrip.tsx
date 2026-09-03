import { ExclamationCircleOutlined, EyeOutlined, WarningOutlined } from '@ant-design/icons'
import { __ } from '@common/helpers/i18nWrap'
import { palette } from '@config/theme'
import type { Severity } from '@/api/types'
import type { ReactNode } from 'react'

const TONE: Record<
  Severity,
  { caption: string; icon: ReactNode; label: string; soft: string; tone: string }
> = {
  critical: {
    caption: __('Affects whether pages can be found'),
    icon: <ExclamationCircleOutlined />,
    label: __('Critical'),
    soft: palette.criticalSoft,
    tone: palette.critical
  },
  warning: {
    caption: __('Worth a look when you have a moment'),
    icon: <WarningOutlined />,
    label: __('Warning'),
    soft: palette.warningSoft,
    tone: palette.warning
  },
  info: {
    caption: __('Recorded for the history, no action needed'),
    icon: <EyeOutlined />,
    label: __('Info'),
    soft: palette.infoSoft,
    tone: palette.info
  }
}

const ORDER: Severity[] = ['critical', 'warning', 'info']

interface Props {
  counts: Record<Severity, number>
  onSelect: (severity: Severity) => void
}

/**
 * Three severity stat cards: icon chip and label on top, the count as the
 * hero of the card, the caption as the footer. Each card filters the flight
 * log on click.
 */
export default function SeverityStrip({ counts, onSelect }: Props) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {ORDER.map(severity => {
        const { caption, icon, label, soft, tone } = TONE[severity]
        const count = counts[severity] ?? 0

        return (
          <button
            key={severity}
            className="flex cursor-pointer flex-col items-start gap-3 rounded-lg border border-solid p-5 text-left transition hover:-translate-y-px hover:shadow-sm"
            type="button"
            onClick={() => onSelect(severity)}
            style={{ background: palette.surface, borderColor: palette.line }}
          >
            <span className="flex items-center gap-2.5">
              <span
                className="flex h-7 w-7 items-center justify-center rounded-md text-sm"
                style={{ background: soft, color: tone }}
              >
                {icon}
              </span>
              <span className="text-[13px] font-semibold" style={{ color: palette.ink }}>
                {label}
              </span>
            </span>

            <span
              className="text-[32px] font-semibold leading-none"
              style={{ color: count > 0 ? tone : palette.inkFaint }}
            >
              {count}
            </span>

            <span className="text-xs" style={{ color: palette.inkMuted }}>
              {caption}
            </span>
          </button>
        )
      })}
    </div>
  )
}
