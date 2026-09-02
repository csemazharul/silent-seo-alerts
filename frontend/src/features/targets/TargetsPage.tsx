import { PlusOutlined } from '@ant-design/icons'
import { App, Button, Card, Popconfirm, Switch, Table, Tag } from 'antd'
import { useState } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { useDeleteTarget, useTargets, useUpdateTarget } from '@/api/queries'
import { resultLabel } from '@components/changeLabels'
import PageHeader from '@components/PageHeader'
import When from '@components/When'
import { palette } from '@config/theme'
import AddTargetModal from './AddTargetModal'
import type { Target } from '@/api/types'

const RESULT_TONE: Record<string, string> = {
  ok: 'success',
  changed: 'warning',
  error: 'error',
  impaired: 'default'
}

export default function TargetsPage() {
  const [isAdding, setIsAdding] = useState(false)
  const { message } = App.useApp()
  const { data: targets, isLoading } = useTargets()
  const updateTarget = useUpdateTarget()
  const deleteTarget = useDeleteTarget()

  const toggle = (target: Target, isActive: boolean) => {
    updateTarget.mutate(
      { id: target.id, is_active: isActive },
      { onError: error => message.error(error.message) }
    )
  }

  const remove = (target: Target) => {
    deleteTarget.mutate(target.id, {
      onSuccess: () => message.success(__('Page removed from monitoring.')),
      onError: error => message.error(error.message)
    })
  }

  return (
    <>
      <PageHeader
        actions={
          <Button icon={<PlusOutlined />} type="primary" onClick={() => setIsAdding(true)}>
            {__('Add page')}
          </Button>
        }
        description={__('Pages checked on every run. Switch off anything you do not need watched.')}
        title={__('Monitored Pages')}
      />

      <Card styles={{ body: { padding: '4px 8px 8px' } }}>
        <Table<Target>
          dataSource={targets ?? []}
          loading={isLoading}
          pagination={{ pageSize: 20, hideOnSinglePage: true }}
          rowKey="id"
          size="middle"
          columns={[
            {
              title: __('Page'),
              dataIndex: 'label',
              render: (label: string, target) => (
                <div className="flex min-w-0 flex-col">
                  <span className="truncate font-medium">{label}</span>
                  <a
                    className="truncate text-xs"
                    href={target.url}
                    rel="noreferrer"
                    style={{ color: palette.inkFaint }}
                    target="_blank"
                  >
                    {target.url}
                  </a>
                </div>
              )
            },
            {
              title: __('Last checked'),
              dataIndex: 'last_checked_at',
              width: 150,
              render: (value: null | string) => <When fallback={__('Not yet')} value={value} />
            },
            {
              title: __('Result'),
              dataIndex: 'last_result',
              width: 120,
              render: (result: null | string) =>
                result ? (
                  <Tag bordered={false} color={RESULT_TONE[result] ?? 'default'}>
                    {resultLabel(result)}
                  </Tag>
                ) : null
            },
            {
              title: __('Monitoring'),
              dataIndex: 'is_active',
              width: 120,
              render: (isActive: number, target) => (
                <Switch
                  checked={Boolean(isActive)}
                  size="small"
                  onChange={checked => toggle(target, checked)}
                />
              )
            },
            {
              title: '',
              width: 100,
              align: 'right',
              render: (_, target) => (
                <Popconfirm
                  cancelText={__('Cancel')}
                  okText={__('Remove')}
                  title={__('Stop monitoring this page?')}
                  description={__('Its history stays in the flight log.')}
                  onConfirm={() => remove(target)}
                >
                  <Button className="scm-row-remove" size="small" type="text">
                    {__('Remove')}
                  </Button>
                </Popconfirm>
              )
            }
          ]}
        />
      </Card>

      <AddTargetModal open={isAdding} onClose={() => setIsAdding(false)} />
    </>
  )
}
