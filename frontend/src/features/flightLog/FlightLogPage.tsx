import { Button, Card, Select, Table, Tag } from 'antd'
import { useMemo, useState } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { useFindings, useTargets } from '@/api/queries'
import { changeLabel } from '@components/changeLabels'
import PageHeader from '@components/PageHeader'
import SeverityTag from '@components/SeverityTag'
import { palette } from '@config/theme'
import { selectFilters, useFlightLogStore } from '@/store/flightLogStore'
import FindingDrawer from './FindingDrawer'
import type { Finding, FindingStatus, Severity } from '@/api/types'

const STATUS_LABELS: Record<FindingStatus, string> = {
  open: __('Open'),
  resolved: __('Resolved'),
  auto_resolved: __('Auto-resolved'),
  muted: __('Muted')
}

export default function FlightLogPage() {
  const [selected, setSelected] = useState<Finding | null>(null)
  const store = useFlightLogStore()
  // Derived here rather than in a store selector: selectFilters builds a new
  // object every call, which would re-render forever as a zustand selector.
  const filters = useMemo(() => selectFilters(store), [store])

  const { data, isLoading } = useFindings(filters)
  const { data: targets } = useTargets()

  const hasFilters =
    store.severity.length > 0 || store.targetId !== undefined || store.status.length !== 1

  return (
    <>
      <PageHeader
        description={__('Every change we have recorded, with what it means in plain English.')}
        title={__('Flight Log')}
      />

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Select<Severity[]>
          allowClear
          className="min-w-48"
          mode="multiple"
          placeholder={__('All severities')}
          value={store.severity}
          onChange={store.setSeverity}
          options={[
            { label: __('Critical'), value: 'critical' },
            { label: __('Warning'), value: 'warning' },
            { label: __('Info'), value: 'info' }
          ]}
        />
        <Select<FindingStatus[]>
          allowClear
          className="min-w-48"
          mode="multiple"
          placeholder={__('Any status')}
          value={store.status}
          onChange={store.setStatus}
          options={(Object.keys(STATUS_LABELS) as FindingStatus[]).map(status => ({
            label: STATUS_LABELS[status],
            value: status
          }))}
        />
        <Select
          allowClear
          className="min-w-56"
          placeholder={__('All pages')}
          value={store.targetId}
          onChange={store.setTargetId}
          options={(targets ?? []).map(target => ({ label: target.label, value: target.id }))}
        />
        {hasFilters && (
          <Button type="text" onClick={store.reset}>
            {__('Clear filters')}
          </Button>
        )}
      </div>

      <Card styles={{ body: { padding: '4px 8px 8px' } }}>
        <Table<Finding>
          dataSource={data?.items ?? []}
          loading={isLoading}
          rowClassName="cursor-pointer"
          rowKey="id"
          size="middle"
          onRow={finding => ({ onClick: () => setSelected(finding) })}
          locale={{
            emptyText: (
              <div className="py-10 text-center">
                <p className="m-0 text-sm font-medium" style={{ color: palette.ink }}>
                  {__('No changes recorded yet.')}
                </p>
                <p className="m-0 text-xs" style={{ color: palette.inkMuted }}>
                  {__('Anything that changes on your monitored pages will appear here.')}
                </p>
              </div>
            )
          }}
          pagination={{
            current: data?.page ?? 1,
            pageSize: data?.per_page ?? 20,
            total: data?.total ?? 0,
            showSizeChanger: false,
            hideOnSinglePage: true,
            onChange: store.setPage
          }}
          columns={[
            {
              title: __('Severity'),
              dataIndex: 'severity',
              width: 120,
              render: (severity: Severity) => <SeverityTag severity={severity} />
            },
            {
              title: __('What changed'),
              dataIndex: 'change_type',
              render: (type: string, finding) => (
                <div className="flex min-w-0 flex-col">
                  <span className="font-medium">{changeLabel(type)}</span>
                  <span className="truncate text-xs" style={{ color: palette.inkMuted }}>
                    {finding.explanation.what}
                  </span>
                </div>
              )
            },
            {
              title: __('Page'),
              dataIndex: 'target_label',
              width: 180,
              render: (label: null | string) => label ?? __('Site-wide')
            },
            {
              title: __('Status'),
              dataIndex: 'status',
              width: 140,
              render: (status: FindingStatus) => (
                <Tag bordered={false} color={status === 'open' ? 'default' : 'success'}>
                  {STATUS_LABELS[status]}
                </Tag>
              )
            },
            {
              title: __('Detected'),
              dataIndex: 'created_at',
              width: 180
            }
          ]}
        />
      </Card>

      <FindingDrawer finding={selected} onClose={() => setSelected(null)} />
    </>
  )
}
