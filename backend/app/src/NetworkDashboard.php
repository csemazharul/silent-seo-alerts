<?php

namespace SEOChangeMonitor\src;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\ImpairedState;

/**
 * Network-admin overview across every site in a WordPress multisite install.
 *
 * Deliberately reads each site's own tables in-process rather than calling out
 * anywhere, so the "contacts nothing external" promise still holds. A hosted
 * dashboard spanning separate installs would break that and is a different
 * product.
 */
class NetworkDashboard
{
    /** Reading every site on a large network is expensive; cache briefly. */
    public const CACHE_SECONDS = 300;

    public const MAX_SITES = 200;

    public function __construct()
    {
        Hooks::addAction('network_admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu()
    {
        add_menu_page(
            __('Silent SEO Alerts', 'silent-seo-alerts'),
            __('Silent SEO Alerts', 'silent-seo-alerts'),
            'manage_network_options',
            Config::SLUG . '-network',
            [$this, 'render'],
            'dashicons-visibility',
            26
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collect($useCache = true)
    {
        // get_sites()/switch_to_blog() only exist on a network install, so this
        // stays inert rather than fatal if it is ever reached on a single site.
        if (!is_multisite()) {
            return [];
        }

        $cacheKey = Config::withPrefix('network_summary');

        if ($useCache) {
            $cached = get_site_transient($cacheKey);
            if (\is_array($cached)) {
                return $cached;
            }
        }

        $rows = [];
        foreach (get_sites(['number' => self::MAX_SITES, 'fields' => 'ids']) as $siteId) {
            switch_to_blog((int) $siteId);

            try {
                $lastRun = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();

                $rows[] = [
                    'site_id'   => (int) $siteId,
                    'name'      => get_bloginfo('name'),
                    'url'       => home_url('/'),
                    'admin_url' => admin_url('admin.php?page=' . Config::SLUG),
                    'pages'     => (int) Target::where('is_active', 1)->where('type', Target::TYPE_PAGE)->count(),
                    'critical'  => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_CRITICAL)->count(),
                    'warning'   => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_WARNING)->count(),
                    'last_run'  => $lastRun ? $lastRun->finished_at : null,
                    'impaired'  => ImpairedState::isImpaired(),
                    'active'    => true,
                ];
            } catch (\Throwable $e) {
                // A site where the plugin was never activated has no tables.
                $rows[] = [
                    'site_id'   => (int) $siteId,
                    'name'      => get_bloginfo('name'),
                    'url'       => home_url('/'),
                    'admin_url' => admin_url('admin.php?page=' . Config::SLUG),
                    'pages'     => 0,
                    'critical'  => 0,
                    'warning'   => 0,
                    'last_run'  => null,
                    'impaired'  => false,
                    'active'    => false,
                ];
            }

            restore_current_blog();
        }

        // Worst first, the only order a network admin cares about.
        usort(
            $rows,
            static fn ($a, $b) => [$b['critical'], $b['warning']] <=> [$a['critical'], $a['warning']]
        );

        set_site_transient($cacheKey, $rows, self::CACHE_SECONDS);

        return $rows;
    }

    public function render()
    {
        if (!current_user_can('manage_network_options')) {
            return;
        }

        $refresh = isset($_GET['refresh']) && check_admin_referer('scm_network_refresh');
        $rows    = $this->collect(!$refresh);

        $totals = ['critical' => 0, 'warning' => 0, 'pages' => 0, 'impaired' => 0];
        foreach ($rows as $row) {
            $totals['critical'] += $row['critical'];
            $totals['warning']  += $row['warning'];
            $totals['pages']    += $row['pages'];
            $totals['impaired'] += $row['impaired'] ? 1 : 0;
        }

        $refreshUrl = wp_nonce_url(
            add_query_arg(['page' => Config::SLUG . '-network', 'refresh' => 1], network_admin_url('admin.php')),
            'scm_network_refresh'
        );

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('Silent SEO Alerts network overview', 'silent-seo-alerts'));

        printf(
            '<p>%s</p>',
            esc_html(
                sprintf(
                    /* translators: 1: sites, 2: pages, 3: critical, 4: warnings */
                    __('%1$d sites · %2$d pages monitored · %3$d critical · %4$d warnings', 'silent-seo-alerts'),
                    \count($rows),
                    $totals['pages'],
                    $totals['critical'],
                    $totals['warning']
                )
            )
        );

        if ($totals['impaired'] > 0) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        /* translators: %d: number of sites */
                        _n(
                            'Monitoring is impaired on %d site. Its results cannot be trusted until fixed.',
                            'Monitoring is impaired on %d sites. Their results cannot be trusted until fixed.',
                            $totals['impaired'],
                            'silent-seo-alerts'
                        ),
                        $totals['impaired']
                    )
                )
            );
        }

        printf(
            '<p><a class="button" href="%s">%s</a> <span style="color:#646970;">%s</span></p>',
            esc_url($refreshUrl),
            esc_html__('Refresh now', 'silent-seo-alerts'),
            esc_html__('Figures are cached for 5 minutes.', 'silent-seo-alerts')
        );

        echo '<table class="widefat striped"><thead><tr>';
        foreach (
            [
                __('Site', 'silent-seo-alerts'),
                __('Critical', 'silent-seo-alerts'),
                __('Warnings', 'silent-seo-alerts'),
                __('Pages', 'silent-seo-alerts'),
                __('Last check', 'silent-seo-alerts'),
                '',
            ] as $heading
        ) {
            printf('<th>%s</th>', esc_html($heading));
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            printf(
                '<td><strong>%s</strong><br><span style="color:#646970;">%s</span></td>',
                esc_html($row['name']),
                esc_html($row['url'])
            );

            if (!$row['active']) {
                printf(
                    '<td colspan="4" style="color:#646970;">%s</td>',
                    esc_html__('Plugin not active on this site', 'silent-seo-alerts')
                );
            } else {
                printf(
                    '<td><strong style="color:%s;">%d</strong></td>',
                    $row['critical'] > 0 ? '#b32d2e' : 'inherit',
                    (int) $row['critical']
                );
                printf('<td>%d</td>', (int) $row['warning']);
                printf('<td>%d</td>', (int) $row['pages']);
                printf(
                    '<td>%s</td>',
                    $row['impaired']
                        ? '<span style="color:#bd8600;">' . esc_html__('impaired', 'silent-seo-alerts') . '</span>'
                        : esc_html($row['last_run'] ?: __('never', 'silent-seo-alerts'))
                );
            }

            printf(
                '<td><a href="%s">%s</a></td>',
                esc_url($row['admin_url']),
                esc_html__('Open', 'silent-seo-alerts')
            );
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
