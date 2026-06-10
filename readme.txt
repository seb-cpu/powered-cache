=== AICache ===
Contributors: webs
Tags: cache, performance, ai, page cache, core web vitals
Requires at least: 5.7
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted WordPress caching & performance. Runs a real audit, explains in plain language what's slowing your site, and fixes it in one click.

== Description ==

**AICache** is a caching and performance plugin with a feature no free competitor offers: an **AI Performance Diagnostic** that reads your site's real performance data (PageSpeed Insights), explains in plain language *why* it's slow, and maps every problem to a one-click, reversible fix.

= The AI advantage (the headline) =

* **Explain & Fix** — run a diagnostic and get a plain-English brief ("your homepage takes 4.1s because a Google Font blocks rendering and your hero image isn't preloaded"), each finding mapped to a real AICache setting.
* **One-click, validated, reversible** — a deterministic safety gate only ever applies real, in-range settings through AICache's existing save path; every change is snapshotted with one-click **Undo**, then re-audited to prove the gain.
* **Bring your own key** — uses your OpenRouter API key (default model Google Gemini 2.5 Flash-Lite, ~$0.001 per run, on demand — never per page view). The key is encrypted at rest and **never leaves your server**. **Works with no key too** — it falls back to a deterministic rules engine, so the plugin is fully functional without AI.
* **Natural-language command bar** and **optimization presets** (Safe / Balanced / Aggressive / WooCommerce) with explicit, risk-labelled changes.

= Performance core =

* **Page Cache** — full-page caching, gzip, separate mobile cache, logged-in users, configurable expiration; works on **nginx** (one-click config generator) and Apache (.htaccess), or via a pure-PHP drop-in with zero server config.
* **File Optimization** — minify/combine HTML/CSS/JS, defer & delay JavaScript, exclusions.
* **Font Optimization** — self-host Google Fonts (GDPR-friendly), preload above-the-fold fonts, layout-shift-safe `font-display` handling (eliminates font-swap CLS).
* **Cache Preloader** — sitemap auto-detection + recursive parsing, background preloading with progress tracking.
* **Delivery & integrations** — CDN/Cloudflare purge, image optimization, LCP optimization, DNS-prefetch/preconnect, link prefetch, self-hosted analytics, Heartbeat control, Varnish.
* **Native admin** — a dashboard-first UI in WordPress's own design language: live Performance Score, native submenus, auto-saving toggles and a real command palette. WP-CLI and multisite supported.

== Installation ==

1. Upload the `aicache` directory to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New → Upload).
2. Activate the plugin through the 'Plugins' menu.
3. Open **AICache** in the admin sidebar — you land on **The Brief**.
4. (Optional) In **Copilot**, add your OpenRouter API key to unlock the AI explanations, or define `SWIFTPRESS_OPENROUTER_KEY` in `wp-config.php`. Without a key the diagnostic still works via the rules fallback.
5. Run a diagnostic, apply the safe fixes, and (on nginx) copy the generated server config from the **Server** tab.

= Minimum Requirements =

* WordPress 5.7 or later
* PHP 8.0 or later (PHP 8.3 recommended)

== Frequently Asked Questions ==

= Do I need an API key? =

No. AICache works fully without one — the diagnostic falls back to a deterministic rules engine. Adding your own OpenRouter key (in the Copilot tab, or as a `wp-config.php` constant) unlocks the plain-language AI explanations. The key is encrypted at rest and is never sent to the browser, never written to cache files, and never included in settings exports.

= What data does the AI feature send, and where? =

Only when you click "Run Diagnostic". It sends **derived performance metrics** (scores, byte counts, timing numbers) and a list of **which AICache features are enabled**, to Google PageSpeed Insights and the OpenRouter API. **No page content, no visitor data, no personal information** is ever sent. See the External Services section. The feature is opt-in (no key, or no click = no external call) and you can remove your key at any time.

= How do I avoid breaking my site with optimization? =

AICache is built to be safe: nothing changes without your explicit confirmation; the AI only proposes whitelisted, validated settings; every applied change is snapshotted with one-click **Undo**; risky options (combine/delay JS, remove unused CSS) are off by default and labelled by risk; and you can exclude any specific CSS/JS file that misbehaves. Enable optimizations incrementally and use the re-audit loop to confirm each one helped.

= Does AICache work with Cloudflare and WooCommerce? =

Yes. There's a built-in Cloudflare integration (purge on cache clear) and compatibility shims for WooCommerce, Elementor, Divi, Beaver Builder, Bricks, WPML, ACF, AMP and more. Dynamic WooCommerce pages (cart/checkout/account) should be excluded from caching.

= Can I use WP-CLI? =

Yes: `wp swiftpress flush`, `wp swiftpress flush fonts`, `wp swiftpress preload status`, `wp swiftpress preload refresh`, `wp swiftpress status`.

= Is it multisite compatible? =

Yes — single-site and network-wide activation, with per-site cache directories and settings.

== External Services ==

This plugin optionally connects to the following external services when the AI Performance Diagnostic feature is used (admin-triggered, opt-in, on-demand only):

* Google PageSpeed Insights API (https://developers.google.com/speed/docs/insights/v5/get-started)
  - Used to collect performance metrics for the audited URL.
  - Data sent: the URL of your site's homepage (a public URL). No personal data is sent.
  - Terms: https://developers.google.com/terms
  - Privacy: https://policies.google.com/privacy

* OpenRouter API (https://openrouter.ai)
  - Used to generate plain-language explanations of performance findings.
  - Data sent: derived performance metrics (scores, byte counts) and a list of which AICache features are enabled. No page content, no visitor data, no personal information is sent.
  - Terms: https://openrouter.ai/terms
  - Privacy: https://openrouter.ai/privacy

If you do not configure an API key and do not run the diagnostic, the plugin makes no external requests.

== Credits & Attribution ==

AICache is a fork of **Powered Cache** and **Simple Cache** (by **Taylor Lovett** / 10up, with contributions from the Frontity project), distributed under the GNU General Public License v2.0 or later. The original authors' copyright notices are preserved. AICache adds the AI Performance Diagnostic, the "Editorial Console" admin UI, an nginx config generator, and assorted hardening on top of that GPL foundation.

== Changelog ==

= 1.3.0 =
* New: Database optimization — one-click cleanup of old revisions (keeps the newest 3), stale auto-drafts, expired trash, spam comments and expired transients, plus OPTIMIZE TABLE on every run; optional weekly schedule via WP-Cron.
* New: Bloat control switches — disable XML-RPC (and its pingback surface), remove jQuery Migrate on the front end, stop loading Dashicons for logged-out visitors, and limit WooCommerce cart-fragments AJAX to cart/checkout pages.
* Admin app is now responsive (usable down to phone widths) with visible keyboard-focus styles, an accessible command palette (combobox/listbox semantics, focus trap & return) and screen-reader-announced toasts; fixed the admin being unusable under prefers-reduced-motion.
* Full-plugin audit (39 confirmed findings fixed): settings saved from the AI or auto-save now run the same side-effects as the form (preloader start/stop, cache cleanup); saving settings no longer flushes the whole object cache; failed tracker downloads back off instead of blocking pageviews; the Heartbeat toggle actually throttles; Critical CSS/RUCSS are AI-appliable with a Used-CSS safelist field; LCP/prefetch toggles honestly marked engine-pending; accurate Apache guidance on the Server screen; setting labels come from one translatable map; better contrast and many copy/branding cleanups.
* Polish: the Brief headline no longer double-dots or repeats its first sentence; icons are size-locked outside the app stylesheet.

= 1.2.0 =
* Navigation moved into native WordPress submenus (The Brief / Settings / Server / Copilot) — the custom side rail is gone, screens are deep-linkable and feel at home in wp-admin.
* The command palette is real: type to run commands (diagnostic, flush, bulk image optimize, open screens, toggle any setting) or ask AICache a question — answered by the AI within your spend cap.
* Diagnostic results now persist: the Brief re-shows your last analysis (with its age) after navigating away.
* PageSpeed Insights key is validated with Google on paste and shows an active/inactive status, like the OpenRouter key.
* New: Speed up the WordPress admin — removes remote-fetching dashboard widgets, throttles Heartbeat outside the editor, stops synchronous update checks from blocking admin pageloads.
* New: Minify HTML toggle surfaced in Settings; self-host GA/Tag Manager + Meta Pixel engine (twice-daily refreshed local copies); per-family font preloading (layout-shift guard).
* Critical CSS / Remove Unused CSS engine (off by default, test per site); icons can no longer render oversized outside the app's stylesheet.

= 1.1.0 =
* Image optimization is now a real engine: JPEG/PNG uploads get next-gen (WebP/AVIF) versions automatically, the front end serves them wherever the converted file is smaller, and existing media can be bulk-converted with one click (or `wp swiftpress optimize-images`).
* Layout-shift fix: self-hosted fonts now load with CLS-safe `font-display` (no more text reflow on first visit) — filterable via `swiftpress_font_display`.
* Edge/CDN cache-busting: minified CSS/JS URLs now carry a content version (`?m=`), so style and script changes propagate through Cloudflare instantly instead of after the cache TTL.
* WordPress 7.0 compatibility metadata: plugin now reports `Tested up to`, `Requires at least` and `Requires PHP` through the updater, so wp-admin shows real compatibility instead of "unknown"; added an `Update URI` header to protect against wordpress.org slug collisions.
* Native WordPress admin re-skin, auto-saving toggles, OpenRouter key auto-validation on paste, DNS-prefetch auto-detection.

= 1.0.0 =
* AI Performance Diagnostic ("Explain & Fix"): PageSpeed audit → plain-language findings → one-click, validated, reversible fixes. BYO OpenRouter key with a no-key deterministic fallback.
* New "Editorial Console" admin: dashboard-first UI, live Performance Score, optimization presets, command palette, dark mode.
* nginx config generator surfaced as a first-class screen; previously-hidden settings (CDN, image optimization, LCP, self-hosted analytics, Heartbeat) exposed.
* Page caching (gzip, mobile, logged-in), HTML/CSS/JS minify+combine, JS defer/delay, Google Fonts self-hosting + font-display swap, sitemap-based preloading, resource hints, Cloudflare + Varnish, import/export, WP-CLI, multisite.
* Security hardening (encrypted key storage, secret-strip lists, validated AI apply path) and PHP 8.3 / WP 6.7 compatibility fixes.

== Upgrade Notice ==

= 1.1.0 =
Real image optimization engine, CLS-safe font loading, edge cache-busting and WordPress 7.0 compatibility reporting.

= 1.0.0 =
Initial AICache release.
