=== Style Guide Reviewer ===
Contributors: yourwporgusername
Tags: ai, editorial, content, gutenberg, style-guide
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Review post content against a brand style guide, directly in the block editor, using the WordPress AI Client.

== Description ==

Style Guide Reviewer adds a sidebar to the block editor that lints the current post against a brand style guide you paste into Settings. Violations are returned as a structured list grouped by severity (critical, major, minor, suggestion), each with an offending excerpt and a rewrite suggestion.

The plugin consumes whichever AI provider the site admin has configured through the WordPress AI Client — there is no plugin-managed API key. Install any AI Client connector (OpenAI, Anthropic, Google Gemini, etc.), configure it once, and the sidebar lights up.

**Features**

* Zero-key setup — uses the site's configured AI Client provider.
* Block-editor sidebar that renders issues grouped by severity, with offending text quoted.
* Results cached per-post by content hash and invalidated on save — repeat reviews are free.
* Per-user rate limiting (10 reviews/minute by default, filterable).
* Long posts are safely trimmed at a sentence boundary before sending.
* Exposed as a WordPress Ability (`sgr/review-post`), so other AI orchestrators on the site can consume it.
* Clean uninstall — all options and caches removed.

== Installation ==

1. Ensure your site runs WordPress 7.0 or later.
2. Install and configure an AI Client connector plugin for your provider of choice (for example, an OpenAI, Anthropic, or Gemini connector).
3. Install Style Guide Reviewer from the Plugins directory, or upload the .zip and activate.
4. In the admin, visit **Settings → Style Guide Reviewer** and paste (or upload) your brand style guide.
5. Open any post in the block editor and click the **Style Guide Reviewer** icon in the top-right to run a review.

== Frequently Asked Questions ==

= Do I need an API key? =

Not for this plugin. Style Guide Reviewer does not store AI credentials. It delegates to the WordPress AI Client, which is configured once per site by whatever connector plugin you install for your chosen provider.

= What WordPress version is required? =

WordPress 7.0 is the minimum. The plugin relies on the core AI Client (`wp_ai_client_prompt()`) and Abilities API that ship with 7.0.

= How are long posts handled? =

Content is stripped of HTML/shortcodes and trimmed to roughly 40,000 characters at a sentence boundary before being sent to the model. If content was trimmed, the sidebar shows an info banner.

= Can I increase or disable the rate limit? =

Yes — filter `sgr_rate_limit_per_minute`. Returning `0` disables limiting entirely.

= Where is the review result cached? =

In hidden post meta (`_sgr_review_cache`), keyed by a SHA-256 hash of (guide + normalized content). The cache is cleared whenever the post is saved, and expires after 24 hours by default (filter `sgr_cache_ttl`).

= Is the plugin multisite-aware? =

Yes. Options and caches are cleaned up per-site when the plugin is uninstalled network-wide.

== Screenshots ==

1. The sidebar button and grouped results.
2. The settings page with the style-guide textarea and file upload.

== Changelog ==

= 2.0.0 =
* Migrated from bundled OpenAI integration to the WordPress 7.0 AI Client — no plugin-managed API keys.
* Exposed as a `sgr/review-post` Ability via the Abilities API.
* Added per-post result caching (SHA-256 content hash) with `save_post` invalidation.
* Added per-user rate limiting (10/min, filterable).
* Added safe content trimming at sentence boundaries.
* Hardened file upload handler (capability + nonce before touching `$_FILES`, `wp_check_filetype_and_ext`, WP_Filesystem, 1 MB cap).
* Full wp.org submission polish: namespaced classes, uninstall handler, text-domain loader, JS translations, multisite-aware cleanup.
* Automatic migration from 1.x (POC): copies `sgr_poc_guide_text` to the new option and deletes the old API-key option.

= 1.0.0 =
* Initial POC release (OpenAI Responses API, direct key storage).

== Upgrade Notice ==

= 2.0.0 =
This release removes the plugin-managed OpenAI API key. Install a WordPress AI Client connector for your preferred provider before updating; the plugin will delete the old `sgr_poc_openai` option automatically.
