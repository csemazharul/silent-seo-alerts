import { palette } from '@config/theme'
import type { ReactNode } from 'react'

interface Props {
  title: string
  actions?: ReactNode
}

/** Consistent title block across every screen. */
export default function PageHeader({ title, actions }: Props) {
  return (
    <div className="mb-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
      <h1
        className="m-0 min-w-0 text-xl font-semibold leading-tight tracking-tight"
        style={{ color: palette.ink }}
      >
        {title}
      </h1>
      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  )
}
