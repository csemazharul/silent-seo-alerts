<?php

namespace SEOChangeMonitor\Services\Ai;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * One BYOK provider. Implementations do the HTTP call and nothing else. The
 * prompt, caching, cost accounting and fallback all live in AiExplainer.
 */
interface AiProvider
{
    /**
     * @param string $system system instruction
     * @param string $user   the change record, already stripped of page content
     *
     * @return array{text: string, input_tokens: int, output_tokens: int}
     *
     * @throws AiException on any transport, auth, or rate-limit failure
     */
    public function complete($system, $user, $model);

    /** Default model when the user has not chosen one. */
    public function defaultModel();

    /** Models offered in the settings dropdown. */
    public function models();

    /**
     * USD per million tokens: ['input' => float, 'output' => float].
     * Used only for the estimated running cost shown to the user.
     */
    public function pricing($model);
}
