<?php
namespace SEOChangeMonitor;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

final class Dotenv
{
    public static function load($path = '')
    {
        if (! file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            $position = strpos($line, '#');

            if ($position !== false) {
                $line = substr($line, 0, $position);
            }

            if (empty($line)) {
                continue;
            }

            [$name, $value] = explode('=', trim($line), 2);

            $name = Config::VAR_PREFIX . trim($name);

            $value = trim($value);

            if (is_numeric($value)) {
                $value += 0; // Converts to int or float
            } elseif (strtolower($value) === 'true' || strtolower($value) === 'false') {
                $value = strtolower($value) === 'true'; // Converts to boolean
            }

            if (! \array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }
}
