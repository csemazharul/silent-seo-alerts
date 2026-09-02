<?php

namespace SEOChangeMonitor\Services\Alerts;

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
 * One email per check run, never one per finding.
 */
class AlertMailer
{
    public function sendRunDigest($runId)
    {
        if (!Settings::get('email_enabled')) {
            return false;
        }

        $run = CheckRun::findOne(['id' => $runId]);
        if (!$run || $run->email_sent) {
            return false; // guard against a double cron fire
        }

        $findings = Digest::sortBySeverity(
            Digest::openFindings($runId, (string) Settings::get('email_threshold'), Finding::SEVERITY_WARNING)
        );
        $resolved = Digest::resolvedCriticals($run);
        $impaired = ImpairedState::details();

        if ($findings === [] && $resolved === [] && !$impaired) {
            return false;
        }

        // Claim the send before doing it, so a concurrent run cannot double-send.
        Db::update('check_runs', ['email_sent' => 1], ['id' => $runId]);

        $sent = wp_mail(
            Settings::emailRecipient(),
            $this->subject($findings, $resolved, $impaired),
            $this->body($findings, $resolved),
            ['Content-Type: text/html; charset=UTF-8']
        );

        if (!$sent) {
            Db::update('check_runs', ['email_sent' => 0], ['id' => $runId]);
        }

        return $sent;
    }


    private function subject($findings, $resolved, $impaired)
    {
        $siteName = get_bloginfo('name');
        $critical = \count(array_filter($findings, static fn ($f) => $f->severity === Finding::SEVERITY_CRITICAL));

        if ($critical > 0) {
            /* translators: 1: site name, 2: number of critical changes */
            return sprintf(
                _n(
                    '[%1$s] SEO Monitor: %2$d critical change detected',
                    '[%1$s] SEO Monitor: %2$d critical changes detected',
                    $critical,
                    'seo-change-monitor'
                ),
                $siteName,
                $critical
            );
        }

        if ($findings === [] && $resolved !== []) {
            /* translators: %s: site name */
            return sprintf(__('[%s] SEO Monitor: all clear', 'seo-change-monitor'), $siteName);
        }

        if ($impaired) {
            /* translators: %s: site name */
            return sprintf(__('[%s] SEO Monitor: monitoring impaired', 'seo-change-monitor'), $siteName);
        }

        /* translators: 1: site name, 2: number of changes */
        return sprintf(
            _n(
                '[%1$s] SEO Monitor: %2$d change detected',
                '[%1$s] SEO Monitor: %2$d changes detected',
                \count($findings),
                'seo-change-monitor'
            ),
            $siteName,
            \count($findings)
        );
    }

    /**
     * Renders the digest view. $findings, $resolved and $impaired are in scope
     * for the template, along with $explanations keyed by finding id.
     */
    private function body($findings, $resolved)
    {
        $explanations = [];
        foreach (array_merge($findings, $resolved) as $finding) {
            $after   = json_decode((string) $finding->after_value, true) ?: [];
            $context = isset($after['context']) && \is_array($after['context']) ? $after['context'] : [];

            $explanations[$finding->id] = ExplanationRegistry::get($finding->change_type, $context);
        }

        $pageLabels = $this->pageLabels(array_merge($findings, $resolved));
        $adminUrl   = admin_url('admin.php?page=' . Config::SLUG . '#/log');

        ob_start();
        include Config::get('BASEDIR') . '/app/Views/emails/digest.php';

        return (string) ob_get_clean();
    }

    private function pageLabels(array $findings)
    {
        $labels = [];
        foreach ($findings as $finding) {
            if (!$finding->target_id || isset($labels[$finding->target_id])) {
                continue;
            }

            $target                       = Target::findOne(['id' => $finding->target_id]);
            $labels[$finding->target_id] = $target ? $target->label : null;
        }

        return $labels;
    }
}
