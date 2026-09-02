import { palette } from '@config/theme'
import type { ReactNode } from 'react'

interface Props {
  title: string
  description: string
  actions?: ReactNode
}

/** Consistent title block across every screen. */
export default function PageHeader({ title, description, actions }: Props) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="m-0 text-2xl font-semibold leading-tight" style={{ color: palette.ink }}>
          {title}
        </h1>
        <p className="mt-1 mb-0 text-sm" style={{ color: palette.inkMuted }}>
          {description}
        </p>
      </div>
      {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
    </div>
  )
}
