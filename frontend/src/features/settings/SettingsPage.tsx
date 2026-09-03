import { App, Button, Card, Form, Input, InputNumber, Select, Space, Switch } from 'antd'
import { useEffect } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { usePreviewReport, useSendReport, useSettings, useUpdateSettings } from '@/api/queries'
import PageHeader from '@components/PageHeader'
import { palette } from '@config/theme'
import type { Settings } from '@/api/types'

export default function SettingsPage() {
  const [form] = Form.useForm<Settings>()
  const { message } = App.useApp()
  const { data: settings, isLoading } = useSettings()
  const updateSettings = useUpdateSettings()
  const sendReport = useSendReport()
  const previewReport = usePreviewReport()

  useEffect(() => {
    if (settings) form.setFieldsValue(settings)
  }, [settings, form])

  const submit = (values: Settings) => {
    updateSettings.mutate(values, {
      onSuccess: () => message.success(__('Settings saved.')),
      onError: error => message.error(error.message)
    })
  }

  const sendNow = () => {
    sendReport.mutate(undefined, {
      onSuccess: () => message.success(__('Report sent.')),
      onError: error => message.error(error.message)
    })
  }

  /** Opens the rendered report in a new tab so the branding can be checked. */
  const preview = () => {
    previewReport.mutate(undefined, {
      onSuccess: data => {
        const tab = window.open('', '_blank')
        if (!tab) {
          message.warning(__('Allow pop-ups to see the preview.'))

          return
        }

        tab.document.write(data.html)
        tab.document.close()
      },
      onError: error => message.error(error.message)
    })
  }

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title={__('Settings')} />

      {/*
        Spacing lives on this container, not on the cards: antd's cssinjs runs at
        high hash priority, so a margin utility on .ant-card loses to its reset.
      */}
      <Form
        className="flex flex-col gap-4"
        disabled={isLoading}
        form={form}
        layout="vertical"
        onFinish={submit}
      >
        <Card title={__('Checking')}>
          <Form.Item
            className="mb-5"
            extra={__(
              'Checks also run automatically about 90 seconds after any plugin, theme or core update.'
            )}
            label={__('How often to check')}
            name="frequency"
          >
            <Select
              options={[
                { label: __('Every hour'), value: 'hourly' },
                { label: __('Twice a day'), value: 'twicedaily' },
                { label: __('Once a day'), value: 'daily' },
                { label: __('Off (only when I click Check now)'), value: 'off' }
              ]}
            />
          </Form.Item>

          <Form.Item
            className="mb-0"
            extra={__(
              '0 keeps everything forever. Unresolved critical findings are never deleted.'
            )}
            label={__('Keep history for (days)')}
            name="retention_days"
          >
            <InputNumber className="w-40" max={3650} min={0} />
          </Form.Item>
        </Card>

        <Card title={__('Email alerts')}>
          <Form.Item
            className="mb-5"
            label={__('Email me about changes')}
            name="email_enabled"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item className="mb-5" label={__('Only email me about')} name="email_threshold">
            <Select
              options={[
                { label: __('Critical changes only'), value: 'critical' },
                { label: __('Critical and warnings'), value: 'warning' },
                { label: __('Everything, including info'), value: 'info' }
              ]}
            />
          </Form.Item>

          <Form.Item
            className="mb-0"
            extra={__('Leave empty to use the site admin email.')}
            label={__('Send to')}
            name="email_recipient"
          >
            <Input placeholder={__('you@example.com')} type="email" />
          </Form.Item>
        </Card>

        <Card
          extra={
            <Space>
              <Button
                disabled={isLoading}
                loading={previewReport.isPending}
                size="small"
                onClick={preview}
              >
                {__('Preview')}
              </Button>
              <Button
                disabled={isLoading}
                loading={sendReport.isPending}
                size="small"
                onClick={sendNow}
              >
                {__('Send now')}
              </Button>
            </Space>
          }
          title={__('Weekly client report')}
        >
          <Form.Item
            className="mb-5"
            extra={__('Sent every Monday morning. Your branding, not ours.')}
            label={__('Email a weekly summary')}
            name="report_enabled"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            className="mb-5"
            extra={__('Comma-separated. These addresses receive the report.')}
            label={__('Send to')}
            name="report_recipients"
          >
            <Input placeholder={__('client@example.com, you@agency.com')} />
          </Form.Item>

          <div className="flex gap-3">
            <Form.Item
              className="mb-5 flex-1"
              label={__('Your business name')}
              name="report_brand_name"
            >
              <Input placeholder={__('Shown in place of the plugin name')} />
            </Form.Item>
            <Form.Item className="mb-5 w-32" label={__('Accent colour')} name="report_brand_color">
              <Input placeholder="#3b5bdb" />
            </Form.Item>
          </div>

          <Form.Item className="mb-5" label={__('Logo URL')} name="report_logo_url">
            <Input placeholder="https://example.com/logo.png" />
          </Form.Item>

          <Form.Item
            className="mb-0"
            extra={__('Replaces the default line at the bottom of the report.')}
            label={__('Footer text')}
            name="report_footer"
          >
            <Input placeholder={__('Prepared by Your Agency')} />
          </Form.Item>
        </Card>

        <Card title={__('AI crawlers')}>
          <Form.Item
            className="mb-0"
            extra={__(
              'Notes the date each known AI crawler last visited. Full-page caching can hide some visits.'
            )}
            label={__('Record when AI crawlers visit')}
            name="bot_tracking"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>
        </Card>

        <div
          className="rounded-xl flex items-center gap-3 border border-solid px-5 py-4"
          style={{ background: palette.surface, borderColor: palette.line }}
        >
          <Button htmlType="submit" loading={updateSettings.isPending} type="primary">
            {__('Save settings')}
          </Button>
          <span className="text-xs" style={{ color: palette.inkMuted }}>
            {__('Changes apply to the next scheduled check.')}
          </span>
        </div>
      </Form>
    </div>
  )
}
