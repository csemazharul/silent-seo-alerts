export type Severity = 'critical' | 'info' | 'warning'

export type FindingStatus = 'auto_resolved' | 'muted' | 'open' | 'resolved'

export type TargetType = 'page' | 'robots' | 'site_settings' | 'sitemap'

export interface Target {
  id: number
  type: TargetType
  post_id: null | number
  url: string
  label: string
  is_active: number
  last_checked_at: null | string
  last_result: null | string
  created_at: null | string
}

export interface Explanation {
  what: string
  why: string
  check: string
}

export interface AttributedEvent {
  event_id?: number
  type: string
  subject: string
  at: string
}

export interface Finding {
  id: number
  target_id: null | number
  target_label: null | string
  target_url: null | string
  target_type: null | TargetType
  change_type: string
  severity: Severity
  status: FindingStatus
  before: unknown
  after: unknown
  context: Record<string, unknown>
  attributed_events: AttributedEvent[]
  is_expected: boolean
  note: null | string
  resolved_at: null | string
  created_at: null | string
  explanation: Explanation
}

export interface FindingsPage {
  items: Finding[]
  total: number
  page: number
  per_page: number
}

export interface CheckRun {
  id: number
  trigger_type: string
  status: 'complete' | 'failed' | 'impaired' | 'running'
  targets_total: number
  targets_done: number
  critical_count: number
  warning_count: number
  info_count: number
  started_at: null | string
  finished_at: null | string
}

export interface Settings {
  frequency: 'daily' | 'hourly' | 'off' | 'twicedaily'
  email_enabled: boolean
  email_threshold: Severity
  email_recipient: string
  retention_days: number
  bot_tracking: boolean
  webhook_enabled: boolean
  webhook_urls: string[]
  webhook_threshold: Severity
  webhook_secret: string
  ai_enabled: boolean
  ai_provider: 'anthropic' | 'openai'
  ai_api_key: string
  ai_api_key_set?: boolean
  ai_api_key_clear?: boolean
  ai_model: string
  ai_monthly_cap: number
  slack_enabled: boolean
  slack_webhook_url: string
  slack_threshold: Severity
  report_enabled: boolean
  report_recipients: string
  report_brand_name: string
  report_brand_color: string
  report_logo_url: string
  report_footer: string
}

export interface AiUsage {
  month: string
  calls: number
  cost: number
}

export interface AiExplanation {
  text: string
  provider: string
  model: string
  generated_at: string
  cached: boolean
  cost?: number
  usage?: AiUsage
}

export interface SiteEventSummary {
  id: number
  type: string
  subject: string
  at: string
}

export interface BaselineState {
  armed_at: number
  targets: number
}

export interface DashboardSummary {
  open_counts: { critical: number; warning: number; info: number }
  active_targets: number
  last_run: CheckRun | null
  next_run_at: null | string
  frequency: string
  impaired: null | { since: string; reason: string }
  baseline: BaselineState | null
  recent_events: SiteEventSummary[]
}

export interface PostSearchResult {
  post_id: number
  title: string
  url: string
  type: string
}

export interface FindingFilters {
  severity?: Severity[]
  status?: FindingStatus[]
  target_id?: number
  change_type?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}
