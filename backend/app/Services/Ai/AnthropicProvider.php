<?php

namespace SEOChangeMonitor\Services\Ai;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Anthropic Messages API.
 *
 * WordPress requires plugins to use the WP HTTP API rather than bundling an
 * SDK, so this is a direct call to POST /v1/messages.
 */
class AnthropicProvider implements AiProvider
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const API_VERSION = '2023-06-01';

    /** @var string */
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = (string) $apiKey;
    }

    public function defaultModel()
    {
        // Explaining a field diff is not hard reasoning; the small model is the
        // right tool and costs a fraction of a frontier one.
        return 'claude-haiku-4-5';
    }

    public function models()
    {
        return [
            'claude-haiku-4-5' => 'Claude Haiku 4.5 (fastest, cheapest)',
            'claude-sonnet-5'  => 'Claude Sonnet 5 (more detailed, pricier)',
        ];
    }

    public function pricing($model)
    {
        $rates = [
            'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
            'claude-sonnet-5'  => ['input' => 2.00, 'output' => 10.00],
        ];

        return isset($rates[$model]) ? $rates[$model] : $rates['claude-haiku-4-5'];
    }

    public function complete($system, $user, $model)
    {
        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => 30,
                'headers' => [
                    'content-type'      => 'application/json',
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                ],
                'body' => wp_json_encode(
                    [
                        'model'      => $model,
                        'max_tokens' => 400,
                        'system'     => $system,
                        'messages'   => [['role' => 'user', 'content' => $user]],
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            throw new AiException($response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status === 401 || $status === 403) {
            throw new AiException(__('Anthropic rejected the API key.', 'seo-change-monitor'));
        }

        if ($status === 429) {
            throw new AiException(__('Anthropic rate limit reached. Try again shortly.', 'seo-change-monitor'));
        }

        if ($status < 200 || $status >= 300) {
            $message = isset($body['error']['message'])
                ? $body['error']['message']
                : sprintf(__('Anthropic returned HTTP %d.', 'seo-change-monitor'), $status);

            throw new AiException($message);
        }

        $text = '';
        foreach (isset($body['content']) ? (array) $body['content'] : [] as $block) {
            if (isset($block['type'], $block['text']) && $block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        if (trim($text) === '') {
            throw new AiException(__('Anthropic returned an empty response.', 'seo-change-monitor'));
        }

        return [
            'text'          => trim($text),
            'input_tokens'  => isset($body['usage']['input_tokens']) ? (int) $body['usage']['input_tokens'] : 0,
            'output_tokens' => isset($body['usage']['output_tokens']) ? (int) $body['usage']['output_tokens'] : 0,
        ];
    }
}
