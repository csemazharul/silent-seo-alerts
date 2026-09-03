import {
  App,
  Button,
  Card,
  Drawer,
  Empty,
  Input,
  Modal,
  Space,
  Tag,
  Timeline,
  Typography
} from 'antd'
import { useEffect, useState } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { useExplainWithAi, useReopenFinding, useResolveFinding, useSettings } from '@/api/queries'
import { changeLabel, eventLabel } from '@components/changeLabels'
import SeverityTag from '@components/SeverityTag'
import When from '@components/When'
import { palette } from '@config/theme'
import type { AiExplanation, Finding } from '@/api/types'

const renderValue = (value: unknown) => {
  if (value === null || value === undefined || value === '') {
    return (
      <span className="italic" style={{ color: palette.inkFaint }}>
        {__('(empty)')}
      </span>
    )
  }

  if (typeof value === 'object') {
    return <pre className="scm-code max-h-48">{JSON.stringify(value, null, 2)}</pre>
  }

  return <span className="break-all text-sm">{String(value)}</span>
}

interface Props {
  finding: Finding | null
  onClose: () => void
}

export default function FindingDrawer({ finding, onClose }: Props) {
  const { message } = App.useApp()
  const [note, setNote] = useState('')
  const [pendingAction, setPendingAction] = useState<'mute' | 'resolve' | null>(null)
  const [aiText, setAiText] = useState<AiExplanation | null>(null)
  const resolveFinding = useResolveFinding()
  const reopenFinding = useReopenFinding()
  const explainWithAi = useExplainWithAi()
  const { data: settings } = useSettings()

  const aiAvailable = Boolean(settings?.ai_enabled && settings?.ai_api_key_set)

  // Each finding carries its own interpretation; clear it when the drawer moves.
  useEffect(() => setAiText(null), [finding?.id])

  if (!finding) return null

  const runExplain = (refresh: boolean) => {
    explainWithAi.mutate(
      { id: finding.id, refresh },
      {
        onSuccess: setAiText,
        // A failure leaves the written explanation above untouched.
        onError: error => message.error(error.message)
      }
    )
  }

  const isClosed = finding.status !== 'open'

  const confirm = () => {
    if (note.trim() === '') {
      message.warning(__('Please add a short note explaining why.'))

      return
    }

    resolveFinding.mutate(
      { id: finding.id, note, action: pendingAction as 'mute' | 'resolve' },
      {
        onSuccess: () => {
          message.success(pendingAction === 'mute' ? __('Muted.') : __('Marked resolved.'))
          setNote('')
          setPendingAction(null)
          onClose()
        },
        onError: error => message.error(error.message)
      }
    )
  }

  return (
    <>
      <Drawer
        open
        // The drawer portals to <body> at z-index 1000, under wp-admin's own
        // toolbar; the class offsets it so the header stays clickable.
        rootClassName="scm-drawer"
        width={620}
        onClose={onClose}
        title={
          <span className="flex items-center gap-2">
            <SeverityTag severity={finding.severity} />
            {changeLabel(finding.change_type)}
          </span>
        }
        extra={
          isClosed ? (
            <Button
              size="small"
              onClick={() =>
                reopenFinding.mutate(finding.id, {
                  onSuccess: () => message.success(__('Reopened.'))
                })
              }
            >
              {__('Reopen')}
            </Button>
          ) : (
            <Space>
              <Button size="small" onClick={() => setPendingAction('mute')}>
                {__('Mute')}
              </Button>
              <Button size="small" type="primary" onClick={() => setPendingAction('resolve')}>
                {__('Resolve')}
              </Button>
            </Space>
          )
        }
      >
        <div className="flex flex-col gap-4">
          <div className="text-sm" style={{ color: palette.inkMuted }}>
            {finding.target_url ? (
              <a href={finding.target_url} rel="noreferrer" target="_blank">
                {finding.target_label ?? finding.target_url}
              </a>
            ) : (
              __('Site-wide')
            )}
            <span className="mx-2">·</span>
            <When value={finding.created_at} />
            {finding.is_expected && (
              <Tag bordered={false} className="ml-2" color="default">
                {__('you edited this page')}
              </Tag>
            )}
          </div>

          <Card size="small" title={__('What changed')}>
            <div className="mb-4">
              <div
                className="mb-1.5 text-xs font-semibold uppercase tracking-wide"
                style={{ color: palette.inkFaint }}
              >
                {__('Before')}
              </div>
              {renderValue(finding.before)}
            </div>
            <div>
              <div
                className="mb-1.5 text-xs font-semibold uppercase tracking-wide"
                style={{ color: palette.inkFaint }}
              >
                {__('After')}
              </div>
              {renderValue(finding.after)}
            </div>
          </Card>

          <Card size="small" title={__('What this means')}>
            <div className="flex flex-col gap-3 text-sm">
              <div>
                <Typography.Text strong>{__('What happened')}</Typography.Text>
                <p className="mb-0 mt-1">{finding.explanation.what}</p>
              </div>
              <div>
                <Typography.Text strong>{__('Why it matters')}</Typography.Text>
                <p className="mb-0 mt-1">{finding.explanation.why}</p>
              </div>
              <div>
                <Typography.Text strong>{__('What to check')}</Typography.Text>
                <p className="mb-0 mt-1">{finding.explanation.check}</p>
              </div>
            </div>
          </Card>

          {aiAvailable && (
            <Card
              size="small"
              title={__('AI interpretation')}
              extra={
                <Button
                  loading={explainWithAi.isPending}
                  size="small"
                  onClick={() => runExplain(Boolean(aiText))}
                >
                  {aiText ? __('Regenerate') : __('Explain this')}
                </Button>
              }
            >
              {aiText ? (
                <>
                  <p className="m-0 text-sm">{aiText.text}</p>
                  <p className="mb-0 mt-2 text-xs" style={{ color: palette.inkFaint }}>
                    {__('Written by')} {aiText.model}
                    {aiText.cached ? ` · ${__('cached, no new API call')}` : ''} ·{' '}
                    {__('an interpretation of the values above, not a measurement')}
                  </p>
                </>
              ) : (
                <p className="m-0 text-sm" style={{ color: palette.inkMuted }}>
                  {__(
                    'Ask your AI provider to interpret this specific change. Only the values above and the site events are sent, never your page content.'
                  )}
                </p>
              )}
            </Card>
          )}

          <Card size="small" title={__('Site events just before this change')}>
            {finding.attributed_events.length === 0 ? (
              <Empty
                description={__('Nothing happened on the site in the window before this change.')}
                image={Empty.PRESENTED_IMAGE_SIMPLE}
              />
            ) : (
              <>
                <Typography.Text type="secondary" className="mb-3 block text-xs">
                  {__(
                    'These happened shortly before. They may be related, but we cannot be certain.'
                  )}
                </Typography.Text>
                <Timeline
                  items={finding.attributed_events.map(event => ({
                    children: (
                      <span className="text-sm">
                        <strong>{eventLabel(event.type)}</strong> {event.subject}
                        <When className="ml-2" value={event.at} />
                      </span>
                    )
                  }))}
                />
              </>
            )}
          </Card>

          {finding.note && (
            <Card size="small" title={__('Note')}>
              <p className="mb-0 text-sm">{finding.note}</p>
            </Card>
          )}
        </div>
      </Drawer>

      <Modal
        confirmLoading={resolveFinding.isPending}
        okText={pendingAction === 'mute' ? __('Mute') : __('Resolve')}
        open={pendingAction !== null}
        title={pendingAction === 'mute' ? __('Mute this finding') : __('Mark as resolved')}
        onCancel={() => setPendingAction(null)}
        onOk={confirm}
      >
        <Typography.Paragraph type="secondary" className="text-sm">
          {__('Leave a short note so you remember why, when you look back at this later.')}
        </Typography.Paragraph>
        <Input.TextArea
          autoFocus
          placeholder={__('e.g. Intentional: we rewrote this page for the new campaign.')}
          rows={3}
          value={note}
          onChange={event => setNote(event.target.value)}
        />
      </Modal>
    </>
  )
}
