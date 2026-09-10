=== Silent SEO Alerts ===
Contributors: alexmorgandev
Tags: seo, monitoring, noindex, alerts, schema
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Alerts you when a page goes noindex or its title, meta, canonical or schema changes, and explains what it means in plain English.

== Description ==

Most SEO damage is silent. A plugin update flips a page to `noindex`, a theme
change drops the canonical tag, someone rewrites a title, an editor trims 800
words out of a post — and nothing tells you. You find out weeks later, when the
traffic is already gone.

Silent SEO Alerts takes a snapshot of the SEO-relevant parts of your pages,
then re-checks them on a schedule and tells you what changed. Every finding is
graded — critical, warning or informational — and written in plain English, so
you know whether to drop everything or just make a note.

= What it watches on each page =

* **Indexing** — `noindex` and `nofollow` appearing or disappearing, and any other change to the robots meta tag
* **Titles and meta descriptions** — changed or removed
* **Canonical tags** — changed, removed, or pointed at another domain
* **Structured data** — schema types added or removed, and JSON-LD that stopped parsing
* **Social tags** — Open Graph and Twitter card changes
* **Headings** — the H1 changing, disappearing, or a page suddenly having several
* **Content volume** — word-count drops, flagged harder when the drop is severe
* **Availability** — HTTP errors, new redirects, and redirects that now leave your site

= What it watches site-wide =

* **robots.txt** — any change, and a loud alert if it starts blocking everything
* **AI crawler rules** — when your robots.txt starts or stops allowing GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot and others
* **XML sitemap** — unreachable, invalid, or a sudden drop in the number of URLs
* **Search engine visibility** — the WordPress "Discourage search engines" setting being switched on

= AI crawler activity =

The plugin also records when AI crawlers actually visit, so you can see which
ones are reading your site and notice when one goes quiet. This needs no setup:
it reads the user agent on incoming requests.

= Alerts =

When a run finds something, you get one email digest — not one message per
finding. You choose the threshold, so you can be told about everything or only
about the critical items. Checks run hourly, twice daily or daily, and a page
is also re-checked shortly after you edit it.

= Everything runs on your own site =

There is no account, no API key and no external service. The plugin fetches the
URLs you add to it — normally your own pages, your robots.txt and your sitemap
— using the WordPress HTTP API, and stores the results in your own database.
Nothing is sent anywhere else, and no usage data is collected.

= Source code =

The admin screens are built from TypeScript and React. The files in `assets/`
are the compiled output; the readable source, the build configuration and the
lockfile all live in the public repository:

https://github.com/csemazharul/silent-seo-alerts

`BUILD.txt` there has the two commands that turn one into the other.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/silent-seo-alerts`, or install it through **Plugins → Add New**.
2. Activate it through the **Plugins** menu.
3. Open **Silent SEO Alerts** in the admin sidebar. The plugin takes a first snapshot of your pages — this is the baseline it compares against, so no findings appear until the next run.
4. Under **Settings**, pick a check frequency and the email address alerts should go to.

== Frequently Asked Questions ==

= Why do I see nothing after activating? =

The first run records a baseline. There is nothing to compare against yet, so
there are no findings. The next scheduled run is the one that reports changes.

= Does it slow down my site? =

Checks run on WP-Cron, in the background, and fetch pages one at a time. AI
crawler tracking adds a single throttled lookup on requests from a known bot
user agent and does nothing at all for normal visitors.

= Will it flag changes I made on purpose? =

It will report them, because it cannot know your intent — but expected edits
such as a rewritten title are graded lower than something like a page going
`noindex`. You can dismiss anything you have already dealt with.

= Does it need an API key or an account? =

No. Nothing in this plugin talks to a third-party service.

= What happens to my data if I delete the plugin? =

Deleting the plugin removes its tables, options, scheduled events and cached
values completely. Deactivating it leaves everything in place, so you can
deactivate safely if you only want to pause monitoring.

= Does it work on multisite? =

Yes. Each site keeps its own targets, history and settings, and deleting the
plugin cleans up every site on the network.

== Screenshots ==

1. The dashboard: how many critical, warning and informational changes are open, a 14-day history, a breakdown by change type, what needs attention first, and which AI crawlers have visited.
2. Monitored pages: every page being watched, when it was last checked, how that check went, and a switch to pause any of them.
3. The flight log: every change found, graded by severity and explained in plain English, filterable by severity, status and page.
4. Site-wide checks: robots.txt, the XML sitemap, WordPress's own search engine visibility setting, and what your robots.txt tells each AI crawler.
5. Settings: how often to check, how long to keep history, and who gets emailed about what.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First release.
