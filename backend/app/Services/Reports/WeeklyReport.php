<?php

namespace SEOChangeMonitor\Services\Reports;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Findings\ExplanationRegistry;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Settings;

/**
 * A weekly summary an agency can forward to a client under its own name.
 *
 * Nothing in the output mentions this plugin unless the user leaves the
 * branding fields empty.
 */
class WeeklyReport
{
    public const PERIOD_DAYS = 7;

    /**
     * Everything the template needs, also used by the on-screen preview.
     */
    public function build($since = null)
    {
        $since = $since ?: gmdate('Y-m-d H:i:s', time() - self::PERIOD_DAYS * DAY_IN_SECONDS);

        $found    = $this->findingsSince($since);
        $resolved = $this->resolvedSince($since);
        $runs     = CheckRun::where('started_at', '>=', $since)->get();
        $runs     = Db::rows($runs);

        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($found as $finding) {
            if (isset($counts[$finding->severity])) {
                ++$counts[$finding->severity];
            }
        }

        $openCritical = (int) Finding::where('status', Finding::STATUS_OPEN)
            ->where('severity', Finding::SEVERITY_CRITICAL)
            ->count();

        return [
            'brand'          => $this->brand(),
            'site_name'      => get_bloginfo('name'),
            'site_url'       => home_url('/'),
            'period_start'   => $since,
            'period_end'     => gmdate('Y-m-d H:i:s'),
            'checks_run'     => \count($runs),
            'pages_watched'  => (int) Target::where('is_active', 1)->where('type', Target::TYPE_PAGE)->count(),
            'counts'         => $counts,
            'open_critical'  => $openCritical,
            'impaired'       => ImpairedState::details(),
            'findings'       => $this->present($found),
            'resolved'       => $this->present($resolved),
            'all_quiet'      => $found === [] && $resolved === [],
        ];
    }

    /**
     * @return bool true when the mail was handed to WordPress
     */
    public function send($since = null)
    {
        $recipients = self::recipients();
        if ($recipients === []) {
            return false;
        }

        $data = $this->build($since);

        return wp_mail(
            $recipients,
            $this->subject($data),
            $this->render($data),
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    public function render(array $data)
    {
        ob_start();
        include Config::get('BASEDIR') . '/app/Views/emails/weekly-report.php';

        return (string) ob_get_clean();
    }

    /** @return string[] */
    public static function recipients()
    {
        $raw = Settings::get('report_recipients');
        $raw = \is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);

        return array_values(array_filter(array_map(trim(...), (array) $raw), is_email(...)));
    }

    private function subject(array $data)
    {
        $brand = $data['brand']['name'] !== ''
            ? $data['brand']['name']
            : __('SEO Change Monitor', 'seo-change-monitor');

        if ($data['counts']['critical'] > 0) {
            return sprintf(
                /* translators: 1: brand, 2: site name, 3: count */
                __('%1$s: weekly SEO report for %2$s (%3$d critical)', 'seo-change-monitor'),
                $brand,
                $data['site_name'],
                $data['counts']['critical']
            );
        }

        return sprintf(
            /* translators: 1: brand, 2: site name */
            __('%1$s: weekly SEO report for %2$s', 'seo-change-monitor'),
            $brand,
            $data['site_name']
        );
    }

    private function brand()
    {
        return [
            'name'     => trim((string) Settings::get('report_brand_name')),
            'color'    => trim((string) Settings::get('report_brand_color')) ?: '#3b5bdb',
            'logo_url' => trim((string) Settings::get('report_logo_url')),
            'footer'   => trim((string) Settings::get('report_footer')),
        ];
    }

    private function findingsSince($since)
    {
        $rows = Finding::where('created_at', '>=', $since)->orderBy('id')->desc()->get();

        return Db::rows($rows);
    }

    private function resolvedSince($since)
    {
        $rows = Finding::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->orderBy('id')->desc()
            ->get();

        return Db::rows($rows);
    }

    private function present(array $findings)
    {
        $labels = [];
        $out    = [];

        foreach ($findings as $finding) {
            if ($finding->target_id && !isset($labels[$finding->target_id])) {
                $target                      = Target::findOne(['id' => $finding->target_id]);
                $labels[$finding->target_id] = $target ? $target->label : null;
            }

            $after   = json_decode((string) $finding->after_value, true) ?: [];
            $context = isset($after['context']) && \is_array($after['context']) ? $after['context'] : [];

            $out[] = [
                'id'          => (int) $finding->id,
                'severity'    => $finding->severity,
                'status'      => $finding->status,
                'change_type' => $finding->change_type,
                'page'        => $finding->target_id ? $labels[$finding->target_id] : null,
                'explanation' => ExplanationRegistry::get($finding->change_type, $context),
                'created_at'  => $finding->created_at,
            ];
        }

        return $out;
    }
}
