# WP Super Cache — Competitor Dossier
**Research date:** 2026-06-08
**Plugin version researched:** 3.1.1 (released May 27, 2026)
**Active installs:** 1,000,000+
**Developer:** Automattic (WordPress.com parent company)
**License:** GPL (free, no premium tier)

---

## 1. Market Positioning

WP Super Cache is the canonical "baseline simplicity" caching plugin — backed by Automattic, the company that owns WordPress.com itself. It occupies the "zero-cost, zero-frills static page cache" niche. Its competitive moat is not features but trust: Automattic's name signals longevity and core compatibility. No premium upsell, no freemium gating, no feature-locking. It is literally the simplest credible caching solution a WordPress user can install for free.

**One-line market position:** The reliable, unglamorous, Automattic-backed static HTML cache for simple sites that just want "caching on."

**Ironic insight worth knowing:** Automattic themselves run WordPress.com on Varnish (server-level), not WP Super Cache. The product is honest about its ceiling.

**Rankings in 2026 roundups:** Usually appears 9th-12th in "best caching plugins" lists, above nothing but below everything with minification. OnlineMediaMasters ranked it 11th of 12. The universal verdict: "barely maintained for years, no significant new features, not addressing Core Web Vitals."

---

## 2. Pricing (2026)

**100% free. No tiers. No premium version. No upsell.**

- WordPress.org: free download
- WordPress.com: listed as "Free on paid plans" but also displays a "This plugin is not supported on WordPress.com" compatibility notice — meaning it targets self-hosted only
- No commercial licensing, no add-ons sold separately
- Support is community-only (WordPress.org forums), no paid support option

---

## 3. Full Feature List

### 3.1 Cache Delivery Methods (Three Modes, Ranked by Speed)

| Mode | How It Works | Speed | Difficulty |
|------|-------------|-------|------------|
| **Expert** | Apache mod_rewrite serves static HTML — zero PHP execution | Fastest (TTFB ~20-25ms) | Requires .htaccess editing |
| **Simple** | PHP is loaded but serves pre-built static HTML file | Fast (TTFB ~50ms) | Beginner-safe |
| **WP-Cache** | Legacy/flexible; caches logged-in users and feeds | Slowest of the three | Works anywhere |

Expert mode is Apache-only. Simple mode works on any server including Nginx.

### 3.2 Cache Preloading
- Crawls entire site sequentially, generating cached files page-by-page
- Configurable refresh interval (default: 600 minutes; recommended: 1440 minutes/daily on shared hosting)
- Preload Mode disables garbage collection while running (old files kept until preload completes)
- Email notification option for preload completion
- "Preload Cache Now" button for on-demand full-site warm

### 3.3 Garbage Collection
- Automated deletion of stale cache files based on cache timeout setting
- Default cache timeout: 1800 seconds (30 min); recommended in guides: 3600-7200 seconds
- Disabled while Preload Mode is active
- Email notifications available for garbage collection events

### 3.4 CDN Integration
- OSSDL CDN off-linker built in: rewrites URLs for images, CSS, JS in wp-content and wp-includes to point at CDN hostname
- Supports origin-pull CDNs (BunnyCDN, KeyCDN)
- Custom CNAME support (up to ~2 additional CDN URLs)
- HTTPS URL skip option
- Does NOT integrate natively with Cloudflare (Cloudflare support was removed; referenced in negative reviews as "Jetpack ads" controversy)
- No automatic cache purge API calls to CDN on content update

### 3.5 Compression
- Gzip compression of cached files
- Must be manually enabled in Advanced tab (not on by default)
- Serves compressed versions to browsers that support it

### 3.6 Mobile Device Detection
- Basic mobile device detection built in
- Can serve separate cached versions for mobile vs desktop
- Embedded device detection (as of v3.1.0) — no longer requires Composer dependency

### 3.7 Logged-In User Handling
- Can be configured to skip caching for logged-in users (recommended default)
- WP-Cache mode can cache pages for logged-in users if needed
- Cookie-based bypass detection

### 3.8 Cache Management UI
- "Contents" tab shows: number of cached pages, which pages are cached, age of each cached file in seconds
- Manual "Delete Cache" action
- Cache tester built into Easy tab: sends request and compares timestamps to confirm caching is working

### 3.9 Developer Extension System
- Internal plugin system loaded before most of WordPress
- `add_cacheaction()` API for custom hooks and filters
- REST API endpoints for cache management
- REST API caching support for headless WordPress

### 3.10 Debugging
- Debug tab: enable logging to record errors and events
- Log viewer (protected from garbage collection since v1.12.3)
- Detailed debug comments injected into HTML source (visible in page source as `<!-- super cache -->`)
- Debug comments stripped from API responses (fixed in v3.1.1)

### 3.11 Miscellaneous
- Dynamic caching option (for ads/counters, Simple mode only)
- 304 browser caching support
- Extra homepage checks option
- Cache rebuild option (prevents CPU spikes when cache expires under high traffic)
- Multisite network support (Simple mode recommended for multisite)
- Reject/exclude specific URL strings from caching
- Configurable cache storage location (default: wp-content/cache/supercache/)
- Yandex parameter handling (added v3.0.1)
- Disable caching for wp_die() error pages (added v3.1.0)

### 3.12 What Is Completely Absent
- CSS/JS minification — none
- HTML minification — none
- Image optimization — none
- Lazy loading — none
- Critical CSS generation — none
- Object caching (Redis/Memcached) — none
- Database optimization — none
- Browser resource hints (preload/preconnect/prefetch) — none
- Font optimization — none
- Core Web Vitals-specific tools — none
- Any AI or ML features — none whatsoever

---

## 4. Admin Panel & User Experience

### 4.1 Overall Aesthetic & Feel
"Tab-heavy, text-dense, feels like it hasn't been redesigned since 2010." The interface uses checkboxes with technical terminology rather than modern toggle switches with tooltips. No icons, no visual hierarchy beyond tabs. Functional but not welcoming. Described consistently as "not pretty" and "old-fashioned" across multiple independent reviews.

The plugin does NOT create its own top-level admin menu item — it slots into Settings > WP Super Cache, which keeps it discreet but also makes it feel like an afterthought rather than a product.

### 4.2 Tab Structure (7 Tabs)

**Easy Tab** (Primary onboarding surface)
- Single "Caching On/Off" checkbox (the whole point)
- "Update Status" save button
- "Cache Tester" section with "Test Cache" button — excellent: instantly verifies caching works by comparing page headers without leaving the plugin

**Advanced Tab** (Core configuration)
- Caching enable/disable checkbox (duplicates Easy tab — confusing)
- Cache Delivery Method radio: Simple | Expert
- Miscellaneous: compression, dynamic caching, extra homepage checks, cache rebuild
- Expiry Time & Garbage Collection: timeout field + schedule display
- Rejected URL Strings: textarea for exclusion patterns
- PHP/mod_rewrite selection (Expert mode)

**CDN Tab**
- Enable CDN Support checkbox
- Off-site URL field (CDN root)
- Additional CNAMEs field
- HTTPS URL skip checkbox

**Contents Tab**
- Live count of cached pages vs expired pages
- Per-page listing with age in seconds
- Delete all cache button
- Useful as a dashboard but very basic — no charts, no sorting

**Preload Tab**
- Cache refresh interval (minutes)
- Preload Mode checkbox (disables garbage collection)
- Email notification toggle
- "Preload Cache Now" button
- Warning text about server resource usage

**Plugins Tab**
- Compatibility settings for third-party integrations
- Mostly a list of known-compatible plugin configurations

**Debug Tab**
- Enable logging checkbox
- Log viewer (read-only display)
- Cache status display
- Technical information for troubleshooting

### 4.3 Onboarding Flow
1. Install/activate
2. Redirect to Easy tab
3. Check "Caching On" → click "Update Status"
4. Optionally click "Test Cache" to verify
5. Done for 80% of users

The "Easy" tab is a genuinely good onboarding decision — it surfaces the single meaningful action (enable caching) without overwhelming the user. The problem is everything after that requires jumping to Advanced with no progressive disclosure or tooltip guidance.

### 4.4 Information Architecture Problems
- "Caching" checkbox appears on BOTH Easy and Advanced tabs — redundant and confusing
- No status dashboard showing cache hit rate, memory usage, or performance impact
- No "recommended settings" wizard or contextual help beyond static text
- Expert mode warning about .htaccess risks is text-only, no visual alert
- Advanced tab mixes performance-critical settings (cache delivery method) with housekeeping (garbage collection) with no visual separation

### 4.5 Smart Defaults Assessment
| Default | Is It Smart? | Notes |
|---------|-------------|-------|
| Caching OFF on install | Sensible — requires explicit opt-in | |
| Simple mode as default | Smart — safer than Expert | |
| Compression OFF by default | Bad — most users won't find it | |
| Cache timeout 1800s | Mediocre — too short for most blogs | |
| Preload OFF by default | Smart — prevents shared hosting overload | |
| Logging OFF by default | Smart | |

---

## 5. AI / Automation

**None. Zero. Not even marketing-level AI.**

WP Super Cache has no AI, no ML, no automated optimization suggestions, no smart defaults engine, no anomaly detection. It does not use any LLM or rule-based "intelligence" beyond static if/else logic for cache invalidation.

The broader WordPress ecosystem (Uncanny Automator 7.1, March 2026) has added "Smarter Cache Control" automation integrations, but WP Super Cache itself contributes nothing to this — it merely exposes hooks that other tools can use.

**Verdict:** Clean slate. Zero AI credibility claimed, none exists. Distinguishing SwiftPress with genuine LLM-powered optimization would be a massive differentiator in this space.

---

## 6. Performance Methodology

### 6.1 Core Mechanism
WP Super Cache's entire performance model is: **PHP bypass via static HTML**. 

Dynamic WordPress = PHP + MySQL query on every request.
WP Super Cache = serve pre-built HTML file from disk, skip PHP and MySQL entirely.

This is effective but single-dimensional. It does nothing about:
- Asset size (no minification)
- Render-blocking resources
- Image weight
- Database efficiency
- Object/opcode caching

### 6.2 Benchmark Data (From Independent Reviews, 2025-2026)

**HostingRadar.io test (blog with Elementor, 22 plugins):**
- Before: 58/100 PageSpeed, 3.6s load time
- After WP Super Cache: 84/100 PageSpeed, 1.4s load time
- Improvement: +26 PageSpeed points, 61% faster load

**PixelNet test:**
- TTFB reduced from 800ms to under 50ms on shared hosting (Expert mode)

**Expert vs. Simple mode TTFB difference:**
- Expert: ~20-25ms
- Simple: ~50ms
- Difference: ~10-25ms — meaningful but not dramatic

**Comparative benchmarks:**
- WP Super Cache: 84/100, 1.4s
- WP Rocket: 91/100, 1.1s
- LiteSpeed Cache: 93/100, 0.9s

The gap to WP Rocket is ~0.3s — real but not catastrophic. The deeper problem is WP Super Cache stops at page caching while WP Rocket goes further with asset optimization.

### 6.3 Cache Invalidation Strategy
- Full cache clear on post publish/update
- Automatic expiry via garbage collection (timeout-based)
- Manual "Delete Cache" button
- No granular/selective cache invalidation per URL or post type

---

## 7. Nginx Support (Specific Focus)

### 7.1 The Core Problem
WP Super Cache's Expert mode — its fastest configuration — depends on Apache's mod_rewrite module to serve static HTML without touching PHP. Nginx does not have .htaccess support and cannot auto-configure from the plugin's generated rules. This is a structural limitation.

### 7.2 What Actually Works on Nginx

**Simple mode: Works fine.** PHP serves the cached HTML files. Speed is acceptable (~50ms TTFB). The plugin functions normally, just can't bypass PHP entirely.

**Expert mode on Nginx: Requires manual server configuration** — not trivial. The required nginx.conf additions use `try_files` directives:

```nginx
set $cache_uri $request_uri;

# Bypass conditions (POST, query strings, admin, logged-in users)
if ($request_method = POST) { set $cache_uri 'null cache'; }
if ($query_string != "") { set $cache_uri 'null cache'; }
if ($request_uri ~* "(/wp-admin/|/xmlrpc.php|/wp-(.*)\.php|index\.php|wp-content/uploads/)") {
    set $cache_uri 'null cache';
}
if ($http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wordpress_logged_in") {
    set $cache_uri 'null cache';
}

location / {
    try_files /wp-content/cache/supercache/$http_host/$cache_uri/index-https.html
              /wp-content/cache/supercache/$http_host/$cache_uri/index.html
              $uri $uri/ /index.php$is_args$args;
}
```

GetPageSpeed updated their nginx config guide for WP Super Cache on February 16, 2026 — indicating active community maintenance of these workarounds.

### 7.3 Nginx Gotchas
- **Query string handling:** Must pass `$is_args$args` to index.php fallback. Many older guides got this wrong and told users to omit it (incorrectly breaks WP Super Cache). The correct behavior: pages with query strings bypass the cache but the fallback PHP still works correctly.
- **HTTPS vs HTTP cache files:** Two separate cached files exist (`index-https.html` and `index.html`) — nginx config must try both.
- **"Not a trivial task":** GetPageSpeed's own documentation admits this. Compared to LiteSpeed Cache (native integration) or WP Rocket (works transparently), this is a significant friction point.
- **Plugin generates .htaccess rules it cannot deploy:** On nginx, WP Super Cache generates the Apache rules but they do nothing. There is no in-plugin nginx config generator or even a "copy this to your nginx.conf" helper.

### 7.4 Summary
Nginx support is "technically possible but DIY." Simple mode works for most nginx users. Expert mode on nginx requires manual server config that most shared hosting users cannot modify. Reviewers generally describe it as compatible ("doesn't matter, Apache or Nginx") — but this glosses over the fact that full Expert-mode speed on nginx requires work outside WordPress admin.

---

## 8. Recent Development Activity (2024-2026)

The plugin went through a major version increment (1.x → 2.x → 3.x) in 2025-2026 but the changes were almost entirely housekeeping:

| Version | Date | Notable |
|---------|------|---------|
| 3.1.1 | May 2026 | Security hardening for cache filename generation, PHP 8 fixes |
| 3.1.0 | April 2026 | Don't cache wp_die() pages, embed device detection |
| 3.0.3 | November 2025 | WP 6.9 compatibility, dependency updates |
| 3.0.2 | October 2025 | E2E tests, code analysis fixes |
| 3.0.1 | August 2025 | Yandex parameter handling |
| 3.0.0 | June 2025 | Coding standards, bump minimum WP to 6.7 |
| 2.0.0 | January 2025 | Bump minimum PHP to 7.2, cache content verification |

**Honest assessment:** These are maintenance releases, not feature development. The plugin is being kept alive and compatible, not evolved. OnlineMediaMasters' characterization that Automattic "barely maintains this plugin" with "no significant features added for years" is accurate. The major version bumps (1→2→3) reflect dependency and standards updates, not user-visible feature work.

---

## 9. Strengths (Be Honest)

1. **Automattic trust signal** — strongest brand credibility of any free caching plugin
2. **Radical simplicity** — 3-click setup that genuinely works
3. **Built-in cache tester** — immediate verification without leaving the plugin
4. **Zero upsell pressure** — no nags, no "upgrade to pro," no artificial feature limits
5. **Conservative stability** — "WP Super Cache rarely breaks anything"
6. **Large install base** (1M+) means broad plugin ecosystem compatibility is well-tested
7. **Expert mode TTFB** — when properly configured on Apache, competitive raw performance
8. **Custom plugin API** — `add_cacheaction()` system is developer-extensible

---

## 10. Weaknesses (Be Honest)

1. **UI frozen in 2010** — no redesign, checkboxes instead of toggles, no visual hierarchy
2. **No performance scope beyond page cache** — zero CSS/JS/image/font optimization
3. **No Core Web Vitals tooling** — cannot improve LCP, CLS, INP scores at all
4. **nginx Expert mode is manual labor** — no in-plugin nginx config generator
5. **Redundant "Caching" checkbox** on two tabs — basic UX mistake
6. **No cache hit rate or performance dashboard** — Contents tab shows file count, not actionable metrics
7. **Community-only support** — "can take days for an answer"
8. **Cloudflare integration removed** — users with Cloudflare have one fewer integration option
9. **No granular cache invalidation** — full cache clear or nothing
10. **Preload is binary** — no priority ordering (high-traffic pages first), just sequential crawl
11. **CDN: no purge API** — doesn't call CDN to invalidate when content updates
12. **No automatic compression enablement** — gzip is off by default, most users miss it
13. **Ranked 11th of 12** in major 2026 plugin roundup — market consensus is it's falling behind

---

## 11. UX & Feature Ideas Worth Borrowing (Legally — Inspiration Only)

### Idea 1: The "Easy" Tab + Built-In Cache Tester Pattern
The single-action "Easy" tab with an immediate "Test Cache" button is genuinely good UX. The user enables caching and can verify it works without leaving the settings page or opening browser dev tools. SwiftPress could implement this as a one-click "verify cache is working" button that checks response headers and shows a green/red status inline. The difference: make it real-time and show what it found (cache age, method, TTFB).

### Idea 2: The "Contents" Tab as Cache Inventory
Showing which pages are cached and how old each cached file is — rather than just "X pages cached" — gives users actionable visibility. SwiftPress could do this far better with sorting (oldest first, most-viewed first), filtering by post type, and per-URL manual invalidation buttons. The concept is sound; the execution is primitive.

### Idea 3: In-Plugin Nginx Config Generator
WP Super Cache's biggest nginx friction point is the absence of any help generating nginx rules. When SwiftPress detects nginx (via `$_SERVER['SERVER_SOFTWARE']` or similar), it could display a "Copy these rules to your nginx.conf" code block — pre-filled with the correct site-specific paths. No plugin does this well. It would be a standout feature for the growing nginx/VPS user base.

---

## 12. Competitive Summary Table

| Dimension | WP Super Cache | SwiftPress Target |
|-----------|---------------|-------------------|
| Price | Free, no tiers | Free (GPL) |
| Page cache | Yes (3 modes) | Yes |
| Object cache | No | Yes (Redis/Memcached) |
| CSS/JS minify | No | Yes |
| Image optimization | No | Yes |
| Lazy load | No | Yes |
| Font optimization | No | Yes |
| DB optimization | No | Yes |
| CDN with purge API | No | Yes |
| Nginx support | Manual/DIY | First-class |
| AI features | None | BYO OpenRouter key |
| Admin UI | 2010-era | Modern |
| Cache hit dashboard | No | Yes |

---

## Sources

- https://wordpress.org/plugins/wp-super-cache/
- https://github.com/Automattic/wp-super-cache/releases
- https://jetpack.com/support/wp-super-cache/initial-wp-super-cache-setup-and-configuration/
- https://onlinemediamasters.com/wp-super-cache-settings/
- https://www.hostingradar.io/en/blog/wp-super-cache-review
- https://www.pixelnet.in/blog/wp-super-cache-plugin-review/
- https://fatlabwebsupport.com/blog/website-optimization/wp-super-cache-review/
- https://www.wpbeginner.com/beginners-guide/how-to-install-and-setup-wp-super-cache-for-beginners/
- https://www.getpagespeed.com/server-setup/wp-super-cache-nginx-configuration
- https://easyengine.io/wordpress-nginx/tutorials/single-site/wp-super-cache/
- https://webstick.blog/wp-super-cache-review
- https://wpmayor.com/wordpress-caching-plugins/
- https://onlinemediamasters.com/best-wordpress-cache-plugins/
- https://blogvault.net/wp-super-cache-review/
- https://www.isitwp.com/wordpress-plugins/wp-super-cache/
