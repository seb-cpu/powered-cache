# FlyingPress — Competitor Dossier
*Researched: 2026-06-08 | Version current as of: v5.5.0 (June 2, 2026)*

---

## 1. Market Positioning

**Tagline:** "Lightning-Fast WordPress on Autopilot"

FlyingPress positions itself as the modern, real-user-centric alternative to WP Rocket. Its core differentiator is not just synthetic PageSpeed scores but leading the Chrome UX Report (CrUX) for Core Web Vitals pass rates — a claim backed by HTTP Archive data showing ~54% of FlyingPress-using sites passing all three CWVs, versus ~52% for WP Rocket and ~56% for NitroPack.

**Target buyer:** Site owners, developers, and agencies who want measurable real-user improvement, not just green lab scores. Heavy emphasis on WooCommerce + Elementor/page builder compatibility, where competitors often struggle.

**Key brand claims:**
- #1 in Core Web Vitals based on real Chrome user data (CrUX / HTTP Archive)
- "30+ optimizations in one plugin"
- "Easiest to use and set up" — deliberately fewer settings than WP Rocket
- No need for separate CDN subscription (vs Cloudflare APO at $5/month)
- Cloud-based heavy lifting (unused CSS, image compression) so your host's CPU is spared

---

## 2. Pricing Tiers (2026)

Prices shown are annual. FlyingPress has updated its plan names and structure since older reviews.

| Plan | Sites | Price/year |
|---|---|---|
| Starter | 1 | $59 |
| Pro | 3 | $109 |
| Business | 10 | $229 |
| Unlimited | Unlimited | $279 |

- **14-day free trial** — card required but not charged until trial ends, cancel anytime.
- **Staging sites** do not count toward license limits.
- **Upgrades** are prorated (pay only the difference).
- All plans include: image optimization, Core Web Vitals tracking, cloud optimizer.

**Older pricing found in some reviews** (Personal $60/Developer $150/Professional $250/Agency $500) appears to reflect a previous tier structure. The current live pricing page shows the Starter/Pro/Business/Unlimited model above.

### FlyingCDN Add-On

FlyingCDN is a separate, optional product — exclusively available to FlyingPress customers.

| | |
|---|---|
| Base cost | $10/domain/month |
| Included bandwidth | 100 GB |
| Extra bandwidth | $5 per 100 GB |
| Free trial | 7 days (no credit card) |
| Infrastructure | Cloudflare Enterprise (previously ran on BunnyCDN) |
| Edge locations | 310 PoPs in 120+ countries |
| TTFB | <50ms globally |

FlyingCDN adds full HTML page caching at the edge, image WebP/AVIF delivery, logged-in user caching, and DDoS protection — capabilities BunnyCDN requires paid add-ons for separately.

---

## 3. Complete Feature List

### 3.1 Caching

- **Full-page cache** — generates static HTML, stores at `/wp-content/cache/flying-press/`. Served directly by nginx or PHP.
- **Cache preloading** — auto-generates cache on content changes without manual action.
- **Browser caching** — correct cache-control headers for repeat visitors.
- **Separate mobile cache** — distinct cached pages AND distinct optimization sets (CSS/JS) for mobile vs. desktop. Critical differentiator since v5.0.
- **Cache for logged-in users by role** — useful for membership or WooCommerce logged-in accounts.
- **Cache exclusions** — exclude by URL patterns (no regex support — a known gap).
- **Cookie-based bypass** — WooCommerce cart, EDD, comment author, postpass cookies skip cache.
- **Auto-refresh cache** — configurable intervals.
- **Link preloading** — browser Speculation Rules API used to preload pages on hover/scroll before user clicks (replaces old `<link rel=prefetch>` approach).
- **Cloudflare full-page cache** — native integration purges HTML pages at Cloudflare edge without APO subscription. Syncs exclusions, ignored query strings, bypass cookies automatically.

### 3.2 CSS Optimization

- **Minify CSS** — removes whitespace, comments.
- **Remove Unused CSS (UCSS)** — cloud-based: pages are rendered in a real browser environment on FlyingPress cloud servers (6 global regions) to detect truly unused rules including dynamic/JS-injected CSS. Three modes: complete removal, async load, on-interaction load.
- **Critical CSS generation** — inlines above-the-fold CSS for fast first render; per-page generation.
- **Self-host external CSS** — downloads third-party stylesheets (jsDelivr, cdnjs, unpkg, jQuery CDN, FontAwesome, Bootstrap CDN, etc.) locally. Custom domain list via filter.
- **CSS lazy render** — defers rendering of below-fold HTML sections using CSS selectors (e.g., `#footer`, `#comments`, `.elementor-section`). Pre-assigns dimensions to prevent CLS. Can reduce DOM complexity by up to 60% for heavy page-builder pages.

### 3.3 JavaScript Optimization

- **Minify JS** — removes whitespace.
- **Defer JavaScript** — adds `defer` attribute to render-blocking scripts.
- **Delay All JavaScript** — two modes: "Load on idle" (fires after browser idle event) or "Load after interaction" (fires on first user gesture — most aggressive, for simple sites).
- **Third-party script detection and delay** — auto-identifies analytics, ads, social widgets (GA4, GTM, Facebook Pixel, etc.) and delays them on interaction. No manual list required.
- **Self-host external JS** — same as CSS self-hosting, downloads third-party scripts locally.
- **Script exclusions** — exclusion list only (no per-page/per-post script manager — requires Perfmatters as companion for granular control).

### 3.4 Fonts

- **Self-host Google Fonts** — downloads font files locally, rewrites stylesheet references. Eliminates DNS lookups to fonts.gstatic.com and fonts.googleapis.com.
- **Preload fonts** — auto-detects actively-used fonts and adds preload hints.
- **System fonts first** — `font-display: swap` with system fallback during font load to reduce CLS.
- **Combine Google Fonts** — merges multiple Google Font API calls into one request.

### 3.5 Images

- **Lazy load images** — defers off-screen images. Smart detection: auto-excludes above-fold hero images from lazy loading.
- **Lazy load iFrames** — defers YouTube embeds and other iFrames.
- **Lightweight YouTube previews** — self-hosts YouTube thumbnail images; replaces iframe with click-to-load UI. Eliminates ~600KB of YouTube script on initial load.
- **Layout shift reduction** — auto-adds width/height attributes to images without explicit dimensions.
- **Self-host Gravatar images** — downloads comment avatars locally.
- **Image optimization (v5.3+, Jan 2026)** — cloud-based compression pipeline:
  - Formats: AVIF, WebP, or keep original.
  - Compression: lossy or lossless.
  - Auto-optimize new uploads option.
  - Restore originals anytime.
  - Processing on FlyingPress cloud (not your host).
  - Functionally replaces ShortPixel/Imagify for most sites, though dedicated image plugins may achieve slightly better compression ratios.

### 3.6 Database Optimization

- Delete post revisions, drafts, trashed posts.
- Remove spam and trashed comments.
- Clean expired transients.
- Optimize and repair database tables.
- Scheduled auto-cleanup (autopilot mode).

### 3.7 WordPress Bloat Reduction

- Disable XML-RPC.
- Remove emoji scripts.
- Remove Dashicons from frontend.
- Remove jQuery Migrate.
- Remove WordPress version from headers.
- Disable embeds/oEmbeds.

### 3.8 Monitoring & Analytics

- **Real-User Core Web Vitals Tracking (v5.2+, Sep 2025):**
  - Lightweight async `vitals.min.js` injected on all public pages.
  - Collects LCP, INP, CLS, TTFB from real Chrome users.
  - Dashboard "Vitals" tab inside WP admin.
  - Filter by: device (mobile/desktop), time range (1h / 24h / 7d / 30d), page-level, country-level.
  - No PII collected. Data stored on FlyingPress servers, linked to license key + domain.
  - Included in all plans, no extra cost.

### 3.9 Cloudflare Integration (v5.1+, Aug 2025)

- Full HTML page caching at Cloudflare edge (no APO required, works on free Cloudflare plan).
- Auto zone detection (v5.5).
- Syncs all exclusions, cookie bypasses, query string rules bidirectionally.
- Intelligent cache purge: HTML-only purge vs. everything purge.
- Separate mobile cache flag pushes "Cache by Device Type" to Cloudflare automatically.

### 3.10 Developer Features

- WP-CLI support.
- 15 developer reference articles covering hooks, filters, APIs.
- Custom filter to extend self-hosted domain lists.
- Configuration import/export.
- nginx native cache serving config (community-provided gist + official docs).

---

## 4. Admin Panel / UX Analysis

### 4.1 Overall Look and Feel

FlyingPress's panel is described consistently across reviews as "minimalist and clean" — a deliberate contrast to WP Rocket's "colorful dashboard-style" interface. The design philosophy is fewer settings with smarter defaults, so users can get results without needing to understand performance theory.

The interface is tab-based with clear section headings. Each setting includes an inline tooltip. Advanced options are visible (not buried behind "show advanced" toggles), which differentiates it from beginner-first tools that hide power features.

### 4.2 Tab / Information Architecture

Based on documentation and reviewer walkthroughs, the sidebar/tab structure in v5 is:

1. **Dashboard** — Cache stats (total pages cached, queue length), quick actions: Preload All, Purge All, Preload Selected, Purge Selected.
2. **Optimization** — The main consolidated tab (v5 redesign collapsed multiple tabs into one):
   - CSS: minify, remove unused, critical CSS
   - JS: minify, defer, delay mode selector, third-party detection
   - Images: lazy load, YouTube previews, Gravatar self-host, image compression
   - Fonts: Google Fonts self-host + preload, system fonts first
   - iFrames: lazy load
   - Self-hosting: external CSS/JS local hosting
   - Lazy Render: CSS selector input for below-fold elements
3. **Caching** — Page exclusions, separate mobile cache, logged-in user cache, auto-refresh, query string handling, cookie bypasses.
4. **CDN** — FlyingCDN API key, Cloudflare integration, BunnyCDN or custom CDN URL rewriting.
5. **Database** — Cleanup options with scheduling.
6. **Settings** — License key, config import/export, cache purge triggers, Core Web Vitals tracking enable/disable.
7. **Vitals** (dedicated tab since v5.2) — Real-user CWV dashboard with filters.

### 4.3 Onboarding Flow

Install → Activate → The plugin prompts a step-by-step sequence:
1. Enable caching
2. Turn on CSS optimization
3. Enable JavaScript delay
4. Activate lazy loading

No wizard in the traditional sense — it's a logical top-to-bottom settings flow. Setup described as taking under 10 minutes for basic configuration. More complex sites (page builders, WooCommerce) require additional tuning (CSS exclusions, JS exclusions).

The recommended setup from the community involves installing companion plugin Perfmatters alongside FlyingPress for per-page script management, which the plugin itself lacks.

### 4.4 Smart Defaults

- Cache preloading: on by default.
- Lazy load: on by default, with smart above-fold detection (no manual exclusion needed for hero images).
- Google Fonts self-hosting: on by default in recommended config.
- Third-party script detection: automatic (no manual list).
- Cloud-based unused CSS: on by default in v5 (no server-side processing needed).

### 4.5 v5 Dashboard Improvement (May 2025)

The v5.0 redesign brought:
- Real-time URL queue visibility (see which pages are being processed for UCSS).
- Settings save without triggering cache purge — big QoL improvement for iterating on settings.
- Consolidated optimization settings into single tab.

---

## 5. Performance Methodology

FlyingPress's philosophy: **real-user field data first, synthetic scores second.**

The company explicitly states "We focus on enhancing real-world experiences, aligned with metrics valued by Google — not just synthetic scores." Their CrUX-based #1 ranking claim (54% pass rate) is derived from HTTP Archive data, not their own tests.

### Technical stack:
- **CloudOptimizer** (v5.0): Cloud infrastructure across 6 global regions (LA, DC, Amsterdam, Frankfurt, Singapore, Mumbai) handles resource-intensive tasks: unused CSS analysis runs in a real browser environment (headless Chrome equivalent) so dynamically generated CSS is properly detected. Auto-scales. If cloud is down, site continues to function normally.
- **Separate mobile/desktop optimization sets**: Different CSS (unused CSS removal results), different lazy render targets, different cache files — recognizing that a mobile and desktop layout are fundamentally different DOMs.
- **Browser Speculation Rules API**: Used for link preloading, more efficient than old `<link rel=prefetch>` approach.
- **Lightweight queue system** (v5.5): Replaced Action Scheduler (WooCommerce's heavy job library) with in-house lightweight queue — reduces DB overhead.
- **GZIP pre-compression**: Cache files are pre-compressed, reducing cache file size ~80% and improving nginx direct serve speed.

### Benchmarks from independent tests (2026):
- WooCommerce/Elementor site: FlyingPress 99/100 mobile PSI vs WP Rocket 91/100; INP 60ms vs 150ms; TBT 10ms vs 271ms.
- Typical before/after: TTFB 0.794s → 0.280s; LCP 1.494s → 0.580s; Full load 2.751s → 1.039s; HTTP requests 60 → 20.
- Load test (250 concurrent): unoptimized site crashes; FlyingPress maintains ~38ms average response.

---

## 6. Nginx Support

FlyingPress has **solid nginx support** but requires manual server config for optimal performance.

### How it works:
- **Default mode (PHP serving):** Works on nginx out of the box. Cache files stored at `/wp-content/cache/flying-press/$http_host/$request_uri/index.html`. PHP intercepts requests and serves cached HTML. Works everywhere, no server config needed.
- **Nginx direct serving (recommended for performance):** An nginx config block must be added to the server block. This bypasses PHP entirely for cached pages, serving pre-generated HTML files directly from disk — fastest possible TTFB.

### Nginx config logic (from community gist + official docs):
```nginx
# Cache disabled when:
# - POST request
# - Query string present ($is_args)
# - Logged-in cookies present (wordpress_logged_in_*, postpass_*, comment_author_*)
# - WooCommerce cart cookie (woocommerce_items_in_cart)
# - EDD items in cart cookie
# - Cached file doesn't exist

# Cache enabled when all above false → rewrite to:
# /wp-content/cache/flying-press/$http_host/$request_uri/index.html

# Headers added for served cached files:
X-Flying-Press-Cache: HIT
X-Flying-Press-Source: Nginx
Cache-Control: no-cache, must-revalidate, max-age=0
```

GZIP pre-compressed cache files are served with `gzip_static on` directive.

### Compatibility notes:
- Compatible with nginx Helper plugin.
- Official docs include nginx configuration article.
- Bitnami stack explicitly not supported.
- LaunchWP.io hosting has a dedicated FlyingPress + nginx setup guide.
- RunCloud, GridPane, SpinupWP users have community guides.

### Bottom line on nginx:
FlyingPress works well on nginx. It's not as turnkey as LiteSpeed (which has its own cache driver), but the nginx direct-serve config is straightforward and well-documented. For nginx + Redis object cache combinations, FlyingPress reportedly beats WP Rocket by 100–200ms on WooCommerce TTFB.

**Gap:** No built-in Redis/Memcached object cache integration. This is a documented weakness — the plugin only handles full-page (HTML) cache, not object cache.

---

## 7. AI / Automation Honesty Check

**Is there real AI/ML? No.**

FlyingPress's marketing language around "CloudOptimizer" and "intelligent optimization" describes **cloud-based rule-based automation**, not machine learning or LLMs.

What it actually does:
- Renders pages in headless browsers (real browser environment) on their cloud servers to detect unused CSS — this is deterministic analysis, not ML.
- "Automatic" third-party script detection uses pattern matching against a known list of CDN domains and script patterns.
- "Smart" lazy render detection (v5.0) identifies above-fold vs. below-fold elements using viewport intersection calculations — standard browser API, no ML.
- "Auto" font preloading detects fonts referenced in CSS — rule-based.

The v5.0 announcement blog post explicitly makes **no claims about AI or machine learning**. The word "AI" does not appear in their changelog or features page. This is honest positioning — they don't use the term.

**Conclusion:** FlyingPress automates correctly; it does not apply AI. This is a genuine opportunity for SwiftPress.

---

## 8. Honest Weaknesses

1. **No per-page/per-post script manager.** You cannot disable a specific plugin's scripts on specific pages from within FlyingPress. This requires the paid Perfmatters companion plugin ($25/year). WP Rocket has a per-page script exclusion UI built in.

2. **No regex for cache exclusions.** Exclusion rules are string-match only. Competing plugins (WP Rocket, Cache Enabler) support regex. Sites with complex URL patterns (e.g., faceted navigation) need workarounds.

3. **No Redis/Memcached object cache.** FlyingPress only handles full-page HTML cache. Sites with database-heavy operations (WooCommerce, membership) need a separate object cache plugin (Redis Object Cache, W3TC).

4. **Remove Unused CSS can break sites.** Despite cloud rendering, page builders (Elementor, Divi) with dynamic widget states often require manual CSS exclusion lists. First-time setup can break site visuals. Documentation gives guidance but it's still manual.

5. **Cloud dependency risk.** Critical features (Unused CSS removal, Image optimization) depend on FlyingPress's cloud servers. If their infrastructure has downtime, these features degrade silently (fallback exists for site serving, but optimization quality drops).

6. **FlyingCDN requires DNS changes + technical knowledge.** Not a one-click activation. Users on shared cPanel hosting or Squarespace-migrated domains may struggle. $10/month minimum makes it expensive for hobby sites.

7. **Smaller community than WP Rocket.** Fewer third-party tutorials, YouTube walkthroughs, forum threads. Support docs described as "thin" for edge cases.

8. **No free version.** No freemium tier. 14-day trial exists but requires a credit card. Prevents WordPress.org directory listing discovery.

9. **FlyingCDN is locked to FlyingPress.** Cannot use FlyingCDN without FlyingPress. Switching away from the plugin means losing CDN setup entirely.

10. **Renewal discount not transparent.** Some older sources show different renewal pricing vs. first-year pricing, but the current pricing page doesn't clearly show renewal rates.

---

## 9. UX Patterns Worth Noting

### 9.1 Smart Above-Fold Detection (Steal This)
A single "Lazy Load" checkbox automatically determines which images are above-fold vs. below-fold, excludes above-fold from lazy loading, and preloads them with high priority. No user input required for the common case. Before v5, users had to manually specify CSS selectors to exclude from lazy load.

### 9.2 Real-Time Queue Visibility
The Dashboard tab shows a live queue of pages being processed by CloudOptimizer. Users can see "Page X is being optimized" rather than waiting in the dark. This prevents support tickets about "why is UCSS not working yet."

### 9.3 Settings Save Without Cache Purge
Changing a setting and saving does not trigger a full cache purge + preload cycle. This is a QoL improvement that saves developers minutes per configuration iteration. WP Rocket and other plugins often wipe the cache on every save.

### 9.4 Vitals Tab as Feedback Loop
The built-in CWV monitoring tab creates a tight feedback loop: make optimization changes → wait → see LCP/INP improve in real user data. This keeps users engaged with the plugin dashboard rather than bouncing between PSI, Search Console, and the plugin.

### 9.5 Device-Specific Optimization
The "Separate Mobile Cache and Optimizations" toggle is one checkbox that cascades into: separate HTML cache, separate unused CSS removal result, and separate Cloudflare cache rules. The complexity is hidden behind a single decision.

### 9.6 Self-Hosting as Privacy Feature
FlyingPress's documentation for Google Fonts self-hosting explicitly mentions GDPR compliance as a benefit (eliminates IP-to-Google-server transfer). This dual positioning (performance + compliance) broadens the value proposition.

---

## 10. Three Things Worth Borrowing for SwiftPress

### 10.1 Lazy Render via CSS Selectors — with Auto-Detection
**What FlyingPress does:** A textarea where users paste CSS selectors (e.g., `#footer`, `.elementor-section:nth-child(4)`) to defer full rendering of below-fold HTML blocks.
**Why it matters:** Reduces DOM parsing work during initial page load. Can cut LCP by 200–500ms on page-builder-heavy pages. WP Rocket does not have this.
**SwiftPress implementation:** Add a Lazy Render section to the panel with: (a) a CSS selector input, (b) a "detect now" button that uses a server-side headless fetch to auto-suggest candidates based on viewport intersection at load time, (c) a toggle to assign placeholder dimensions automatically to prevent CLS.
**Effort:** Medium (the core technique is a JS mutation observer + CSS visibility defer; the auto-detect button requires a curl/headless fetch with viewport simulation).

### 10.2 Real-User Core Web Vitals Dashboard (Built Into the Plugin)
**What FlyingPress does:** Injects a tiny async `vitals.min.js` (using the web-vitals library) that sends LCP, INP, CLS, TTFB to FlyingPress's backend, displayed in a Vitals tab with device/time/page/country filters.
**Why it matters:** This creates a feedback loop that keeps users inside the plugin dashboard. It also justifies the premium price tag — "we monitor your real performance, not just a lab score." No competitor in the free space does this.
**SwiftPress AI angle:** SwiftPress can extend this with an AI-powered insight layer — collect the same RUM data, then run a nightly LLM analysis (Gemini Flash at ~$0.0001/call) that generates 3 plain-English recommendations based on which pages are failing and why. Output shown in a "Smart Advisor" panel section.
**Effort:** Medium-High (the data collection JS is 2–3KB, the backend aggregation needs a simple time-series store or piggyback on WP options/custom table; the AI analysis layer can be async/cron-based).

### 10.3 Device-Specific Optimization Cascade from One Toggle
**What FlyingPress does:** "Separate Mobile Cache and Optimizations" is a single checkbox that triggers: separate HTML cache files, separate unused CSS analysis run, separate Cloudflare cache-by-device-type rule, separate lazy render decisions.
**Why it matters:** Mobile and desktop pages often look completely different when using page builders. Running a single shared CSS/JS optimization for both leads to over-broad UCSS removal or lazy render rules that break mobile nav or desktop hero. One toggle makes this expert-level decision accessible to novice users.
**SwiftPress implementation:** Add a "Mobile-Optimized Cache" toggle that: generates separate cache files per user agent bucket (mobile/desktop), runs CSS optimization logic separately for each, and shows a "Mobile" vs "Desktop" split in the cache stats dashboard widget.
**Effort:** Medium (the cache key already needs to incorporate UA detection; the main complexity is running critical CSS generation and UCSS exclusion logic twice per page URL).

---

## 11. Sources Used

- FlyingPress official site: https://flyingpress.com
- FlyingPress features page: https://flyingpress.com/features/
- FlyingPress pricing page: https://flyingpress.com/pricing/
- FlyingPress changelog: https://flyingpress.com/changelog/
- FlyingPress v5 release blog: https://flyingpress.com/blog/v5-release/
- FlyingPress image optimization blog: https://flyingpress.com/blog/image-optimization/
- FlyingPress CWV tracking blog: https://flyingpress.com/blog/core-web-vitals-tracking/
- FlyingPress server-side caching blog: https://flyingpress.com/blog/server-side-caching-not-enough/
- FlyingPress documentation home: https://docs.flyingpress.com/en/
- FlyingPress self-host external CSS/JS docs: https://docs.flyingpress.com/en/articles/11406792-self-host-external-css-and-javascript
- FlyingPress system requirements: https://docs.flyingpress.com/en/articles/11402864-system-requirements-for-flyingpress
- FlyingCDN site: https://flyingcdn.com/
- Nginx config gist: https://gist.github.com/CyberCr33p/356cc43e22300ed9cd7227c6889b13c4
- WPKube review 2026: https://www.wpkube.com/flyingpress-review/
- OnlineMediaMasters settings guide 2026: https://onlinemediamasters.com/flyingpress-settings/
- Technumero settings guide 2025: https://technumero.com/best-flyingpress-settings/
- Gaurav Tiwari review: https://gauravtiwari.org/flyingpress-review/
- Bloggingnote review: https://bloggingnote.com/flyingpress-review/
- McStarters review 2025: https://mcstarters.com/blog/flyingpress-review/
- Sayan Samanta review 2026: https://sayansamanta.com/flyingpress-review/
- SpeedVitals FlyingPress vs WP Rocket: https://speedvitals.com/blog/flyingpress-vs-wp-rocket/
- WPDiscounts FlyingPress vs WP Rocket vs NitroPack: https://wpdiscounts.io/blog/flyingpress-vs-wp-rocket-vs-nitropack/
- Ahoi.dev CloudOptimizer analysis: https://ahoi.dev/flyingpress-cloud-based-optimizations/
