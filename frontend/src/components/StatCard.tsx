import { palette } from '@config/theme'
import type { Severity } from '@/api/types'

const TONE: Record<Severity, { bg: string; dot: string; text: string; label: string }> = {
  critical: { bg: palette.criticalSoft, dot: palette.critical, text: palette.critical, label: 'Critical' },
  warning: { bg: palette.warningSoft, dot: palette.warning, text: palette.warning, label: 'Warning' },
  info: { bg: palette.infoSoft, dot: palette.info, text: palette.info, label: 'Info' }
}

interface Props {
  severity: Severity
  count: number
  caption: string
  onClick: () => void
}

export default function StatCard({ severity, count, caption, onClick }: Props) {
  const tone = TONE[severity]
  const isActive = count > 0

  return (
    <button
      className="group flex w-full cursor-pointer flex-col gap-3 rounded-xl border border-solid p-5 text-left transition hover:-translate-y-px hover:shadow-md"
      style={{ background: palette.surface, borderColor: palette.line }}
      type="button"
      onClick={onClick}
    >
      <span
        className="inline-flex items-center gap-2 self-start rounded-md px-2 py-1 text-xs font-medium"
        style={{ background: tone.bg, color: tone.text }}
      >
        <span className="h-1.5 w-1.5 rounded-full" style={{ background: tone.dot }} />
        {tone.label}
      </span>

      <span
        className="text-4xl font-semibold leading-none"
        style={{ color: isActive ? tone.text : palette.inkFaint }}
      >
        {count}
      </span>

      <span className="text-xs" style={{ color: palette.inkMuted }}>
        {caption}
      </span>
    </button>
  )
}
