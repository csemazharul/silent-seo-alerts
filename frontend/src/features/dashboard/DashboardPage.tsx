import {
  CheckCircleFilled,
  ReloadOutlined,
  RightOutlined,
  SafetyCertificateOutlined
} from '@ant-design/icons'
import { Alert, App, Button, Card, Popconfirm } from 'antd'
import { useNavigate } from 'react-router'
import classNames from '@common/helpers/classNames'
import { __, sprintf } from '@common/helpers/i18nWrap'
import {
  useArmBaseline,
  useCheckNow,
  useDashboardSummary,
  useDisarmBaseline,
  useFindings,
  useRunStatus
} from '@/api/queries'
import { changeLabel, eventLabel } from '@components/changeLabels'
import PageHeader from '@components/PageHeader'
import SeverityStrip from '@components/SeverityStrip'
import When from '@components/When'
import { palette } from '@config/theme'
import { useFlightLogStore } from '@/store/flightLogStore'
import type { Severity } from '@/api/types'
import type { ReactNode } from 'react'

const SEVERITY_TONE: Record<Severity, string> = {
  critical: palette.critical,
  info: palette.info,
  warning: palette.warning
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const { message } = App.useApp()
  const showOnly = useFlightLogStore(state => state.showOnly)

  const { data: summary } = useDashboardSummary()
  const { data: run } = useRunStatus()
  // Only the short "needs attention" list; the strip uses server-side counts so
  // it stays correct past one page of findings.
  const { data: recent } = useFindings({ status: ['open'], per_page: 6 })

  const checkNow = useCheckNow()
  const armBaseline = useArmBaseline()
  const disarmBaseline = useDisarmBaseline()

  const counts = summary?.open_counts ?? { critical: 0, warning: 0, info: 0 }
  const items = recent?.items ?? []
  const events = summary?.recent_events ?? []
  const isRunning = run?.status === 'running' || checkNow.isPending
  const isImpaired = Boolean(summary?.impaired) || run?.status === 'impaired'

  const openSeverity = (severity: Severity) => {
    showOnly([severity])
    navigate('/log')
  }

  const runCheck = () => {
    checkNow.mutate(undefined, {
      onSuccess: () => message.success(__('Check complete.')),
      onError: error => message.error(error.message)
    })
  }

  const arm = () => {
    armBaseline.mutate(undefined, {
      onSuccess: data =>
        message.success(
          sprintf(
            __('Baseline saved for %d pages. Run your updates, then check again.'),
            data.targets
          )
        ),
      onError: error => message.error(error.message)
    })
  }

  return (
    <>
      <PageHeader
        actions={
          <>
            {summary?.baseline ? (
              <Popconfirm
                cancelText={__('Keep it')}
                okText={__('Discard')}
                title={__('Discard the saved baseline?')}
                onConfirm={() => disarmBaseline.mutate()}
              >
                <Button loading={disarmBaseline.isPending}>{__('Discard baseline')}</Button>
              </Popconfirm>
            ) : (
              <Button
                icon={<SafetyCertificateOutlined />}
                loading={armBaseline.isPending}
                onClick={arm}
              >
                {__('Arm baseline')}
              </Button>
            )}
            <Button icon={<ReloadOutlined />} loading={isRunning} type="primary" onClick={runCheck}>
              {__('Check now')}
            </Button>
          </>
        }
        description={__('What changed on your pages, and what it means.')}
        title={__('Dashboard')}
      />

      {/* Spacing lives on this column: margin utilities on antd roots lose to
          its cssinjs reset at high hash priority. */}
      <div className="flex flex-col gap-4">
        {isImpaired && (
          <Alert
            showIcon
            description={
              summary?.impaired?.reason ??
              __(
                'This plugin could not fetch your pages on the last run, so no all-clear can be given.'
              )
            }
            message={__('Monitoring impaired')}
            type="warning"
          />
        )}

        {summary?.baseline && (
          <Alert
            showIcon
            description={__(
              'The next check compares against this snapshot instead of the last run, so anything your updates break shows up clearly. It expires on its own after 24 hours.'
            )}
            message={__('Baseline saved. Go ahead and run your updates')}
            type="info"
          />
        )}

        <SeverityStrip counts={counts} onSelect={openSeverity} />

        <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
          <Card
            className="lg:col-span-3"
            extra={
              items.length > 0 ? (
                <Button size="small" type="link" onClick={() => navigate('/log')}>
                  {__('View all')}
                </Button>
              ) : null
            }
            title={__('Needs your attention')}
          >
            {items.length === 0 ? (
              <div className="flex flex-col items-center gap-1 px-4 py-10 text-center">
                <CheckCircleFilled style={{ color: palette.success, fontSize: 22 }} />
                <p className="m-0 mt-2 text-sm font-medium" style={{ color: palette.ink }}>
                  {__('Nothing unexpected has changed.')}
                </p>
                <p className="m-0 max-w-xs text-xs" style={{ color: palette.inkMuted }}>
                  {__('We will tell you the moment something does.')}
                </p>
              </div>
            ) : (
              // Rows are full-bleed against the card padding so the hover band
              // and the dividers reach its edges the way a table's would.
              <ul className="-mx-4 -my-2 flex list-none flex-col p-0">
                {items.map((finding, index) => (
                  <li key={finding.id}>
                    <button
                      className={classNames(
                        'scm-hover group flex w-full cursor-pointer items-center gap-3 border-0 border-solid bg-transparent px-4 py-2.5 text-left transition-colors',
                        index > 0 && 'border-t'
                      )}
                      style={{ borderColor: palette.lineSoft }}
                      type="button"
                      onClick={() => navigate('/log')}
                    >
                      <span
                        className="h-1.5 w-1.5 shrink-0 rounded-full"
                        style={{ background: SEVERITY_TONE[finding.severity] }}
                      />
                      <span className="min-w-0 flex-1">
                        <span
                          className="block truncate text-sm font-medium"
                          style={{ color: palette.ink }}
                        >
                          {changeLabel(finding.change_type)}
                        </span>
                        <span
                          className="block truncate text-xs"
                          style={{ color: palette.inkMuted }}
                        >
                          {finding.target_label ?? finding.target_url ?? __('Site-wide')}
                        </span>
                      </span>
                      <When className="hidden shrink-0 sm:block" value={finding.created_at} />
                      <RightOutlined
                        className="shrink-0 text-xs opacity-0 transition-opacity group-hover:opacity-100"
                        style={{ color: palette.inkFaint }}
                      />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <div className="flex flex-col gap-4 lg:col-span-2">
            <Card title={__('Monitoring status')}>
              <dl className="m-0 flex flex-col gap-2.5 text-sm">
                <Row label={__('Pages monitored')} value={String(summary?.active_targets ?? 0)} />
                <Row
                  label={__('Last check')}
                  value={<When fallback={__('never')} value={summary?.last_run?.finished_at} />}
                />
                <Row
                  label={__('Next check')}
                  value={
                    summary?.frequency === 'off' ? (
                      __('scheduling is off')
                    ) : (
                      <When fallback={__('not scheduled')} value={summary?.next_run_at} />
                    )
                  }
                />
                <Row
                  label={__('Last run')}
                  tone={isImpaired ? palette.warning : undefined}
                  value={summary?.last_run?.status ?? __('never run')}
                />
              </dl>
            </Card>

            <Card title={__('Recent site activity')}>
              {events.length === 0 ? (
                <p className="m-0 py-2 text-xs" style={{ color: palette.inkMuted }}>
                  {__('Nothing has changed on this site lately.')}
                </p>
              ) : (
                <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
                  {events.slice(0, 5).map(event => (
                    <li key={event.id} className="flex items-baseline gap-2.5 text-sm">
                      <span
                        className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                        style={{ background: palette.inkFaint }}
                      />
                      <span className="min-w-0 flex-1">
                        <span className="font-medium" style={{ color: palette.ink }}>
                          {eventLabel(event.type)}
                        </span>{' '}
                        <span className="break-words text-xs" style={{ color: palette.inkMuted }}>
                          {event.subject}
                        </span>
                      </span>
                      <When className="shrink-0" value={event.at} />
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          </div>
        </div>
      </div>
    </>
  )
}

function Row({ label, value, tone }: { label: string; tone?: string; value: ReactNode }) {
  return (
    <div className="flex items-baseline justify-between gap-4">
      <dt className="m-0 shrink-0" style={{ color: palette.inkMuted }}>
        {label}
      </dt>
      <dd className="m-0 truncate font-medium" style={{ color: tone ?? palette.ink }}>
        {value}
      </dd>
    </div>
  )
}
