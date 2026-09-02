import { Tag } from 'antd'
import { __ } from '@common/helpers/i18nWrap'
import type { Severity } from '@/api/types'

const MAP: Record<Severity, { color: string; label: string }> = {
  critical: { color: 'error', label: __('Critical') },
  warning: { color: 'warning', label: __('Warning') },
  info: { color: 'processing', label: __('Info') }
}

export default function SeverityTag({ severity }: { severity: Severity }) {
  const config = MAP[severity] ?? MAP.info

  return (
    <Tag bordered={false} color={config.color}>
      {config.label}
    </Tag>
  )
}
