=== SwiftPress ===
Contributors: swiftpress
Tags: cache, performance, page cache, minify, preload
Requires at least: 5.7
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WordPress performance optimization — page caching, file optimization, font optimization, and intelligent cache preloading.

== Description ==

SwiftPress is a lean, no-bloat WordPress caching and performance plugin that delivers fast page loads without the complexity. Five core modules work together to accelerate your site:

**Page Cache** — Full-page caching with gzip compression, mobile-specific cache files, support for logged-in users, automatic .htaccess configuration (Apache), and configurable expiration.

**File Optimization** — Minify and combine HTML, CSS, and JavaScript. Defer or delay JS execution. Remove unused CSS. Exclude specific files as needed.

**Font Optimization** — Self-host Google Fonts locally for GDPR compliance and faster loading. Preload above-the-fold fonts. Force font-display: swap to eliminate layout shift.

**Cache Preloader** — Automatically preload your homepage, posts, pages, and taxonomies. Sitemap-based preloading auto-detects your sitemap (robots.txt, wp-sitemap.xml, sitemap_index.xml, sitemap.xml), parses it recursively (including gzipped sitemaps), and queues up to 10,000 URLs for background preloading with configurable batch sizes and crawl intervals.

**Advanced Controls** — DNS prefetch, preconnect hints, prefetch links on hover, cache exclusions by URI/cookie/user-agent/query string, async cache cleaning, and import/export settings.

= Key Features =

* Full-page caching with configurable expiration
* Gzip compressed cache files
* Separate mobile cache files
* Logged-in user caching
* Automatic .htaccess configuration (Apache)
* HTML, CSS, and JavaScript minification
* CSS and JS combining
* JavaScript defer and delay
* Critical CSS optimization
* Remove unused CSS
* Self-hosted Google Fonts (GDPR-friendly)
* Font preloading and font-display: swap
* Sitemap auto-detection and recursive parsing
* Background cache preloading with progress tracking
* DNS prefetch and preconnect resource hints
* Link prefetching on hover
* Cache exclusions (URI, cookies, user agents, query strings)
* Import/export settings
* WP-CLI support
* Multisite compatible
* Clean, native WordPress admin interface

== Installation ==

1. Upload the `swiftpress` directory to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **SwiftPress** in the admin sidebar to configure settings.
4. Enable Page Cache as a starting point, then explore other modules.

= Minimum Requirements =

* WordPress 5.7 or later
* PHP 7.4 or later

== Frequently Asked Questions ==

= How do I clear the cache? =

You can clear the cache in several ways:

1. Click the **Clear All Cache** button at the top of the SwiftPress settings page.
2. Use the **Purge All Cache** option in the WordPress admin bar.
3. Use WP-CLI: `wp swiftpress flush`

The cache is also automatically cleared when you update posts, pages, or plugin settings.

= Does SwiftPress work with Cloudflare? =

Yes. SwiftPress works at the server/application level, while Cloudflare operates at the DNS/proxy level. They complement each other. SwiftPress includes a built-in Cloudflare integration that can automatically purge Cloudflare's cache when your local cache is cleared. Configure it in the Advanced section with your Cloudflare API credentials.

= Does SwiftPress work with WooCommerce? =

Yes. SwiftPress is fully compatible with WooCommerce. Dynamic pages like the cart and checkout are automatically excluded from caching. The compatibility module ensures that logged-in customer sessions, cart contents, and checkout flows are never served from cache.

= How does font optimization work? =

When enabled, SwiftPress detects Google Fonts loaded by your theme and plugins, downloads the font files to your server, and rewrites the CSS to serve them locally. This eliminates external requests to Google's servers, improves load times, and helps with GDPR compliance. Above-the-fold fonts are preloaded, and `font-display: swap` is injected to prevent invisible text during loading.

= How does sitemap preloading work? =

SwiftPress automatically detects your sitemap by checking (in order): robots.txt Sitemap directives, /wp-sitemap.xml, /sitemap_index.xml, and /sitemap.xml. It then parses the sitemap recursively (including sitemap indexes and gzipped sitemaps), stores discovered URLs in a database table, and feeds them to the cache preloader in configurable batches. A progress bar in the admin UI shows total, completed, pending, and failed URLs. The sitemap is re-detected and re-parsed daily via wp_cron.

= Can I use WP-CLI to manage SwiftPress? =

Yes. SwiftPress provides these WP-CLI commands:

* `wp swiftpress flush` — Clear all page and file optimization cache
* `wp swiftpress flush fonts` — Clear the font cache
* `wp swiftpress preload status` — Show preload progress (total, completed, pending, failed)
* `wp swiftpress preload refresh` — Re-detect and re-parse sitemap
* `wp swiftpress status` — Show current configuration summary and cache sizes

= Is SwiftPress multisite compatible? =

Yes. SwiftPress supports both single-site and WordPress multisite (network-wide activation). Each site in the network gets its own cache directory and settings.

== Screenshots ==

1. Settings page — Page Cache section with master toggle and expiration controls
2. Settings page — File Optimization section with minification and defer options
3. Settings page — Font Optimization section with self-host toggle
4. Settings page — Cache Preloader section with sitemap detection and progress bar
5. Settings page — Advanced section with DNS prefetch and import/export

== Changelog ==

= 1.0.0 =
* Initial release
* Page caching with gzip compression and mobile support
* HTML, CSS, and JavaScript minification and combining
* JavaScript defer and delay with exclusion lists
* Critical CSS and unused CSS removal
* Google Fonts self-hosting, preloading, and font-display swap
* Sitemap auto-detection and recursive parsing
* Background cache preloading with progress tracking
* DNS prefetch, preconnect, and link prefetching
* Cache exclusions by URI, cookie, user agent, and query string
* Import/export settings
* WP-CLI commands
* Multisite support
* Clean admin UI with 5 collapsible sections
* Cloudflare integration
* Varnish cache support

== Upgrade Notice ==

= 1.0.0 =
Initial release of SwiftPress.
