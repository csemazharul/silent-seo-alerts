<?php

namespace SEOChangeMonitor\Services\Alerts;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Findings\ExplanationRegistry;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Settings;

/**
 * Posts a run summary to the user's own endpoints.
 *
 * These are the only outbound requests the plugin ever makes, they go only to
 * URLs the user typed in themselves, and they carry no page content, just the
 * change record, same as the email.
 */
class WebhookNotifier
{
    public const TIMEOUT = 10;

    /**
     * @return array{sent: int, failed: int}
     */
    public function sendRunDigest($runId)
    {
        $urls = Settings::webhookUrls();

        if (!Settings::get('webhook_enabled') || $urls === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $run = CheckRun::findOne(['id' => $runId]);
        if (!$run) {
            return ['sent' => 0, 'failed' => 0];
        }

        // Deliberately unsorted: consumers parse this, they do not read it.
        $findings = Digest::openFindings($runId, (string) Settings::get('webhook_threshold'), Finding::SEVERITY_CRITICAL);
        $resolved = Digest::resolvedCriticals($run);
        $impaired = ImpairedState::details();

        if ($findings === [] && $resolved === [] && !$impaired) {
            return ['sent' => 0, 'failed' => 0];
        }

        $payload = $this->payload($run, $findings, $resolved, $impaired);

        return $this->dispatch($urls, $payload);
    }

    /**
     * Sends a sample payload so the user can confirm their endpoint works.
     */
    public function sendTest($url)
    {
        return $this->post(
            $url,
            [
                'event'     => 'test',
                'site'      => home_url('/'),
                'sent_at'   => gmdate('c'),
                'message'   => __('Test delivery from SEO Change Monitor.', 'seo-change-monitor'),
                'findings'  => [],
            ]
        );
    }

    private function dispatch(array $urls, array $payload)
    {
        $sent   = 0;
        $failed = 0;

        foreach ($urls as $url) {
            if ($this->post($url, $payload)) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return bool true on a 2xx response
     */
    private function post($url, array $payload)
    {
        $body    = wp_json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent'   => 'SEOChangeMonitor/' . Config::VERSION . '; ' . home_url('/'),
        ];

        // Optional shared secret lets the receiver verify the request is ours.
        $secret = (string) Settings::get('webhook_secret');
        if ($secret !== '') {
            $headers['X-SEO-Monitor-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        $response = wp_remote_post(
            $url,
            [
                'timeout'     => self::TIMEOUT,
                'headers'     => $headers,
                'body'        => $body,
                'data_format' => 'body',
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        return $status >= 200 && $status < 300;
    }

    private function payload($run, array $findings, array $resolved, $impaired)
    {
        return [
            'event'    => 'check_complete',
            'site'     => home_url('/'),
            'sent_at'  => gmdate('c'),
            'run'      => [
                'id'          => (int) $run->id,
                'trigger'     => $run->trigger_type,
                'status'      => $run->status,
                'finished_at' => $run->finished_at,
                'counts'      => [
                    'critical' => (int) $run->critical_count,
                    'warning'  => (int) $run->warning_count,
                    'info'     => (int) $run->info_count,
                ],
            ],
            'monitoring' => $impaired ? 'impaired' : 'ok',
            'impaired'   => $impaired ?: null,
            'findings'   => array_map([$this, 'presentFinding'], $findings),
            'resolved'   => array_map([$this, 'presentFinding'], $resolved),
        ];
    }

    private function presentFinding($finding)
    {
        $before = json_decode((string) $finding->before_value, true) ?: [];
        $after  = json_decode((string) $finding->after_value, true) ?: [];
        $target = $finding->target_id ? Target::findOne(['id' => $finding->target_id]) : null;
        $ctx    = isset($after['context']) && \is_array($after['context']) ? $after['context'] : [];

        return [
            'id'                => (int) $finding->id,
            'change_type'       => $finding->change_type,
            'severity'          => $finding->severity,
            'status'            => $finding->status,
            'page'              => $target ? ['label' => $target->label, 'url' => $target->url] : null,
            'before'            => isset($before['value']) ? $before['value'] : null,
            'after'             => isset($after['value']) ? $after['value'] : null,
            'is_expected'       => (bool) $finding->is_expected,
            'attributed_events' => json_decode((string) $finding->attributed_events, true) ?: [],
            'explanation'       => ExplanationRegistry::get($finding->change_type, $ctx),
            'detected_at'       => $finding->created_at,
        ];
    }

}
