import { App, Button, Card, Form, Input, InputNumber, Select, Switch } from 'antd'
import { useEffect } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import {
  useAiUsage,
  useSettings,
  useTestSlack,
  useTestWebhook,
  useUpdateSettings
} from '@/api/queries'
import PageHeader from '@components/PageHeader'
import { palette } from '@config/theme'
import type { Settings } from '@/api/types'

/**
 * Everything that connects to a service outside this site. Each card needs a
 * URL or key the user brings from elsewhere, which is what separates these
 * from the built-in email alerts on the Settings page.
 */
export default function IntegrationsPage() {
  const [form] = Form.useForm<Settings>()
  const { message } = App.useApp()
  const { data: settings, isLoading } = useSettings()
  const updateSettings = useUpdateSettings()
  const testWebhook = useTestWebhook()
  const testSlack = useTestSlack()

  const aiProvider = Form.useWatch('ai_provider', form)
  const aiEnabled = Form.useWatch('ai_enabled', form)
  const { data: aiUsage } = useAiUsage(Boolean(aiEnabled))

  useEffect(() => {
    if (settings) form.setFieldsValue(settings)
  }, [settings, form])

  const submit = (values: Settings) => {
    updateSettings.mutate(values, {
      onSuccess: () => message.success(__('Integrations saved.')),
      onError: error => message.error(error.message)
    })
  }

  /** Tests the first endpoint currently in the field, saved or not. */
  const sendWebhookTest = () => {
    const urls: unknown = form.getFieldValue('webhook_urls')
    const first = (
      Array.isArray(urls) ? String(urls[0] ?? '') : String(urls ?? '').split('\n')[0]
    ).trim()

    if (first === '') {
      message.warning(__('Add an endpoint URL first.'))

      return
    }

    testWebhook.mutate(first, {
      onSuccess: () => message.success(__('Your endpoint accepted the test delivery.')),
      onError: error => message.error(error.message)
    })
  }

  const sendSlackTest = () => {
    const url = String(form.getFieldValue('slack_webhook_url') ?? '').trim()

    if (url === '') {
      message.warning(__('Add your Slack webhook URL first.'))

      return
    }

    testSlack.mutate(url, {
      onSuccess: () => message.success(__('Posted to Slack.')),
      onError: error => message.error(error.message)
    })
  }

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title={__('Integrations')} />

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
        <Card
          extra={
            <Button
              disabled={isLoading}
              loading={testWebhook.isPending}
              size="small"
              onClick={sendWebhookTest}
            >
              {__('Send test')}
            </Button>
          }
          title={__('Webhooks')}
        >
          <Form.Item
            className="mb-5"
            extra={__(
              'Posts each check result as JSON to your own endpoints, so you can pipe findings anywhere.'
            )}
            label={__('Send results to a webhook')}
            name="webhook_enabled"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            className="mb-5"
            extra={__('One URL per line, up to 10.')}
            label={__('Endpoint URLs')}
            name="webhook_urls"
            getValueProps={value => ({ value: Array.isArray(value) ? value.join('\n') : value })}
            normalize={value =>
              String(value ?? '')
                .split('\n')
                .map(line => line.trim())
                .filter(Boolean)
            }
          >
            <Input.TextArea placeholder="https://example.com/hooks/seo" rows={3} />
          </Form.Item>

          <Form.Item className="mb-5" label={__('Only send')} name="webhook_threshold">
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
            extra={__(
              'Optional. When set, each request carries an X-SEO-Monitor-Signature header so your endpoint can verify it came from this site.'
            )}
            label={__('Signing secret')}
            name="webhook_secret"
          >
            <Input.Password autoComplete="off" placeholder={__('Leave empty for no signature')} />
          </Form.Item>
        </Card>

        <Card
          extra={
            <Button
              disabled={isLoading}
              loading={testSlack.isPending}
              size="small"
              onClick={sendSlackTest}
            >
              {__('Send test')}
            </Button>
          }
          title={__('Slack')}
        >
          <Form.Item
            className="mb-5"
            label={__('Post results to Slack')}
            name="slack_enabled"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            className="mb-5"
            extra={__('Create an incoming webhook in Slack and paste its URL here.')}
            label={__('Incoming webhook URL')}
            name="slack_webhook_url"
          >
            <Input placeholder="https://hooks.slack.com/services/…" />
          </Form.Item>

          <Form.Item className="mb-0" label={__('Only post')} name="slack_threshold">
            <Select
              options={[
                { label: __('Critical changes only'), value: 'critical' },
                { label: __('Critical and warnings'), value: 'warning' },
                { label: __('Everything, including info'), value: 'info' }
              ]}
            />
          </Form.Item>
        </Card>

        <Card
          extra={
            aiUsage?.usage ? (
              <span className="text-xs" style={{ color: palette.inkMuted }}>
                {aiUsage.usage.calls} {__('this month')} · ~${aiUsage.usage.cost.toFixed(2)}
              </span>
            ) : null
          }
          title={__('AI explanations')}
        >
          <p className="mb-4 text-sm" style={{ color: palette.inkMuted }}>
            {__(
              'Uses your own API key to interpret a specific change on demand. Nothing is sent unless you click "Explain this" on a finding, and only the change values travel, never your page content.'
            )}
          </p>

          <Form.Item
            className="mb-5"
            label={__('Enable AI explanations')}
            name="ai_enabled"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item className="mb-5" label={__('Provider')} name="ai_provider">
            <Select
              options={[
                { label: 'Anthropic (Claude)', value: 'anthropic' },
                { label: 'OpenAI', value: 'openai' }
              ]}
            />
          </Form.Item>

          <Form.Item
            className="mb-5"
            extra={
              settings?.ai_api_key_set
                ? __('A key is saved. Leave blank to keep it, or paste a new one to replace it.')
                : __('Your key is stored in your own database and never sent to us.')
            }
            label={__('API key')}
            name="ai_api_key"
          >
            <Input.Password
              autoComplete="off"
              placeholder={settings?.ai_api_key_set ? '••••••••' : 'sk-…'}
            />
          </Form.Item>

          <Form.Item
            className="mb-5"
            extra={__('Leave blank to use the cheapest model for the chosen provider.')}
            label={__('Model')}
            name="ai_model"
          >
            <Input placeholder={aiProvider === 'openai' ? 'gpt-4o-mini' : 'claude-haiku-4-5'} />
          </Form.Item>

          <Form.Item
            className="mb-0"
            extra={__(
              "Explanations stop once this month's estimated spend reaches the cap. 0 means no cap."
            )}
            label={__('Monthly spending cap (USD)')}
            name="ai_monthly_cap"
          >
            <InputNumber className="w-40" max={1000} min={0} step={1} />
          </Form.Item>
        </Card>

        <div
          className="rounded-xl flex items-center gap-3 border border-solid px-5 py-4"
          style={{ background: palette.surface, borderColor: palette.line }}
        >
          <Button htmlType="submit" loading={updateSettings.isPending} type="primary">
            {__('Save integrations')}
          </Button>
          <span className="text-xs" style={{ color: palette.inkMuted }}>
            {__('Each service is off until you switch it on.')}
          </span>
        </div>
      </Form>
    </div>
  )
}
