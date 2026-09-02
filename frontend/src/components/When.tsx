import { Tooltip } from 'antd'
import { absoluteTime, relativeTime } from '@common/helpers/datetime'
import classNames from '@common/helpers/classNames'
import { palette } from '@config/theme'

interface Props {
  value: null | string | undefined
  /** Shown when the value is missing or unparseable. */
  fallback?: string
  className?: string
}

/**
 * Every timestamp off the API is UTC, so it gets shown as a relative label in
 * the reader's own zone with the exact local time behind a tooltip.
 */
export default function When({ value, fallback, className }: Props) {
  const relative = relativeTime(value)

  if (!relative) {
    return (
      <span className={className} style={{ color: palette.inkMuted }}>
        {fallback ?? '—'}
      </span>
    )
  }

  return (
    <Tooltip title={absoluteTime(value)}>
      <span className={classNames('text-xs', className)} style={{ color: palette.inkMuted }}>
        {relative}
      </span>
    </Tooltip>
  )
}
