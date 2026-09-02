<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

class PageFetcher
{
    public function fetch($url)
    {
        $result = new FetchResult();

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 15,
                'redirection' => 0, // record the redirect target, never follow it
                'user-agent'  => 'SEOChangeMonitor/' . Config::VERSION . '; ' . home_url('/'),
                'sslverify'   => apply_filters('https_local_ssl_verify', false),
            ]
        );

        if (is_wp_error($response)) {
            $result->error = $response->get_error_message();

            return $result;
        }

        $result->status = (int) wp_remote_retrieve_response_code($response);
        $result->body   = (string) wp_remote_retrieve_body($response);

        $headers = wp_remote_retrieve_headers($response);
        if (\is_object($headers) && method_exists($headers, 'getAll')) {
            $result->headers = $headers->getAll();
        } elseif (\is_array($headers)) {
            $result->headers = $headers;
        }

        $location = wp_remote_retrieve_header($response, 'location');
        if ($location) {
            $result->redirectTarget = \is_array($location) ? end($location) : $location;
        }

        return $result;
    }

    /**
     * Loopback probe: can this site fetch itself at all?
     */
    public function preflight()
    {
        $result = $this->fetch(home_url('/'));

        return !$result->isTransportError();
    }
}
