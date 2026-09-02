<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

class FetchResult
{
    /** @var int|null */
    public $status;

    /** @var array<string,string> */
    public $headers = [];

    /** @var string */
    public $body = '';

    /** @var string|null */
    public $redirectTarget;

    /** @var string|null Transport-level error message (connection refused, timeout, DNS). */
    public $error;

    public function isTransportError()
    {
        return $this->error !== null;
    }

    public function isRedirect()
    {
        return $this->status >= 300 && $this->status < 400 && $this->redirectTarget;
    }

    public function isOffDomainRedirect()
    {
        if (!$this->isRedirect()) {
            return false;
        }

        $homeHost   = wp_parse_url(home_url(), PHP_URL_HOST);
        $targetHost = wp_parse_url($this->redirectTarget, PHP_URL_HOST);

        // A relative Location header stays on-domain.
        return $targetHost !== null && strcasecmp((string) $targetHost, (string) $homeHost) !== 0;
    }
}
