<?php

namespace SEOChangeMonitor\Services\Ai;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Settings;

/**
 * Optional AI interpretation of a single change.
 *
 * Rules this class exists to enforce:
 *   - on demand only, never during a cron run;
 *   - the same change is never paid for twice (cached on the finding);
 *   - only the change record is sent, never page content;
 *   - the factual log is never overwritten, since AI text lives in `meta`;
 *   - any failure falls back to the written template.
 */
class AiExplainer
{
    /** Kept deliberately tight: this is interpretation, not authorship. */
    private const SYSTEM = <<<'TXT'
You explain a single SEO change to a non-technical website owner.

You are given only a change record: which field changed, its before and after
values, and any site events that happened just before. You do not have the page
content and must not guess at it.

Write 2 to 3 short sentences, plain English, no jargon, no headings, no lists.
Say what this specific change likely means for this site and what the owner
should look at first. If the site events make a cause plausible, say so as a
possibility, never as certainty. If nothing in the record supports a cause, say
that plainly rather than inventing one.
TXT;

    public function isConfigured()
    {
        return Settings::get('ai_enabled') && trim((string) Settings::get('ai_api_key')) !== '';
    }

    /**
     * @param Finding $finding
     * @param bool    $refresh ignore the cached answer and buy a new one
     *
     * @return array{text: string, provider: string, model: string, cached: bool, generated_at: string}
     *
     * @throws AiException
     */
    public function explain($finding, $refresh = false)
    {
        if (!$this->isConfigured()) {
            throw new AiException(__('AI explanations are turned off, or no API key is set.', 'seo-change-monitor'));
        }

        // Re-read rather than trusting the caller's copy: another click (or an
        // earlier one in this request) may already have paid for an answer.
        $fresh   = Finding::findOne(['id' => $finding->id]) ?: $finding;
        $finding = $fresh;

        $meta = json_decode((string) $finding->meta, true);
        $meta = \is_array($meta) ? $meta : [];

        if (!$refresh && isset($meta['ai']['text']) && $meta['ai']['text'] !== '') {
            $cached          = $meta['ai'];
            $cached['cached'] = true;

            return $cached;
        }

        $usage = self::usage();
        $cap   = (float) Settings::get('ai_monthly_cap');
        if ($cap > 0 && $usage['cost'] >= $cap) {
            throw new AiException(
                sprintf(
                    /* translators: %s: formatted dollar amount */
                    __('This month\'s AI spending cap of $%s has been reached.', 'seo-change-monitor'),
                    number_format($cap, 2)
                )
            );
        }

        $provider = self::provider();
        $model    = $this->model($provider);
        $result   = $provider->complete(self::SYSTEM, $this->changeRecord($finding), $model);

        $cost = $this->cost($provider, $model, $result['input_tokens'], $result['output_tokens']);
        $this->recordUsage($cost);

        $entry = [
            'text'         => $result['text'],
            'provider'     => (string) Settings::get('ai_provider'),
            'model'        => $model,
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'cost'         => round($cost, 6),
        ];

        // Written to meta only, so before_value/after_value stay untouched and the
        // permanent log remains purely factual.
        $meta['ai'] = $entry;
        Db::update('findings', ['meta' => wp_json_encode($meta)], ['id' => $finding->id]);

        $entry['cached'] = false;

        return $entry;
    }

    /**
     * The only thing that ever leaves the site: field name, before/after, and
     * the preceding site events. No page content, no URLs beyond the label.
     */
    private function changeRecord($finding)
    {
        $before = json_decode((string) $finding->before_value, true) ?: [];
        $after  = json_decode((string) $finding->after_value, true) ?: [];
        $events = json_decode((string) $finding->attributed_events, true) ?: [];
        $target = $finding->target_id ? Target::findOne(['id' => $finding->target_id]) : null;

        $scalar = static function ($value) {
            if ($value === null || $value === '') {
                return '(empty)';
            }

            return is_scalar($value) ? (string) $value : wp_json_encode($value);
        };

        $lines = [
            'Change type: ' . $finding->change_type,
            'Severity: ' . $finding->severity,
            'Applies to: ' . ($target ? $target->label : 'the whole site'),
            'Value before: ' . $scalar($before['value'] ?? null),
            'Value after: ' . $scalar($after['value'] ?? null),
            'Changed by a site edit the owner made: ' . ($finding->is_expected ? 'yes' : 'not that we can tell'),
        ];

        if ($events === []) {
            $lines[] = 'Site events just before the change: none recorded.';
        } else {
            $lines[] = 'Site events just before the change:';
            foreach ($events as $event) {
                $lines[] = sprintf(
                    '- %s: %s (%s)',
                    str_replace('_', ' ', (string) $event['type']),
                    (string) $event['subject'],
                    (string) $event['at']
                );
            }
        }

        return implode("\n", $lines);
    }

    private function model(AiProvider $provider)
    {
        $chosen = trim((string) Settings::get('ai_model'));

        return $chosen !== '' ? $chosen : $provider->defaultModel();
    }

    private function cost(AiProvider $provider, $model, $inputTokens, $outputTokens)
    {
        $rates = $provider->pricing($model);

        return ($inputTokens / 1000000) * $rates['input'] + ($outputTokens / 1000000) * $rates['output'];
    }

    /** @return AiProvider */
    public static function provider()
    {
        $key  = (string) Settings::get('ai_api_key');
        $name = (string) Settings::get('ai_provider');

        return $name === 'openai' ? new OpenAiProvider($key) : new AnthropicProvider($key);
    }

    /**
     * Running count for the current calendar month.
     *
     * @return array{month: string, calls: int, cost: float}
     */
    public static function usage()
    {
        $month  = gmdate('Y-m');
        $stored = Config::getOption('ai_usage');

        if (!\is_array($stored) || !isset($stored['month']) || $stored['month'] !== $month) {
            return ['month' => $month, 'calls' => 0, 'cost' => 0.0];
        }

        return [
            'month' => $month,
            'calls' => (int) $stored['calls'],
            'cost'  => (float) $stored['cost'],
        ];
    }

    private function recordUsage($cost)
    {
        $usage = self::usage();

        Config::updateOption(
            'ai_usage',
            [
                'month' => $usage['month'],
                'calls' => $usage['calls'] + 1,
                'cost'  => round($usage['cost'] + $cost, 6),
            ]
        );
    }
}
