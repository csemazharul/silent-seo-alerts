<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!defined('ABSPATH')) {
    exit;
}

use DOMDocument;
use DOMXPath;

/**
 * Extracts the monitored SEO fields from a rendered HTML page.
 * Must never fatal on malformed HTML.
 */
class SnapshotExtractor
{
    /**
     * @return array The 11 monitored fields (plus fetch metadata carried on the snapshot row).
     */
    public function extract(FetchResult $result)
    {
        $fields = [
            'title'            => null,
            'meta_description' => null,
            'meta_robots'      => null,
            'canonical'        => null,
            'schema_types'     => [],
            'og'               => [],
            'twitter'          => [],
            'h1_first'         => null,
            'h1_count'         => 0,
            'http_status'      => $result->status,
            'redirect_target'  => $result->redirectTarget,
            'word_count'       => 0,
        ];

        if ($result->body === '' || $result->isTransportError()) {
            return $fields;
        }

        $dom = $this->loadDom($result->body);
        if (!$dom) {
            // Last-resort regex extraction so a completely unparseable page still records a title.
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $result->body, $m)) {
                $fields['title'] = $this->cleanText($m[1]);
            }

            return $fields;
        }

        $xpath = new DOMXPath($dom);

        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $fields['title'] = $this->cleanText($titleNode->textContent);
        }

        foreach ($xpath->query('//meta[@name or @property]') as $meta) {
            $name    = strtolower($meta->getAttribute('name') ?: $meta->getAttribute('property'));
            $content = trim($meta->getAttribute('content'));

            if ($name === 'description') {
                $fields['meta_description'] = $content;
            } elseif ($name === 'robots') {
                $fields['meta_robots'] = strtolower($content);
            } elseif (str_starts_with($name, 'og:')) {
                $fields['og'][$name] = $content;
            } elseif (str_starts_with($name, 'twitter:')) {
                $fields['twitter'][$name] = $content;
            }
        }

        $canonical = $xpath->query('//link[translate(@rel,"CANOICL","canoicl")="canonical"]')->item(0);
        if ($canonical) {
            $fields['canonical'] = trim($canonical->getAttribute('href'));
        }

        $h1s                = $xpath->query('//h1');
        $fields['h1_count'] = $h1s->length;
        if ($h1s->length > 0) {
            $fields['h1_first'] = $this->cleanText($h1s->item(0)->textContent);
        }

        $fields['schema_types'] = $this->schemaTypes($xpath);
        $fields['word_count']   = $this->wordCount($xpath);

        ksort($fields['og']);
        ksort($fields['twitter']);

        return $fields;
    }

    /**
     * Collect every @type from JSON-LD blocks, recursing @graph and nested structures.
     * Invalid JSON blocks are recorded as the sentinel value below so the differ can flag them.
     *
     * @return string[] unique, sorted
     */
    private function schemaTypes(DOMXPath $xpath)
    {
        $types = [];

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $decoded = json_decode(trim($script->textContent), true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $types[] = '(invalid JSON-LD)';

                continue;
            }

            $this->collectTypes($decoded, $types);
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }

    private function collectTypes($node, array &$types)
    {
        if (!\is_array($node)) {
            return;
        }

        if (isset($node['@type'])) {
            foreach ((array) $node['@type'] as $type) {
                if (\is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        foreach ($node as $value) {
            if (\is_array($value)) {
                $this->collectTypes($value, $types);
            }
        }
    }

    private function wordCount(DOMXPath $xpath)
    {
        $body = $xpath->query('//body')->item(0);
        if (!$body) {
            return 0;
        }

        // Drop non-content nodes before counting.
        foreach ($xpath->query('.//script | .//style | .//noscript | .//template', $body) as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $text = $body->textContent;
        preg_match_all('/[\p{L}\p{N}\']+/u', $text, $matches);

        return \count($matches[0]);
    }

    /**
     * @return DOMDocument|null
     */
    private function loadDom($html)
    {
        if (!class_exists(DOMDocument::class)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom      = new DOMDocument();

        // The XML prolog forces UTF-8 interpretation; without it DOMDocument assumes ISO-8859-1.
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    private function cleanText($text)
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }
}
