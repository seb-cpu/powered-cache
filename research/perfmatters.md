# Perfmatters — Competitor Dossier
**Research date:** 2026-06-08  
**Plugin URL:** https://perfmatters.io  
**Created by:** Forgemedia LLC (Brian Jackson + Dustin Hartzler)

---

## 1. Market Positioning

Perfmatters positions itself as the **"lightweight WordPress performance plugin"** — deliberately NOT a caching plugin. Its tagline orbits around complementing a cache plugin rather than replacing one. The core pitch is surgical precision: remove what you don't need, don't load what isn't used, and add zero front-end JavaScript overhead.

**Key differentiation claims:**
- "No JavaScript on the front-end" (the plugin itself)
- Script Manager as the flagship killer feature
- Built by developers with "26+ years of WordPress experience"
- 0.018s front-end overhead vs WP Rocket's 0.107s (self-reported)
- Support directly from developers, not a tiered support team

**Target user:** WordPress developers, performance consultants, and technically-minded site owners who already have (or will pair) a caching layer. Used heavily in the "WP Rocket + Perfmatters" or "FlyingPress + Perfmatters" professional performance stack.

**What it is NOT:** It is not a full-stack cache plugin. No page cache, no browser cache headers, no object cache, no full CDN. It is a performance *optimizer*, not a caching solution.

---

## 2. Pricing (2026, Current)

| Plan | Price/year | Sites | Notes |
|------|-----------|-------|-------|
| Personal | $24.95 | 1 | Full features |
| Business | $54.95 | Up to 3 | Full features |
| Unlimited | $124.95 | Unlimited | Full features, agency use, multisite |

**Key pricing notes:**
- All tiers get identical features — no feature gating between plans
- Flat yearly pricing, renewal price matches signup price (no introductory bait)
- 30-day money-back guarantee with caveats: excludes third-party plugin conflicts, requires documented support attempts, does not apply to renewals/upgrades
- No free tier, no free trial, no lifetime deal
- Seasonal discounts/coupons available periodically
- No per-feature upsells — everything ships in the base purchase

**Value assessment:** At $24.95/year for 1 site, it is one of the most affordably priced premium performance plugins on the market. The Unlimited plan at $124.95 is competitive for agencies running many sites.

---

## 3. Full Feature Set

### 3.1 Quick-Toggle Bloat Removal (General Tab)
One-click toggles to disable WordPress core bloat. Each is an independent checkbox:

- Disable Emojis (removes emoji scripts + DNS prefetch for s.w.org)
- Disable Embeds (oEmbeds, removes wp-embed.js)
- Disable Dashicons (front-end admin icon font, unless admin bar is visible)
- Remove jQuery Migrate
- Disable WordPress Version from output
- Disable XML-RPC (closes attack surface)
- Disable RSS Feeds
- Disable Self-Pingbacks
- Disable Comments (and comment URLs)
- Disable REST API (or limit to authenticated users)
- Disable Heartbeat API (with per-page granularity: disable everywhere / admin only / dashboard only / editor only)
- Limit Post Revisions (set numeric limit)
- Change Autosave Interval
- Remove Shortlink
- Disable WooCommerce Cart Fragments (major win for WooCommerce sites — this alone can save 100-300ms)
- Disable WooCommerce Scripts/Styles globally (then re-enable per page via Script Manager)
- Disable Google Maps (if loaded globally but only needed on /contact)
- Disable Google Fonts globally (separate from local hosting option)
- Disable Gravatars
- Add Blank Favicon (prevents 404 if no favicon is set)
- Change Login URL (security through obscurity)

### 3.2 Script Manager (Killer Feature)

The flagship feature. A front-end overlay panel that lists every JavaScript and CSS asset loading on the current page/post, grouped by their originating plugin or theme.

**Access methods:**
- Admin bar: Hover "Perfmatters" > "Script Manager"
- URL parameter: append `?perfmatters` to any URL

**What it displays per asset:**
- Script/style handle name
- Source URL
- Whether it's in header or footer
- On/Off toggle
- Which plugin/theme it belongs to

**Disable scope options (per asset or per plugin group):**
1. Everywhere (site-wide)
2. Current URL (single page/post)
3. By post type (all pages, all posts, any custom post type)
4. By regex pattern
5. By device type (desktop only / mobile only)
6. By login state (logged in / logged out)
7. With exceptions (e.g., disable everywhere except /cart)

**Global View:** A separate dashboard panel showing all Script Manager configuration across the entire site — deletable rows, stale post ID cleanup.

**MU Mode (Must-Use Plugin mode):**
- Copies `perfmatters_mu.php` into `/wp-content/mu-plugins/`
- Intercepts plugins at the WordPress loading level — before hooks fire
- Effect: disables the ENTIRE plugin per URL/post-type, not just its enqueued assets
- Catches inline CSS/JS, database queries, action hooks — not just enqueued scripts
- Critical for plugins that don't properly enqueue assets
- Requires write permissions to mu-plugins directory
- Warning: reviewing existing script manager settings before enabling is mandatory

**Testing safeguards:**
- Test mode: changes only visible to logged-in admins, not live to public
- Safe mode: can be activated via settings, URL parameter, or PHP constant

**Dependency awareness:** Shows which scripts depend on others (e.g., jQuery). Prevents accidental dequeue of dependencies.

**Settings panel within Script Manager (accessed from front-end panel):**
- Enable/disable MU mode
- Show dependencies toggle
- Include archives
- Disclaimer toggle

### 3.3 Delay JavaScript

- Delays all (or selected) JavaScript until user interaction: scroll, mouse move, click, keyboard
- Configurable fallback timeout: default 15 seconds (increased from 10s in recent update for broader compatibility)
- Per-script exclusion rules on specific pages
- `perfmatters_delay_js_timeout` filter for developers to customize timeout
- Targets: third-party scripts like GTM, GA, Facebook Pixel, AdSense, WooCommerce cart fragments

### 3.4 Defer JavaScript

- Defers JS to load non-blocking after HTML parsing
- Separate from "delay" — defer loads after parse, delay loads after interaction
- Inline script exclusion patterns available

### 3.5 Remove Unused CSS (Used CSS)

- Scans pages and generates a stylesheet containing only the CSS rules actually used
- **Default method:** Inlines used CSS in `<style id="perfmatters-used-css">` in `<head>` — best for PageSpeed scores
- **File method:** Stores used CSS in an external cacheable file — better for page size on repeat visits
- Generation strategy by content type:
  - Pages: generated separately per page (high CSS uniqueness)
  - Posts: generated once for all posts (shared CSS)
  - Custom post types: separately per type
  - WooCommerce products: separately per product type (simple, variable, grouped, external)
  - Archives: once per category
- Unused original stylesheets are deferred and loaded on user interaction (or async)
- `perfmatters_rucss_logged_in` filter to control used CSS generation for logged-in users

### 3.6 Minification

- Minify JS files
- Minify CSS files
- Combine JS files option
- Combine CSS files option

### 3.7 Lazy Loading

- Images (with threshold control)
- Videos
- iFrames
- CSS backgrounds
- DOM monitoring for dynamically added elements
- YouTube iframe optimization (loads lite/placeholder until clicked)
- Elementor Atomic YouTube elements auto-optimized (v2.6.2+)
- Claimed faster than WordPress native lazy loading implementation

### 3.8 Preloading & Resource Hints

- **Preload Critical Images:** Auto-preloads 0-5 leading images per page (typically logo + hero image) — generates `<link rel="preload">` tags
- **Early Hints (HTTP 103):** Via Cloudflare integration — sends resource hints to browser before server response. Out of beta as of Jan 2026. Requires Cloudflare (all plans including free)
- Preconnect hints for specific domains
- DNS prefetch for domains
- Fetch priority control for resources

### 3.9 Local Google Analytics

- Hosts GA4 script on your own server instead of Google's CDN
- **Two script options:**
  - `gtagv4.js` — full GA4 tracking tag (51.5 KB)
  - `analytics-minimal-v4.js` — community-maintained minimal implementation (2.2 KB), reports pageviews, users, locations, devices, traffic sources, real-time
- Auto-IP anonymization (GDPR compliance)
- Admin tracking toggle
- MonsterInsights integration
- Script position: header or footer
- Cron job updates local copy daily from Google servers
- Note: not officially supported by Google, but stable in practice

### 3.10 Local Google Fonts

- Downloads and self-hosts Google Fonts to reduce external requests
- WP-CLI command to clear cached fonts across multisite
- Removes Google Fonts from output entirely as a fallback toggle

### 3.11 CDN Integration

- URL rewriting for CDN asset serving
- Configurable CDN URL
- Custom file extension inclusion/exclusion

### 3.12 Database Optimization

- Clean post revisions
- Clean auto-drafts
- Clean spam comments
- Clean expired transients
- Table optimization
- Scheduled automatic cleanup (daily / weekly / monthly)
- Set limits on future auto-saves and revisions

### 3.13 Code Snippets Manager (Added v2.5.x, now stable)

Added November 2025, out of beta by 2026:

- Create and manage PHP, JS, CSS, and HTML code snippets in-dashboard
- **Flat-file approach:** Snippets written to disk on save, zero database calls at runtime. OPcache compiles once and serves from memory
- ~20.5% less backend execution time vs database-based snippet plugins
- Conditions builder: choose where/when snippet runs
- Per-snippet loading options: defer, async, delay on interaction, preload, minify, file vs inline
- Error checking and safe mode to prevent site-breaking snippets
- Import/export functionality
- Admin bar quick access menu item
- Shortcode support for HTML snippets (v2.6.3)
- Custom CodeMirror 5 theme for code editor

### 3.14 Developer Features

- **WP-CLI support:** Activate/deactivate plugin, manage license, clear used CSS, clear Google Fonts (with multisite support via `--network` flag)
- **WordPress Multisite:** Network-level settings, push config to subsites, Super Admin vs Site Admin access control
- **PHP filters:** `perfmatters_delay_js_timeout`, `perfmatters_rucss_logged_in`, and numerous others for customization
- **WordPress 7 compatibility:** UI adjustments in v2.6.1 preparing for WordPress 7
- **PHP 8.1+ required** (as of v2.6.0, March 2026)

---

## 4. Admin Panel / UX

### 4.1 Information Architecture (Settings Tabs)

8 tabs, each focused on a distinct concern:

1. **General** — Core WordPress bloat removal toggles (20+ individual options)
2. **Assets** — Delay JS, defer JS, minify/combine JS+CSS, remove unused CSS
3. **Preloading** — Critical images, Early Hints, preconnect, DNS prefetch, fetch priority
4. **Lazy Loading** — Images, iframes, videos, CSS backgrounds, DOM monitoring
5. **Fonts** — Local Google Fonts, disable Google Fonts
6. **CDN** — CDN URL rewrite settings
7. **Analytics** — Local GA4 hosting, script type, IP anonymization
8. **Database** — Cleanup options + scheduling

Plus: **License** tab (activation/deactivation), **Tools** tab (import/export, reset options)

### 4.2 Design Language

- Clean, minimal, wp-admin native look (no custom CSS framework fighting with wp-admin)
- Checkbox/toggle-heavy — each feature is a discrete on/off
- Inline help text beneath each option explaining "what it does AND when not to enable it"
- No modal upsells, no nag banners, no feature paywalls
- No gamification or score-based UX (contrast: WP Rocket's "Rocket score" or some plugins' pagespeed integration)
- Options grouped by function, not by "beginner/advanced" (which can be daunting for newcomers)

### 4.3 Onboarding Approach

**No wizard, no guided tour.** The plugin activates with ALL features OFF. Users must:
1. Enter license key
2. Manually navigate tabs and enable desired features
3. Test after each group of changes
4. Clear cache between tests

This is a deliberate philosophy: "Enable what you understand, test, iterate." The tradeoff is a higher bar for beginners who may enable features blindly.

**Documentation compensates for the no-wizard approach:** 120+ documentation articles, each explaining not just "what" but "when" and "potential side effects." The docs are the onboarding flow.

**Recommended sequence from official docs:**
1. Start with General tab — safe toggles
2. Enable Script Manager
3. Use Script Manager before enabling Assets tab features (to avoid double-disabling)
4. Test in test mode before publishing changes
5. Clear cache between each test
6. Check for duplicate feature conflicts with your caching plugin

### 4.4 Script Manager UX (Front-End Overlay)

This is the most distinctive UX element:

- **Lives on the front end, not wp-admin** — you browse to a page, then open the panel
- Admin bar shows a "Perfmatters" menu item
- Clicking opens an overlay panel anchored to the top of the page
- Panel shows all loading assets in a scrollable list, grouped by plugin/theme
- Each group has a header with the plugin name
- Each asset has: handle, URL (shortened), location (header/footer), toggle
- Toggling opens a sub-menu: scope selector (Everywhere / Current URL / Post Type / Regex)
- Additional sub-options: device type (desktop/mobile), login state
- "Test mode" toggle in the panel makes changes admin-only
- "Settings" section within the panel for MU mode, dependency display, etc.
- "Global View" link opens a wp-admin page showing all current Script Manager rules

**UX strengths:**
- In-context: you see the exact page and its assets together
- Grouped by plugin: immediately obvious which plugin loads what
- Reversible: test mode prevents live impact
- No need to know script handles — visual discovery

**UX weaknesses:**
- Not accessible from wp-admin dashboard (must browse to actual page/post)
- Can feel overwhelming on content-heavy pages with 30-50 scripts
- No bulk-disable by category (e.g., "disable all tracking scripts")
- Regex feature is powerful but unfriendly to non-developers
- No search/filter within the Script Manager panel

### 4.5 Patterns Worth Noting

- **Front-end contextual tooling:** Settings live where they apply (on the actual page), not abstracted away in wp-admin
- **Plugin-grouped asset view:** Groups scripts by originating plugin, not alphabetical or by file path
- **Dual-disable architecture:** Regular mode for enqueued assets, MU mode for entire plugin loading — different technical mechanisms for different problem depths
- **Conservative defaults (all off):** Respects existing site state, avoids breaking on activation
- **Documentation as UX:** Very detailed inline explanations treat ignorance as a UX problem to solve in text

---

## 5. AI / Automation

**There is no AI in Perfmatters.** None. No LLM integration, no machine learning, no smart recommendations, no automated script analysis.

Automation exists only as:
- Database cleanup **scheduling** (daily/weekly/monthly cron)
- Daily cron to refresh local GA4 script from Google
- Automatic used CSS generation on first page load after cache flush

All optimization decisions are made by the human user. The Script Manager presents data and controls, but the "what to disable" judgment is entirely manual. This is both a design philosophy (trusted developer knows their site) and a gap a competitor could exploit.

**Marketing-vs-reality verdict:** No AI marketing claims — Perfmatters is honest that it is a manual tool. Zero "AI-powered" language on their site.

---

## 6. Performance Methodology

Perfmatters' methodology is **surgical subtraction** — remove or defer everything that isn't needed, on the pages where it isn't needed.

Core principles:
1. **Reduce HTTP requests** — fewer scripts = faster initial load
2. **Eliminate render-blocking resources** — defer/delay JS and async CSS
3. **Minimize DNS lookups** — localize GA, localize Google Fonts
4. **Right-size CSS** — serve only used CSS per page type
5. **Defer below-fold content** — lazy load everything not immediately visible
6. **Front-load critical resources** — preload LCP images, Early Hints for key assets
7. **Clean the data layer** — database optimization removes bloat that slows queries

**What it does NOT do:**
- Page caching (HTML cache)
- Browser cache headers
- Gzip/Brotli compression
- Object caching (Redis/Memcached)
- Image compression or WebP conversion
- CDN provisioning (only URL rewriting for an existing CDN)
- Server-level optimizations

The methodology explicitly requires pairing with a caching plugin. Perfmatters is the "optimizer" layer, not the "delivery" layer. The recommended professional stacks are:
- WP Rocket + Perfmatters (most common)
- FlyingPress + Perfmatters
- LiteSpeed Cache + Perfmatters
- W3 Total Cache + Perfmatters

---

## 7. Nginx Support

**Direct nginx support: minimal to none.**

Perfmatters is a PHP/WordPress-layer plugin. It does not write or manage server configuration files. The features it delivers are server-agnostic:
- JS/CSS manipulation happens in WordPress PHP hooks
- HTML rewriting (lazy loading, preloading) happens at PHP output buffering
- Database optimization is pure MySQL
- Script Manager is purely WordPress-side (dequeue hooks)

**Impact on nginx users:**
- All core features work on nginx — because they don't touch `.htaccess` or server config at all
- The plugin does not generate `.htaccess` rules (no gzip, no browser cache headers, no rewrite rules)
- This means nginx users must handle compression and browser cache headers separately at the server/vhost level — this is true for all hosting environments

**Early Hints caveat:** The Early Hints feature specifically requires Cloudflare. It does not work with self-managed nginx servers unless they have Cloudflare in front. A site running nginx without Cloudflare cannot use Early Hints via Perfmatters.

**Bottom line for SwiftPress:** Perfmatters' approach (PHP-layer only, no server config) means it works equally well on nginx and Apache — but also means it can't help with server-level performance tuning on either. SwiftPress, which targets nginx specifically, should be a superset here.

---

## 8. Weaknesses and Honest Gaps

1. **No caching.** This is by design, but it means Perfmatters cannot stand alone. Beginners often don't understand this and are disappointed when page speed doesn't dramatically improve without a cache layer.

2. **No image optimization.** No compression, no WebP conversion, no responsive image generation. A significant omission for most real sites where images are the largest assets.

3. **No guided onboarding / wizard.** Starts with everything off. Beginners face a wall of checkboxes. The documentation is excellent but is not a substitute for in-product guidance.

4. **Script Manager is front-end only.** You can't manage scripts from wp-admin. If you're in wp-admin and want to check a page's scripts, you must navigate to that page. Not a dealbreaker, but adds friction.

5. **Script Manager can break sites.** Power comes with responsibility. Disabling the wrong dependency can cause visible JS errors. The test mode helps but doesn't prevent mistakes — just limits their blast radius.

6. **No search in Script Manager.** On sites with 40+ assets loading, finding a specific script requires scrolling. No filter/search bar.

7. **No CDN provisioning.** Only URL rewriting. You still need to set up a CDN separately.

8. **Restrictive refund policy.** The 30-day guarantee excludes third-party plugin conflicts (which is the most common actual complaint), requires documented support attempts, and doesn't apply to renewals. Some reviewers flag this as misleading.

9. **No live chat support.** Email only. For paid software, this is a weak point.

10. **Cloudflare dependency for Early Hints.** No Early Hints on self-managed servers without Cloudflare in front.

11. **PHP 8.1+ required as of March 2026.** Sites still running older PHP (there are many) cannot upgrade Perfmatters.

12. **MU mode setup friction.** Requires write permissions to mu-plugins. On some managed hosts or security-hardened setups, this fails silently and requires SFTP workaround.

13. **No bulk script actions.** Can't select multiple scripts and disable all at once. Must toggle each individually.

14. **No AI or smart recommendations.** Identifying which scripts to disable is entirely manual — no suggestion engine, no automated audit, no "safe to disable" confidence scoring.

---

## 9. Ideas Worth Borrowing for SwiftPress

### Idea 1: Front-end-contextual Script Manager overlay
**The concept:** A toggle panel that appears in the admin bar on the front end, showing all enqueued JS/CSS grouped by plugin, with per-page/per-device/per-login-state granularity.  
**Why it matters:** wp-admin abstraction loses the page context. Seeing scripts in-situ (on the actual page) makes the relationship between "this script" and "this visual element" obvious.  
**Effort:** High — requires front-end panel UI, PHP enqueue introspection, per-URL database storage for rules, and a mu-plugin architecture for full-plugin disabling. But this is Perfmatters' #1 competitive moat.

### Idea 2: MU mode for true plugin-level disabling
**The concept:** A must-use plugin that intercepts WordPress plugin loading before hooks fire, enabling complete plugin suppression per URL/post-type — not just dequeuing enqueued assets.  
**Why it matters:** Many plugins use inline scripts or action hooks instead of proper enqueue. A dequeue-only approach misses these. MU mode kills them at the PHP include level.  
**Effort:** Medium — the technical mechanism is well-understood (mu-plugins directory, plugin loader filtering). The hard part is the UI to manage it and safe guards to prevent site-breaks.

### Idea 3: Flat-file code snippets with per-snippet load optimization
**The concept:** A code snippet manager that writes PHP/JS/CSS/HTML to disk (not database), uses OPcache, and lets you apply per-snippet performance options (defer, async, delay on interaction, minify).  
**Why it matters:** Eliminates DB read on every page load. The AI opportunity: SwiftPress could add LLM-assisted snippet generation — user describes what they want ("add Google Tag Manager with consent check") and gets the snippet, then can configure its loading behavior.  
**Effort:** Medium (flat-file mechanism is straightforward; the LLM integration wrapper is the SwiftPress-specific addition).

### Idea 4: Minimal local analytics option (2.2 KB vs 51.5 KB)
**The concept:** Offer a minimal open-source analytics implementation as an alternative to full GA4, self-hosted on user's server. The 2.2 KB analytics-minimal-v4.js provides pageviews, users, locations, devices, traffic sources — without the 51.5 KB gtag overhead.  
**Why it matters:** Removes Google's third-party dependency entirely, GDPR-friendly, eliminates render-blocking external DNS, works on nginx without Cloudflare.  
**Effort:** Low — the script is open source, the cron to refresh it is a standard WordPress cron, GA4 measurement ID is the only user input needed.

### Idea 5: Per-asset confidence scoring via AI
**The concept:** When displaying scripts in the Script Manager, run a lightweight LLM call (e.g. Gemini Flash at ~$0.0001/call) to analyze the script handle name, source URL, and plugin attribution — then display a "safe to disable" confidence level and a one-line explanation.  
**Why it matters:** Removes the "expert knowledge required" barrier that makes Script Manager intimidating to non-developers. A beginner would see: "jquery-ui-dialog — 82% safe to disable on blog posts: only needed on pages with interactive dialogs. Check your contact page."  
**Effort:** Medium — the AI call is simple (handle name + URL + plugin name as context); the challenging part is the UI (where to surface the score, how to handle uncertainty, how to avoid false confidence).

---

## 10. Competitive Context for SwiftPress

| Capability | Perfmatters | SwiftPress Target |
|-----------|-------------|-------------------|
| Full-page cache | No | Yes |
| Object cache | No | Yes |
| Browser cache headers | No | Yes (nginx config) |
| Script Manager | Yes (best-in-class) | Needs this |
| MU-mode plugin disabling | Yes | Needs this |
| JS delay/defer | Yes | Yes |
| Remove unused CSS | Yes | Yes |
| Local GA | Yes (basic) | Can go further |
| Minify/combine | Yes | Yes |
| Image optimization | No | Opportunity |
| CDN | URL rewrite only | Yes |
| AI features | None | Yes (OpenRouter) |
| nginx-native support | Works (PHP-layer) | Full native target |
| Code snippets | Flat-file, stable | Could add AI gen |
| Pricing | $24.95-$124.95/yr | Competitive needed |

SwiftPress has the opportunity to be the **first plugin that does what Perfmatters + WP Rocket do together, plus adds AI-powered script analysis** — all in one GPL plugin with nginx-first architecture.

---

## Sources

- https://perfmatters.io/features/
- https://perfmatters.io/docs/disable-scripts-per-post-page/
- https://perfmatters.io/docs/mu-mode/
- https://perfmatters.io/docs/changelog/
- https://perfmatters.io/docs/local-analytics/
- https://perfmatters.io/docs/delay-javascript/
- https://perfmatters.io/docs/remove-unused-css/
- https://perfmatters.io/docs/preload/
- https://perfmatters.io/docs/early-hints/
- https://blogginglift.com/perfmatters-pricing/
- https://darrelwilson.com/review/perfmatters-review/
- https://gauravtiwari.org/perfmatters-review/
- https://onlinemediamasters.com/perfmatters-settings/
- https://worldpressit.com/perfmatters-plugin-review-the-lightweight-script-manager-wordpress-needs/
- https://stylemywp.com/review/perfmatters/
- https://runcloud.io/blog/perfmatters-plugin
- https://x.com/brianleejackson/status/1991572890349892027
