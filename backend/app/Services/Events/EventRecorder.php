<?php

namespace SEOChangeMonitor\Services\Events;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\SiteEvent;

/**
 * Records the site changes that could explain an SEO regression, and arms the
 * debounced post-change check.
 */
class EventRecorder
{
    public const TYPE_PLUGIN_UPDATED    = 'plugin_updated';

    public const TYPE_PLUGIN_ACTIVATED  = 'plugin_activated';

    public const TYPE_PLUGIN_DEACTIVATED = 'plugin_deactivated';

    public const TYPE_THEME_UPDATED     = 'theme_updated';

    public const TYPE_THEME_SWITCHED    = 'theme_switched';

    public const TYPE_CORE_UPDATED      = 'core_updated';

    public const TYPE_OPTION_CHANGED    = 'option_changed';

    public const TYPE_POST_SAVED        = 'post_saved';

    /** Debounce window: bulk-updating ten plugins must trigger one check, not ten. */
    public const DEBOUNCE_SECONDS = 90;

    /**
     * @param string $type    one of the TYPE_ constants
     * @param string $subject plugin basename, theme slug, option name, post id…
     * @param array  $details extra context
     */
    public function record($type, $subject, array $details = [])
    {
        $event = SiteEvent::insert(
            [
                'event_type' => $type,
                'subject'    => (string) $subject,
                'details'    => wp_json_encode($details),
            ]
        );

        // A page the user edited themselves is context, not a trigger.
        if ($type !== self::TYPE_POST_SAVED) {
            $this->armPostChangeCheck();
        }

        return $event;
    }

    /**
     * Resets the debounce timer so a burst of updates results in a single check
     * about 90 seconds after the last one.
     */
    public function armPostChangeCheck()
    {
        $hook = Config::withPrefix('post_change_check');

        wp_clear_scheduled_hook($hook);
        wp_schedule_single_event(time() + self::DEBOUNCE_SECONDS, $hook);
    }
}
