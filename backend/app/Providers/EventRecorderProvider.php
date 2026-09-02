<?php

namespace SEOChangeMonitor\Providers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Services\Events\EventRecorder;

/**
 * Listens for the site changes that most often break SEO output.
 */
class EventRecorderProvider
{
    /** Options worth watching; SEO plugins add their own via the filter below. */
    private const WATCHED_OPTIONS = [
        'blog_public',
        'permalink_structure',
        'page_on_front',
        'show_on_front',
        'blogname',
        'blogdescription',
        'home',
        'siteurl',
    ];

    private const WATCHED_OPTION_PREFIXES = ['wpseo', 'rank_math', 'aioseo', 'seopress'];

    /** @var EventRecorder */
    private $recorder;

    public function __construct()
    {
        $this->recorder = new EventRecorder();

        Hooks::addAction('upgrader_process_complete', [$this, 'onUpgrade'], 10, 2);
        Hooks::addAction('activated_plugin', [$this, 'onPluginActivated'], 10, 1);
        Hooks::addAction('deactivated_plugin', [$this, 'onPluginDeactivated'], 10, 1);
        Hooks::addAction('switch_theme', [$this, 'onThemeSwitched'], 10, 1);
        Hooks::addAction('_core_updated_successfully', [$this, 'onCoreUpdated'], 10, 1);
        Hooks::addAction('updated_option', [$this, 'onOptionUpdated'], 10, 3);
        Hooks::addAction('wp_after_insert_post', [$this, 'onPostSaved'], 10, 3);
    }

    public function onUpgrade($upgrader, $hookExtra)
    {
        if (!\is_array($hookExtra) || !isset($hookExtra['type'])) {
            return;
        }

        if ($hookExtra['type'] === 'plugin') {
            foreach ($this->itemsOf($hookExtra, 'plugins', 'plugin') as $plugin) {
                $this->recorder->record(EventRecorder::TYPE_PLUGIN_UPDATED, $plugin, ['name' => $this->pluginName($plugin)]);
            }
        } elseif ($hookExtra['type'] === 'theme') {
            foreach ($this->itemsOf($hookExtra, 'themes', 'theme') as $theme) {
                $this->recorder->record(EventRecorder::TYPE_THEME_UPDATED, $theme);
            }
        } elseif ($hookExtra['type'] === 'core') {
            $this->recorder->record(EventRecorder::TYPE_CORE_UPDATED, get_bloginfo('version'));
        }
    }

    public function onPluginActivated($plugin)
    {
        if ($this->isSelf($plugin)) {
            return;
        }

        $this->recorder->record(EventRecorder::TYPE_PLUGIN_ACTIVATED, $plugin, ['name' => $this->pluginName($plugin)]);
    }

    public function onPluginDeactivated($plugin)
    {
        if ($this->isSelf($plugin)) {
            return;
        }

        $this->recorder->record(EventRecorder::TYPE_PLUGIN_DEACTIVATED, $plugin, ['name' => $this->pluginName($plugin)]);
    }

    public function onThemeSwitched($newName)
    {
        $this->recorder->record(EventRecorder::TYPE_THEME_SWITCHED, $newName);
    }

    public function onCoreUpdated($version)
    {
        $this->recorder->record(EventRecorder::TYPE_CORE_UPDATED, $version);
    }

    public function onOptionUpdated($option, $oldValue, $newValue)
    {
        if (!$this->isWatchedOption($option)) {
            return;
        }

        $this->recorder->record(
            EventRecorder::TYPE_OPTION_CHANGED,
            $option,
            [
                'from' => is_scalar($oldValue) ? (string) $oldValue : '(complex value)',
                'to'   => is_scalar($newValue) ? (string) $newValue : '(complex value)',
            ]
        );
    }

    /**
     * A post the user saved themselves is the signal that later downgrades
     * cosmetic changes on that page to Info.
     */
    public function onPostSaved($postId, $post, $update)
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (!isset($post->post_status) || $post->post_status !== 'publish') {
            return;
        }

        $this->recorder->record(EventRecorder::TYPE_POST_SAVED, (string) $postId, ['title' => $post->post_title]);
    }

    private function isWatchedOption($option)
    {
        $watched = Hooks::applyFilter(Config::withPrefix('watched_options'), self::WATCHED_OPTIONS);

        if (\in_array($option, (array) $watched, true)) {
            return true;
        }

        foreach (self::WATCHED_OPTION_PREFIXES as $prefix) {
            if (strpos($option, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function itemsOf($hookExtra, $pluralKey, $singularKey)
    {
        if (!empty($hookExtra[$pluralKey]) && \is_array($hookExtra[$pluralKey])) {
            return $hookExtra[$pluralKey];
        }

        return !empty($hookExtra[$singularKey]) ? [$hookExtra[$singularKey]] : [];
    }

    private function pluginName($basename)
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $file = WP_PLUGIN_DIR . '/' . $basename;
        if (!is_readable($file)) {
            return $basename;
        }

        $data = get_plugin_data($file, false, false);

        return !empty($data['Name']) ? $data['Name'] : $basename;
    }

    private function isSelf($plugin)
    {
        return $plugin === Config::get('BASENAME');
    }
}
