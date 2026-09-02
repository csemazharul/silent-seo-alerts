import { __ } from '@common/helpers/i18nWrap'

/** Human-readable names for change types, mirroring the PHP ChangeTypes registry. */
const LABELS: Record<string, string> = {
  title_changed: __('Title changed'),
  title_removed: __('Title removed'),
  meta_description_changed: __('Meta description changed'),
  meta_description_removed: __('Meta description removed'),
  meta_robots_changed: __('Robots tag changed'),
  noindex_added: __('Page set to noindex'),
  noindex_removed: __('Noindex removed'),
  nofollow_added: __('Page set to nofollow'),
  canonical_changed: __('Canonical URL changed'),
  canonical_removed: __('Canonical URL removed'),
  canonical_offsite: __('Canonical points off-site'),
  schema_type_removed: __('Schema type removed'),
  schema_type_added: __('Schema type added'),
  schema_invalid_json: __('Schema markup broken'),
  og_changed: __('Open Graph tags changed'),
  twitter_changed: __('Twitter card changed'),
  h1_changed: __('H1 heading changed'),
  h1_count_changed: __('H1 count changed'),
  h1_removed: __('H1 heading removed'),
  http_status_error: __('Page returns an error'),
  redirect_added: __('Redirect added'),
  redirect_offsite: __('Redirects off-site'),
  word_count_drop_major: __('Most content disappeared'),
  word_count_drop: __('Content shrank'),
  robots_txt_changed: __('robots.txt changed'),
  robots_txt_blocks_all: __('robots.txt blocks all crawlers'),
  ai_bot_rule_changed: __('AI crawler rules changed'),
  sitemap_unreachable: __('Sitemap unreachable'),
  sitemap_invalid: __('Sitemap invalid'),
  sitemap_count_drop: __('Sitemap URL count dropped'),
  blog_public_disabled: __('Search engines discouraged'),
  ai_bot_gone_silent: __('AI crawler stopped visiting'),
  monitoring_impaired: __('Monitoring impaired')
}

export const changeLabel = (type: string) =>
  LABELS[type] ?? type.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())
