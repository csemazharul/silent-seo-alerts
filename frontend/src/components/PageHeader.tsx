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
    <div className="mb-5 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
      <div className="min-w-0">
        <h1
          className="m-0 text-xl font-semibold leading-tight tracking-tight"
          style={{ color: palette.ink }}
        >
          {title}
        </h1>
        <p className="mb-0 mt-1 text-sm" style={{ color: palette.inkMuted }}>
          {description}
        </p>
      </div>
      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  )
}
