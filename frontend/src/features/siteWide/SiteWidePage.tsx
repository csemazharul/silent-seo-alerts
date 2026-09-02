import { Alert, Card, Table, Tag } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { __ } from '@common/helpers/i18nWrap'
import call from '@/api/client'
import PageHeader from '@components/PageHeader'
import { palette } from '@config/theme'

interface BotRow {
  slug: string
  label: string
  user_agent: string
  last_seen: null | string
  hits: number
}

interface SitePanel {
  checked_at: null | string
  fields: Record<string, unknown>
  error: null | string
}

interface SiteStatus {
  robots: null | SitePanel
  sitemap: null | SitePanel
  settings: null | SitePanel
  bots: BotRow[]
  bot_tracking: boolean
  impaired: null | { since: string; reason: string }
}

const verdictTag = (verdict?: string) => {
  if (verdict === 'blocked') return <Tag color="error">{__('Blocked')}</Tag>
  if (verdict === 'allowed') return <Tag color="success">{__('Allowed')}</Tag>

  return <Tag>{__('Not specified')}</Tag>
}

function Placeholder({ text }: { text: string }) {
  return (
    <p className="m-0 py-6 text-center text-sm" style={{ color: palette.inkMuted }}>
      {text}
    </p>
  )
}

export default function SiteWidePage() {
  const { data, isLoading } = useQuery({
    queryKey: ['site-status'],
    queryFn: () => call<SiteStatus>('status/site')
  })

  const robots = data?.robots?.fields ?? {}
  const sitemap = data?.sitemap?.fields ?? {}
  const settings = data?.settings?.fields ?? {}
  const aiBots = (robots.ai_bots ?? {}) as Record<string, string>
  const isPublic = settings.blog_public !== false

  return (
    <>
      <PageHeader
        description={__('The settings and files that affect every page at once.')}
        title={__('Site-wide')}
      />

      {/* Spacing lives on this column: margin utilities on antd roots lose to
          its cssinjs reset at high hash priority. */}
      <div className="flex flex-col gap-4">
        {!isPublic && (
          <Alert
            showIcon
            description={__(
              'WordPress is asking search engines to stay away. Until this is turned off in Settings → Reading, your pages will not appear in search results.'
            )}
            message={__('Search engines are being discouraged from indexing this site')}
            type="error"
          />
        )}

        <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        <Card loading={isLoading} title={__('robots.txt')}>
          {robots.reachable === false ? (
            <Placeholder text={__('Not reachable.')} />
          ) : (
            <div className="flex flex-col gap-3">
              {Boolean(robots.blocks_all) && (
                <Alert
                  showIcon
                  message={__('This file blocks all crawlers from the whole site.')}
                  type="error"
                />
              )}
              <pre className="scm-code max-h-56">
                {String(robots.raw ?? '').trim() || __('(empty file)')}
              </pre>
            </div>
          )}
        </Card>

        <Card loading={isLoading} title={__('XML sitemap')}>
          {sitemap.reachable ? (
            <div className="flex flex-col gap-2">
              <div className="flex items-baseline gap-2">
                <span className="text-3xl font-semibold leading-none">
                  {Number(sitemap.url_count ?? 0)}
                </span>
                <span className="text-sm" style={{ color: palette.inkMuted }}>
                  {__('URLs listed')}
                </span>
              </div>
              <a
                className="break-all text-xs"
                href={String(sitemap.url ?? '')}
                rel="noreferrer"
                target="_blank"
              >
                {String(sitemap.url ?? '')}
              </a>
              {sitemap.valid === false && (
                <Alert showIcon message={__('Sitemap is not valid XML.')} type="error" />
              )}
            </div>
          ) : (
            <Placeholder
              text={
                isPublic
                  ? __('No sitemap found at the usual locations.')
                  : __('WordPress disables the sitemap while search engines are discouraged.')
              }
            />
          )}
          </Card>
        </div>

        <Card
          extra={
            <span className="text-xs" style={{ color: palette.inkMuted }}>
              {__('Last-seen counts only requests that reach WordPress. Caching can hide visits.')}
            </span>
          }
          loading={isLoading}
          styles={{ body: { paddingTop: 4 } }}
          title={__('AI crawlers')}
        >
        <Table<BotRow>
          dataSource={data?.bots ?? []}
          pagination={false}
          rowKey="slug"
          size="middle"
          columns={[
            {
              title: __('Crawler'),
              dataIndex: 'label',
              render: (label: string, row) => (
                <div className="flex flex-col">
                  <span className="font-medium">{label}</span>
                  <span className="text-xs" style={{ color: palette.inkFaint }}>
                    {row.user_agent}
                  </span>
                </div>
              )
            },
            {
              title: __('robots.txt says'),
              dataIndex: 'slug',
              width: 170,
              render: (slug: string) => verdictTag(aiBots[slug])
            },
            {
              title: __('Last seen here'),
              dataIndex: 'last_seen',
              width: 220,
              render: (lastSeen: null | string, row) => {
                if (!data?.bot_tracking) {
                  return (
                    <span className="text-xs" style={{ color: palette.inkFaint }}>
                      {__('tracking off')}
                    </span>
                  )
                }

                if (!lastSeen) {
                  return (
                    <span className="text-xs" style={{ color: palette.inkFaint }}>
                      {__('never seen')}
                    </span>
                  )
                }

                const daysAgo = (Date.now() - new Date(`${lastSeen}Z`).getTime()) / 86_400_000

                return (
                  <span className="flex items-center gap-2 text-sm">
                    <span
                      className="h-1.5 w-1.5 shrink-0 rounded-full"
                      style={{ background: daysAgo > 30 ? palette.warning : palette.success }}
                    />
                    {lastSeen}
                    <span className="text-xs" style={{ color: palette.inkFaint }}>
                      {row.hits}
                    </span>
                  </span>
                )
              }
            }
            ]}
          />
        </Card>
      </div>
    </>
  )
}
