import { CheckCircleOutlined, ReloadOutlined, SafetyCertificateOutlined } from '@ant-design/icons'
import { Alert, App, Button, Card, Popconfirm } from 'antd'
import { useNavigate } from 'react-router'
import { __ } from '@common/helpers/i18nWrap'
import {
  useArmBaseline,
  useCheckNow,
  useDashboardSummary,
  useDisarmBaseline,
  useFindings,
  useRunStatus
} from '@/api/queries'
import { changeLabel } from '@components/changeLabels'
import PageHeader from '@components/PageHeader'
import SeverityTag from '@components/SeverityTag'
import StatCard from '@components/StatCard'
import { palette } from '@config/theme'
import { useFlightLogStore } from '@/store/flightLogStore'
import type { Severity } from '@/api/types'

export default function DashboardPage() {
  const navigate = useNavigate()
  const { message } = App.useApp()
  const showOnly = useFlightLogStore(state => state.showOnly)

  const { data: summary } = useDashboardSummary()
  const { data: run } = useRunStatus()
  // Only the short "needs attention" list; the tiles use server-side counts so
  // they stay correct past one page of findings.
  const { data: recent } = useFindings({ status: ['open'], per_page: 5 })

  const checkNow = useCheckNow()
  const armBaseline = useArmBaseline()
  const disarmBaseline = useDisarmBaseline()

  const counts = summary?.open_counts ?? { critical: 0, warning: 0, info: 0 }
  const items = recent?.items ?? []
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
          __('Baseline saved for %d pages. Run your updates, then check again.').replace(
            '%d',
            String(data.targets)
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
      <div className="flex flex-col gap-5">
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

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard
            caption={__('Affects whether pages can be found')}
            count={counts.critical}
            severity="critical"
            onClick={() => openSeverity('critical')}
          />
          <StatCard
            caption={__('Worth a look when you have a moment')}
            count={counts.warning}
            severity="warning"
            onClick={() => openSeverity('warning')}
          />
          <StatCard
            caption={__('Recorded for the history, no action needed')}
            count={counts.info}
            severity="info"
            onClick={() => openSeverity('info')}
          />
        </div>

        <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
          <Card className="lg:col-span-2" title={__('Monitoring status')}>
            <dl className="m-0 flex flex-col gap-3 text-sm">
              <Row label={__('Pages monitored')} value={String(summary?.active_targets ?? 0)} />
              <Row label={__('Last check')} value={summary?.last_run?.finished_at ?? __('never')} />
              <Row
                label={__('Next check')}
                value={
                  summary?.frequency === 'off'
                    ? __('scheduling is off')
                    : (summary?.next_run_at ?? __('not scheduled'))
                }
              />
              <Row
                label={__('Last run')}
                tone={isImpaired ? palette.warning : undefined}
                value={summary?.last_run?.status ?? __('never run')}
              />
            </dl>
          </Card>

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
              <div className="flex flex-col items-center gap-1.5 py-8 text-center">
                <CheckCircleOutlined style={{ color: palette.success, fontSize: 24 }} />
                <p className="m-0 mt-1 text-sm font-medium" style={{ color: palette.ink }}>
                  {__('Nothing unexpected has changed.')}
                </p>
                <p className="m-0 text-xs" style={{ color: palette.inkMuted }}>
                  {__('We will tell you the moment something does.')}
                </p>
              </div>
            ) : (
              <ul className="m-0 flex list-none flex-col gap-1 p-0">
                {items.map(finding => (
                  <li key={finding.id}>
                    <button
                      className="scm-hover flex w-full cursor-pointer items-start gap-3 rounded-lg border-0 bg-transparent px-2 py-2 text-left transition"
                      type="button"
                      onClick={() => navigate('/log')}
                    >
                      <SeverityTag severity={finding.severity} />
                      <span className="min-w-0 flex-1">
                        <span className="block text-sm font-medium" style={{ color: palette.ink }}>
                          {changeLabel(finding.change_type)}
                        </span>
                        <span
                          className="block truncate text-xs"
                          style={{ color: palette.inkMuted }}
                        >
                          {finding.target_label ?? finding.target_url ?? __('Site-wide')}
                        </span>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>
    </>
  )
}

function Row({ label, value, tone }: { label: string; tone?: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-4">
      <dt className="m-0" style={{ color: palette.inkMuted }}>
        {label}
      </dt>
      <dd className="m-0 font-medium" style={{ color: tone ?? palette.ink }}>
        {value}
      </dd>
    </div>
  )
}
