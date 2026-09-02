<?php

namespace SEOChangeMonitor\Services\Ai;

if (!\defined('ABSPATH')) {
    exit;
}

use Exception;

/**
 * Any AI failure. Always caught, so the template explanation is shown instead and
 * a bad key or an outage can never break monitoring.
 */
class AiException extends Exception
{
}
