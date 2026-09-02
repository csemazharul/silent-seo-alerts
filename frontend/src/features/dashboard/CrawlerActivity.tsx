import { Tooltip } from 'antd'
import { __ } from '@common/helpers/i18nWrap'
import { relativeTime } from '@common/helpers/datetime'
import { useSiteStatus } from '@/api/queries'
import { palette } from '@config/theme'

const TOP = 6

/**
 * Horizontal bar list of AI crawler visits. One series, so one hue — the bar
 * length carries the magnitude and the value sits at the tip in ink.
 */
export default function CrawlerActivity() {
  const { data } = useSiteStatus()

  const bots = (data?.bots ?? [])
    .filter(bot => bot.hits > 0)
    .sort((a, b) => b.hits - a.hits)
    .slice(0, TOP)

  if (data && !data.bot_tracking) {
    return (
      <p className="m-0 py-2 text-xs" style={{ color: palette.inkMuted }}>
        {__('Bot tracking is switched off in Settings.')}
      </p>
    )
  }

  if (data && bots.length === 0) {
    return (
      <p className="m-0 py-2 text-xs" style={{ color: palette.inkMuted }}>
        {__('No crawler visits recorded yet.')}
      </p>
    )
  }

  const max = Math.max(...bots.map(bot => bot.hits), 1)

  return (
    <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
      {bots.map(bot => {
        const seen = relativeTime(bot.last_seen)

        return (
          <li key={bot.slug}>
            <Tooltip
              placement="left"
              title={seen ? `${bot.label} — ${__('last seen')} ${seen}` : bot.label}
            >
              <div className="flex items-center gap-3">
                <span className="w-28 shrink-0 truncate text-xs" style={{ color: palette.ink }}>
                  {bot.label}
                </span>
                <span className="min-w-0 flex-1">
                  <span
                    className="block h-2 min-w-[3px]"
                    style={{
                      // 4px rounded data-end; square at the baseline edge.
                      background: 'var(--scm-viz-info)',
                      borderRadius: '0 4px 4px 0',
                      width: `${(bot.hits / max) * 100}%`
                    }}
                  />
                </span>
                <span className="text-xs font-medium tabular-nums" style={{ color: palette.ink }}>
                  {bot.hits}
                </span>
              </div>
            </Tooltip>
          </li>
        )
      })}
    </ul>
  )
}
