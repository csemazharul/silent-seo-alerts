// Vitest global setup: provide the server variables the app reads at runtime.
;(globalThis as Record<string, unknown>).SERVER_VARIABLES = {
  ajaxURL: 'http://localhost/wp-admin/admin-ajax.php',
  apiURL: { base: 'http://localhost/wp-json/SilentSeoAlerts/v1', separator: '?' },
  assetsURL: '',
  dateFormat: 'F j, Y',
  nonce: 'test-nonce',
  pluginAdminURL: 'http://localhost/wp-admin/admin.php?page=silent-seo-alerts#',
  pluginSlug: 'silent-seo-alerts',
  restNonce: 'test-rest-nonce',
  rootURL: 'http://localhost/',
  routePrefix: 'SEO_CHANGE_MONITOR_',
  settings: '',
  siteBaseURL: 'http://localhost',
  siteURL: 'http://localhost',
  timeFormat: 'g:i a',
  timeZone: 'UTC',
  version: '1.0.0'
}
