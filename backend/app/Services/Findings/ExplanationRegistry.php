<?php

namespace SEOChangeMonitor\Services\Findings;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Services\CheckEngine\ChangeTypes;

/**
 * Pre-written plain-language explanations for every change type.
 * Each answers three questions: what happened, why it matters, what to check.
 * These render for every finding, with no API key and no external calls.
 */
class ExplanationRegistry
{
    /**
     * @param string $type    a ChangeTypes constant
     * @param array  $context optional context from the Change (ratio, removed types, bots…)
     *
     * @return array{what: string, why: string, check: string}
     */
    public static function get($type, array $context = [])
    {
        $all = self::templates($context);

        return $all[$type] ?? [
            'what'  => __('Something monitored on this page changed.', 'seo-change-monitor'),
            'why'   => __('Any unplanned change to SEO output is worth a look.', 'seo-change-monitor'),
            'check' => __('Compare the before and after values above.', 'seo-change-monitor'),
        ];
    }

    private static function templates(array $ctx)
    {
        $ratioPct    = isset($ctx['ratio']) ? round($ctx['ratio'] * 100) : null;
        $removedList = isset($ctx['removed']) ? implode(', ', (array) $ctx['removed']) : '';
        $addedList   = isset($ctx['added']) ? implode(', ', (array) $ctx['added']) : '';
        $botsList    = isset($ctx['bots']) ? implode(', ', (array) $ctx['bots']) : '';

        return [
            ChangeTypes::TITLE_CHANGED => [
                'what'  => __('The page title changed. That is the headline shown in Google results and browser tabs.', 'seo-change-monitor'),
                'why'   => __('Titles strongly influence rankings and whether people click. An unplanned rewrite can drop your position for keywords the old title earned.', 'seo-change-monitor'),
                'check' => __('If you did not edit this page yourself, check your SEO plugin\'s title template and any recently updated plugin or theme.', 'seo-change-monitor'),
            ],
            ChangeTypes::TITLE_REMOVED => [
                'what'  => __('The page no longer has a title tag at all.', 'seo-change-monitor'),
                'why'   => __('Google will invent its own headline for the page and rankings usually suffer. A missing title is almost always a template bug, not a choice.', 'seo-change-monitor'),
                'check' => __('Check your theme header template and SEO plugin. One of them stopped printing the title, usually after an update.', 'seo-change-monitor'),
            ],
            ChangeTypes::META_DESCRIPTION_CHANGED => [
                'what'  => __('The meta description changed. That is the snippet of text under your headline in search results.', 'seo-change-monitor'),
                'why'   => __('Descriptions do not affect rankings directly, but they decide whether people click your result or a competitor\'s.', 'seo-change-monitor'),
                'check' => __('If the new text looks wrong or generic, check the description field in your SEO plugin for this page.', 'seo-change-monitor'),
            ],
            ChangeTypes::META_DESCRIPTION_REMOVED => [
                'what'  => __('The page\'s meta description was removed.', 'seo-change-monitor'),
                'why'   => __('Google now writes its own snippet from random page text, which usually reads worse and gets fewer clicks.', 'seo-change-monitor'),
                'check' => __('Check the description field in your SEO plugin for this page, and whether a recent plugin update cleared it.', 'seo-change-monitor'),
            ],
            ChangeTypes::META_ROBOTS_CHANGED => [
                'what'  => __('The instructions this page gives to search engine crawlers (the robots meta tag) changed.', 'seo-change-monitor'),
                'why'   => __('These instructions control how search engines index and display the page. The change here does not block indexing, but it alters crawler behaviour.', 'seo-change-monitor'),
                'check' => __('Compare the before and after values. If you did not intend this, check your SEO plugin\'s advanced/robots settings for this page.', 'seo-change-monitor'),
            ],
            ChangeTypes::NOINDEX_ADDED => [
                'what'  => __('This page now tells search engines not to index it ("noindex").', 'seo-change-monitor'),
                'why'   => __('Google will drop it from search results, usually within days. All traffic this page earns from search will disappear.', 'seo-change-monitor'),
                'check' => __('Open this page\'s settings in your SEO plugin and look for an indexing or "allow search engines" toggle. If you did not change it, a recently updated or activated plugin likely did.', 'seo-change-monitor'),
            ],
            ChangeTypes::NOINDEX_REMOVED => [
                'what'  => __('This page stopped telling search engines to skip it, so it is now indexable again.', 'seo-change-monitor'),
                'why'   => __('If the page was meant to be hidden (a thank-you or checkout page, say), it can now appear in Google. If it was noindexed by mistake, this is good news.', 'seo-change-monitor'),
                'check' => __('Confirm this page should be visible in search results.', 'seo-change-monitor'),
            ],
            ChangeTypes::NOFOLLOW_ADDED => [
                'what'  => __('This page now tells search engines not to follow any of its links ("nofollow").', 'seo-change-monitor'),
                'why'   => __('Pages it links to lose the ranking credit those links passed. Site-wide, this can quietly weaken every page\'s authority.', 'seo-change-monitor'),
                'check' => __('Check this page\'s advanced robots settings in your SEO plugin. A page-wide nofollow is rarely intentional.', 'seo-change-monitor'),
            ],
            ChangeTypes::CANONICAL_CHANGED => [
                'what'  => __('The page\'s canonical URL changed. That is its declared "official address" for search engines.', 'seo-change-monitor'),
                'why'   => __('Google consolidates ranking signals onto the canonical URL. Pointing it at the wrong address splits or misdirects that credit.', 'seo-change-monitor'),
                'check' => __('Confirm the new canonical is really the address you want ranked. Check your SEO plugin\'s canonical field and your permalink settings.', 'seo-change-monitor'),
            ],
            ChangeTypes::CANONICAL_REMOVED => [
                'what'  => __('The page\'s canonical URL tag was removed.', 'seo-change-monitor'),
                'why'   => __('Without it, search engines guess which of several similar URLs is the real one. With URL parameters or pagination they can guess wrong and split your ranking credit.', 'seo-change-monitor'),
                'check' => __('SEO plugins print this tag automatically, so its disappearance suggests the plugin was deactivated or misconfigured. Check it first.', 'seo-change-monitor'),
            ],
            ChangeTypes::CANONICAL_OFFSITE => [
                'what'  => __('This page now tells search engines the "real" version lives on a different website.', 'seo-change-monitor'),
                'why'   => __('Google will likely drop this page from its index and credit the other site instead. This is one of the most damaging silent SEO failures.', 'seo-change-monitor'),
                'check' => __('Check your SEO plugin\'s canonical setting for this page, and any recently activated plugin that manages canonicals. If you did not set this, treat it urgently.', 'seo-change-monitor'),
            ],
            ChangeTypes::SCHEMA_TYPE_REMOVED => [
                'what'  => $removedList !== ''
                    ? sprintf(__('Structured data disappeared from this page. Missing type(s): %s.', 'seo-change-monitor'), $removedList)
                    : __('A type of structured data (schema markup) disappeared from this page.', 'seo-change-monitor'),
                'why'   => __('Structured data powers rich results such as star ratings, FAQs, product info and recipe cards. Losing it means losing those enhanced listings and the extra clicks they bring.', 'seo-change-monitor'),
                'check' => __('Schema usually comes from your SEO plugin or theme. Check whether a recent update changed its schema settings, and test the page with Google\'s Rich Results Test.', 'seo-change-monitor'),
            ],
            ChangeTypes::SCHEMA_TYPE_ADDED => [
                'what'  => $addedList !== ''
                    ? sprintf(__('New structured data appeared on this page: %s.', 'seo-change-monitor'), $addedList)
                    : __('New structured data (schema markup) appeared on this page.', 'seo-change-monitor'),
                'why'   => __('Usually harmless or positive, since new markup can enable rich results. Worth knowing in case a plugin is adding markup you did not ask for.', 'seo-change-monitor'),
                'check' => __('If you recently enabled a feature or plugin, this is probably it. Otherwise, confirm the new markup describes the page accurately.', 'seo-change-monitor'),
            ],
            ChangeTypes::SCHEMA_INVALID_JSON => [
                'what'  => __('One of the page\'s structured data blocks is now broken (invalid JSON).', 'seo-change-monitor'),
                'why'   => __('Search engines ignore broken structured data entirely, so any rich results that block powered will disappear.', 'seo-change-monitor'),
                'check' => __('A plugin or theme is printing malformed markup, often after an update. Test the page with Google\'s Rich Results Test to see which block fails.', 'seo-change-monitor'),
            ],
            ChangeTypes::OG_CHANGED => [
                'what'  => __('The page\'s Open Graph tags changed. These control how it looks when shared on Facebook, LinkedIn, Slack and most apps.', 'seo-change-monitor'),
                'why'   => __('A broken share preview (wrong image, missing title) measurably reduces clicks on shared links.', 'seo-change-monitor'),
                'check' => __('Paste the page URL into a share-preview debugger (e.g. opengraph.xyz) and confirm it still looks right.', 'seo-change-monitor'),
            ],
            ChangeTypes::TWITTER_CHANGED => [
                'what'  => __('The page\'s Twitter/X card tags changed. These control how it previews when shared there.', 'seo-change-monitor'),
                'why'   => __('Minor by itself, but an unexpected change hints that whatever generates your social tags was modified.', 'seo-change-monitor'),
                'check' => __('Compare the before and after values; if other social tags changed too, check your SEO plugin\'s social settings.', 'seo-change-monitor'),
            ],
            ChangeTypes::H1_CHANGED => [
                'what'  => __('The page\'s main on-page headline (H1) changed.', 'seo-change-monitor'),
                'why'   => __('The H1 tells search engines and readers what the page is about. An unplanned change can weaken relevance for the keywords it targeted.', 'seo-change-monitor'),
                'check' => __('If you did not edit this page, check for theme or page builder updates. They sometimes restructure heading markup.', 'seo-change-monitor'),
            ],
            ChangeTypes::H1_COUNT_CHANGED => [
                'what'  => __('The number of H1 headlines on the page changed.', 'seo-change-monitor'),
                'why'   => __('A page should normally have exactly one H1. Extra ones dilute the topic signal; this usually indicates a template change.', 'seo-change-monitor'),
                'check' => __('Check for recent theme or page builder updates that changed the page structure.', 'seo-change-monitor'),
            ],
            ChangeTypes::H1_REMOVED => [
                'what'  => __('The page no longer has any H1 headline.', 'seo-change-monitor'),
                'why'   => __('The page loses its clearest topic signal to search engines, and this usually means a template broke.', 'seo-change-monitor'),
                'check' => __('Check your theme and page builder. An update likely changed how headings are rendered.', 'seo-change-monitor'),
            ],
            ChangeTypes::HTTP_STATUS_ERROR => [
                'what'  => __('The page stopped loading and now returns an error instead of content.', 'seo-change-monitor'),
                'why'   => __('Visitors see an error page, and if it persists Google will remove the page from search results.', 'seo-change-monitor'),
                'check' => __('Open the page in a private browser window. A 404 means the URL or permalinks changed; a 500 means something on the server is failing, often after a plugin or theme update.', 'seo-change-monitor'),
            ],
            ChangeTypes::REDIRECT_ADDED => [
                'what'  => __('The page now redirects to a different address on this site.', 'seo-change-monitor'),
                'why'   => __('If intentional (a moved page), this is fine. If not, visitors and search engines are being sent somewhere you did not choose.', 'seo-change-monitor'),
                'check' => __('Confirm the destination is right. Check your redirect plugin and permalink settings if it is not.', 'seo-change-monitor'),
            ],
            ChangeTypes::REDIRECT_OFFSITE => [
                'what'  => __('The page now redirects visitors to a different website.', 'seo-change-monitor'),
                'why'   => __('You are handing this page\'s traffic and ranking to another domain. Unless you set this up deliberately, treat it as urgent. Unexpected off-site redirects can also indicate a hacked site.', 'seo-change-monitor'),
                'check' => __('Check your redirect plugin and .htaccess. If you cannot find the source, scan the site for malware.', 'seo-change-monitor'),
            ],
            ChangeTypes::WORD_COUNT_DROP_MAJOR => [
                'what'  => $ratioPct !== null
                    ? sprintf(__('The page\'s text content shrank by about %d%%.', 'seo-change-monitor'), $ratioPct)
                    : __('Most of the page\'s text content disappeared.', 'seo-change-monitor'),
                'why'   => __('The page still loads, so nothing looks broken, but search engines now see a fraction of the content that earned its rankings. Page builder updates and migrations cause this silently.', 'seo-change-monitor'),
                'check' => __('Open the page and compare it with what should be there. Check your page builder after updates, and your post revisions for an accidental overwrite.', 'seo-change-monitor'),
            ],
            ChangeTypes::WORD_COUNT_DROP => [
                'what'  => $ratioPct !== null
                    ? sprintf(__('The page\'s text content shrank by about %d%%.', 'seo-change-monitor'), $ratioPct)
                    : __('A noticeable amount of the page\'s text content disappeared.', 'seo-change-monitor'),
                'why'   => __('If you trimmed the page yourself, ignore this. If not, part of the content may have been lost to a plugin, builder, or template change.', 'seo-change-monitor'),
                'check' => __('Open the page and confirm all sections still render.', 'seo-change-monitor'),
            ],
            ChangeTypes::ROBOTS_TXT_CHANGED => [
                'what'  => __('Your site\'s robots.txt file changed. That is the rulebook telling crawlers what they may visit.', 'seo-change-monitor'),
                'why'   => __('A wrong rule here can hide entire sections of your site from search engines.', 'seo-change-monitor'),
                'check' => __('Compare the before and after content. If you did not change it, check SEO and caching plugins. Several of them write to robots.txt.', 'seo-change-monitor'),
            ],
            ChangeTypes::ROBOTS_TXT_BLOCKS_ALL => [
                'what'  => __('Your robots.txt now blocks all crawlers from the entire site.', 'seo-change-monitor'),
                'why'   => __('Search engines will stop crawling your site completely. Rankings decay and new content never gets indexed. This is a full SEO outage.', 'seo-change-monitor'),
                'check' => __('This rule ("Disallow: /" for all user agents) is sometimes left over from a staging site. Remove it unless you truly mean to block everyone.', 'seo-change-monitor'),
            ],
            ChangeTypes::AI_BOT_RULE_CHANGED => [
                'what'  => $botsList !== ''
                    ? sprintf(__('Your robots.txt rules for AI crawlers changed. Affected: %s.', 'seo-change-monitor'), $botsList)
                    : __('Your robots.txt rules for AI crawlers changed.', 'seo-change-monitor'),
                'why'   => __('These rules decide whether AI assistants (ChatGPT, Claude, Perplexity…) can read and cite your site. Blocking them reduces AI visibility; allowing them lets your content be used by AI tools. Either can be right, but it should be your choice.', 'seo-change-monitor'),
                'check' => __('Confirm the new rules match your intent. Some SEO plugins add AI-bot rules on update without asking.', 'seo-change-monitor'),
            ],
            ChangeTypes::SITEMAP_UNREACHABLE => [
                'what'  => __('Your XML sitemap no longer loads. That is the list of pages you hand to search engines.', 'seo-change-monitor'),
                'why'   => __('Search engines use it to discover new and updated pages. While it is down, new content gets found late or not at all.', 'seo-change-monitor'),
                'check' => __('Sitemaps are generated by your SEO plugin or WordPress itself. Check that the plugin is active and its sitemap feature enabled, then re-save permalinks.', 'seo-change-monitor'),
            ],
            ChangeTypes::SITEMAP_INVALID => [
                'what'  => __('Your XML sitemap loads but is no longer valid XML.', 'seo-change-monitor'),
                'why'   => __('Search engines cannot parse a broken sitemap, so it stops helping them discover your pages.', 'seo-change-monitor'),
                'check' => __('A plugin conflict or PHP error is usually corrupting the output. Open the sitemap URL in a browser and look for an error message inside it.', 'seo-change-monitor'),
            ],
            ChangeTypes::SITEMAP_COUNT_DROP => [
                'what'  => __('The number of pages listed in your XML sitemap dropped noticeably.', 'seo-change-monitor'),
                'why'   => __('Pages missing from the sitemap can still be indexed, but a sudden drop often means content became unpublished, noindexed, or excluded by mistake.', 'seo-change-monitor'),
                'check' => __('Check your SEO plugin\'s sitemap settings for newly excluded post types, and confirm no batch of posts was unpublished.', 'seo-change-monitor'),
            ],
            ChangeTypes::BLOG_PUBLIC_DISABLED => [
                'what'  => __('The WordPress setting "Discourage search engines from indexing this site" was switched on.', 'seo-change-monitor'),
                'why'   => __('Your entire site now asks search engines to remove it from results. Left on, this destroys all search traffic. It is meant for sites under construction.', 'seo-change-monitor'),
                'check' => __('Go to Settings → Reading and untick "Discourage search engines", unless this site really should be hidden.', 'seo-change-monitor'),
            ],
            ChangeTypes::AI_BOT_GONE_SILENT => [
                'what'  => __('An AI crawler that used to visit this site regularly has not been seen for over 30 days.', 'seo-change-monitor'),
                'why'   => __('It may now be blocked by robots.txt, a firewall, or your host, which reduces your visibility in that AI assistant\'s answers.', 'seo-change-monitor'),
                'check' => __('Check your robots.txt rules and any security/firewall plugin\'s bot-blocking list. Note: full-page caching can also hide bot visits from this plugin.', 'seo-change-monitor'),
            ],
            ChangeTypes::MONITORING_IMPAIRED => [
                'what'  => __('This plugin cannot fetch your site\'s own pages, so monitoring is currently impaired.', 'seo-change-monitor'),
                'why'   => __('No new checks can run, which means changes could be happening unseen. This is a monitoring problem, not (necessarily) an SEO problem, but until it is fixed no all-clear can be trusted.', 'seo-change-monitor'),
                'check' => __('Your server may block "loopback" requests to itself. Ask your host to allow the site to request its own URLs, and check any security plugin\'s firewall rules.', 'seo-change-monitor'),
            ],
        ];
    }
}
