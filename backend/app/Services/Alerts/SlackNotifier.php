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
 * Posts a run summary to a Slack incoming webhook.
 *
 * One message per run, mirroring the email: severity, what changed, and the
 * plain-English "why it matters", not a wall of raw diffs.
 */
class SlackNotifier
{
    private const EMOJI = ['critical' => ':rotating_light:', 'warning' => ':warning:', 'info' => ':information_source:'];

    /** Slack truncates long messages; keep the digest scannable. */
    private const MAX_FINDINGS = 8;

    public function sendRunDigest($runId)
    {
        $url = trim((string) Settings::get('slack_webhook_url'));

        if (!Settings::get('slack_enabled') || $url === '') {
            return false;
        }

        $run = CheckRun::findOne(['id' => $runId]);
        if (!$run) {
            return false;
        }

        $findings = Digest::sortBySeverity(
            Digest::openFindings($runId, (string) Settings::get('slack_threshold'), Finding::SEVERITY_CRITICAL)
        );
        $impaired = ImpairedState::details();

        if ($findings === [] && !$impaired) {
            return false;
        }

        return $this->post($url, $this->blocks($findings, $impaired));
    }

    public function sendTest($url)
    {
        return $this->post(
            $url,
            [
                'text' => __('SEO Change Monitor test message', 'seo-change-monitor'),
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                /* translators: %s: site name */
                                __('*SEO Change Monitor* is connected to this channel for *%s*.', 'seo-change-monitor'),
                                get_bloginfo('name')
                            ),
                        ],
                    ],
                ],
            ]
        );
    }

    private function blocks(array $findings, $impaired)
    {
        $siteName = get_bloginfo('name');
        $critical = \count(array_filter($findings, static fn ($f) => $f->severity === Finding::SEVERITY_CRITICAL));

        $headline = $critical > 0
            ? sprintf(
                /* translators: 1: number, 2: site name */
                _n('%1$d critical SEO change on %2$s', '%1$d critical SEO changes on %2$s', $critical, 'seo-change-monitor'),
                $critical,
                $siteName
            )
            : sprintf(
                /* translators: 1: number, 2: site name */
                _n('%1$d SEO change on %2$s', '%1$d SEO changes on %2$s', \count($findings), 'seo-change-monitor'),
                \count($findings),
                $siteName
            );

        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $headline]],
        ];

        if ($impaired) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ':warning: *' . __('Monitoring is impaired', 'seo-change-monitor') . "*\n" . $impaired['reason'],
                ],
            ];
        }

        foreach (\array_slice($findings, 0, self::MAX_FINDINGS) as $finding) {
            $after   = json_decode((string) $finding->after_value, true) ?: [];
            $context = isset($after['context']) && \is_array($after['context']) ? $after['context'] : [];
            $why     = ExplanationRegistry::get($finding->change_type, $context);
            $target  = $finding->target_id ? Target::findOne(['id' => $finding->target_id]) : null;
            $emoji   = isset(self::EMOJI[$finding->severity]) ? self::EMOJI[$finding->severity] : '';

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf(
                        "%s *%s*\n%s\n%s",
                        $emoji,
                        $this->escape($why['what']),
                        $this->escape($target ? $target->label : __('Site-wide', 'seo-change-monitor')),
                        $this->escape($why['why'])
                    ),
                ],
            ];
        }

        if (\count($findings) > self::MAX_FINDINGS) {
            $blocks[] = [
                'type'     => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => sprintf(
                        /* translators: %d: number of additional changes */
                        __('…and %d more in the flight log.', 'seo-change-monitor'),
                        \count($findings) - self::MAX_FINDINGS
                    ),
                ]],
            ];
        }

        $blocks[] = [
            'type'     => 'actions',
            'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => __('Open the flight log', 'seo-change-monitor')],
                'url'  => admin_url('admin.php?page=' . Config::SLUG . '#/log'),
            ]],
        ];

        return ['text' => $headline, 'blocks' => $blocks];
    }

    /** Slack mrkdwn only needs these three escaped. */
    private function escape($text)
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string) $text);
    }

    private function post($url, array $payload)
    {
        $response = wp_remote_post(
            $url,
            [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        return $status >= 200 && $status < 300;
    }

}
