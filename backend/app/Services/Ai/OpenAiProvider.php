<?php

namespace SEOChangeMonitor\Services\Ai;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Chat Completions API.
 *
 * Model list and prices are editable in settings because vendors add models
 * faster than a plugin ships; the cost shown is always labelled an estimate.
 */
class OpenAiProvider implements AiProvider
{
    public const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** @var string */
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = (string) $apiKey;
    }

    public function defaultModel()
    {
        return 'gpt-4o-mini';
    }

    public function models()
    {
        return [
            'gpt-4o-mini' => 'GPT-4o mini (fastest, cheapest)',
            'gpt-4o'      => 'GPT-4o (more detailed, pricier)',
        ];
    }

    public function pricing($model)
    {
        $rates = [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o'      => ['input' => 2.50, 'output' => 10.00],
        ];

        return $rates[$model] ?? $rates['gpt-4o-mini'];
    }

    public function complete($system, $user, $model)
    {
        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => 30,
                'headers' => [
                    'content-type'  => 'application/json',
                    'authorization' => 'Bearer ' . $this->apiKey,
                ],
                'body' => wp_json_encode(
                    [
                        'model'      => $model,
                        'max_tokens' => 400,
                        'messages'   => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $user],
                        ],
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
            throw new AiException(__('OpenAI rejected the API key.', 'seo-change-monitor'));
        }

        if ($status === 429) {
            throw new AiException(__('OpenAI rate limit reached. Try again shortly.', 'seo-change-monitor'));
        }

        if ($status < 200 || $status >= 300) {
            $message = $body['error']['message'] ?? sprintf(__('OpenAI returned HTTP %d.', 'seo-change-monitor'), $status);

            throw new AiException($message);
        }

        $text = isset($body['choices'][0]['message']['content'])
            ? (string) $body['choices'][0]['message']['content']
            : '';

        if (trim($text) === '') {
            throw new AiException(__('OpenAI returned an empty response.', 'seo-change-monitor'));
        }

        return [
            'text'          => trim($text),
            'input_tokens'  => (int) ($body['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
        ];
    }
}
