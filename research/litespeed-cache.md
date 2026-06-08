# LiteSpeed Cache (LSCWP) — Research Dossier

**Research date:** 2026-06-08
**Plugin version at time of research:** 7.7 (released December 2025)
**Active installs:** 7,000,000+
**WP.org rating:** 4.8/5 (2,754 reviews, 2,539 five-star)

---

## 1. Positioning

LiteSpeed Cache positions itself as the single free all-in-one WordPress performance plugin, offering capabilities that normally require a paid plugin (WP Rocket) plus a paid CDN plus a paid image optimization service — all for zero cost. The catch is that its most impressive capability (server-level page caching) is locked to LiteSpeed Web Server (commercial or OpenLiteSpeed free) or QUIC.cloud CDN.

**Core marketing claim:** "Replace your entire optimization stack with one plugin." They attack WP Rocket ($59/yr) on price and W3TC on usability.

**Market position in 2026:** Ranked #2 overall in reputable plugin roundups (behind FlyingPress), #1 in the free-only category. Dominates LiteSpeed-hosted environments completely. On other servers, becomes a mid-tier option useful mainly for its QUIC.cloud connected services.

---

## 2. Feature Set (Complete)

### 2a. Features That Require a LiteSpeed Server (OLS / Enterprise / QUIC.cloud CDN)

These are the differentiating features — unavailable on plain nginx or Apache:

- **Server-Level Full-Page Cache (LSCache)** — PHP-bypass cache stored at the web server layer, not via PHP. TTFB sub-150ms vs 300-500ms for PHP-based plugins. This is the core advantage.
- **Edge Side Includes (ESI 1.0 — Full Protocol)** — Punch holes in fully-cached pages to inject private/user-specific fragments (cart widget, admin bar, user greetings). Full esi:include, esi:inline, esi:choose, esi:try support. This enables full-page cache even for logged-in WooCommerce users, which PHP-based plugins cannot match.
- **Private Cache** — Separate cache per logged-in user. Combined with ESI for per-user dynamic fragments.
- **Smart Cache Purging (Event-Triggered)** — Tag-based purge: when a product is updated, only pages tagged with that product's ID are purged, not the whole cache.
- **REST API Cache** — Cache API endpoints at server level.
- **WP-CLI Cache Commands** — Manage cache from command line.
- **Mobile/Desktop Cache Separation** — Separate cache copies per device type.
- **HTTP/2, HTTP/3, QUIC End-to-End** — Via QUIC.cloud CDN, the only CDN supporting HTTP/3 end-to-end.
- **ESI for WooCommerce/bbPress** — Dedicated integrations to cache even dynamic e-commerce pages.

**Note on OpenLiteSpeed (OLS):** OLS is free but does NOT support ESI or some advanced caching features. ESI requires LiteSpeed Enterprise (commercial) or QUIC.cloud CDN.

### 2b. Features Available on ANY Server (Including Nginx, Apache)

These work even without a LiteSpeed server — they are PHP/QUIC.cloud-powered:

- **PHP-Based Page Cache (fallback)** — Software-level HTML caching. Competitive with WP Rocket/W3TC but loses the TTFB advantage.
- **Object Cache** — Via Redis, Memcached, or LSMCD (LiteSpeed's Memcached alternative). Requires external daemon + PHP extension installed separately.
- **Browser Cache** — Sets Cache-Control headers. Recommended TTL: 1 year for static assets.
- **CSS Minification + Combination** — Merge and minify stylesheets.
- **JS Minification + Combination** — Merge and defer/delay JavaScript.
- **HTML Minification** — Strip whitespace and comments from output.
- **Critical CSS (CCSS) via QUIC.cloud** — WordPress cron queues pages; QUIC.cloud servers generate above-the-fold CSS and deliver it back. Eliminates render-blocking CSS. Works on nginx/Apache for Basic Tier (200 free requests/month).
- **Unique CSS (UCSS) via QUIC.cloud** — Remove all unused CSS rules per page. Same cloud queue mechanism. Per-device variation supported.
- **Image Optimization via QUIC.cloud** — WebP and AVIF conversion. Standard Queue: 100% free (unlimited, slow). Advanced Queue: 1,000 free/month (Basic Tier), 5,000 (LiteSpeed Tier).
- **AVIF Support** — Added in v7.0 (March 2025). Smaller than WebP, better quality.
- **Lazy Load — Images** — Native browser lazy load + custom implementation.
- **Lazy Load — Iframes** — YouTube embeds, maps, etc.
- **Responsive Image Placeholders (LQIP)** — Low-quality blur-up placeholders via QUIC.cloud. 100 free/month Basic Tier, $2 per 3,000 pay-as-you-go.
- **Viewport Images (VPI)** — Detect above-fold images; exclude them from lazy load to prevent LCP degradation. QUIC.cloud-powered.
- **Async CSS Loading** — Render-blocking CSS made non-blocking.
- **JavaScript Defer/Delay** — Delay JS execution until user interaction (reduces TBT/INP).
- **DNS Prefetch / Preconnect** — Automatic injection of resource hints.
- **Google Fonts Localization** — Download and self-host Google Fonts to eliminate third-party DNS lookups.
- **Gravatar Caching** — Local cache of Gravatar images.
- **Database Optimization** — Clean post revisions, trashed content, transients, spam comments; optimize database tables. Configurable max revision count and age limits.
- **Heartbeat Control** — Throttle/disable WordPress heartbeat API for admin, frontend, and editor contexts.
- **CDN Integration** — Works with any CDN (Cloudflare, BunnyCDN, etc.) plus native QUIC.cloud integration.
- **Guest Mode** — Serve cached pages to first-time visitors without cookies. The server identifies them as "guest" and returns a fully-optimized pre-cached page, improving TTFB for cold visitors.
- **Instant Click** — Preload page content on hover (before click). As of v7.7 (Dec 2025), deferred to avoid blocking initial render. Net effect: near-instant page transitions.
- **Cache Preload Crawler** — Autonomous crawler that warms the full-page cache by hitting all URLs from sitemap. Configurable threads (1-3 recommended), delay between hits, server load limits, scheduled windows.
- **WooCommerce Smart Cache** — Cart and checkout excluded from cache; product page caching with WooCommerce-aware purge rules.
- **Multisite Support** — Network-level cache management.
- **Import/Export Settings** — JSON-based settings transfer.
- **Debug Toolbox** — Log viewer, cache hit/miss inspector, report generator.
- **Cloudflare Cache Clear on Purge All** — Added in v7.2 (2025). When LSCache purges, it also clears Cloudflare cache automatically.
- **Allowlist for Critical CSS** — Added in v7.1 (2025). Whitelist specific CSS that must always be included even in critical path.

---

## 3. QUIC.cloud Pricing (2026)

### Online Services (Image & Page Optimization)

Works on ALL server types. Tier determines monthly free quota.

| Service | Basic (nginx/Apache) | LiteSpeed Server | LiteSpeed Enterprise | Partner |
|---|---|---|---|---|
| Image Optimization (Advanced Queue) | 1,000/mo free | 5,000/mo free | 10,000/mo free | 20,000/mo free |
| Page Optimization (Critical/Unique CSS) | 200 req/mo free | 1,000/mo free | 2,000/mo free | 4,000/mo free |
| LQIP Placeholders | 100/mo free | 500/mo free | 1,000/mo free | 2,000/mo free |

**Standard Queue image optimization: 100% free, unlimited (slower processing).**

**Paid add-ons:**
- Image Optimization Advanced Queue: $5/month for 20,000 images
- Page Optimization: from $5/mo (10k req) to $10/mo (30k req)
- LQIP: $2 pay-as-you-go per 3,000 requests

### CDN (QUIC.cloud CDN)

**Free Plan:** Unlimited bandwidth on 6 PoPs (North America + Europe subset only). No DDoS, no advanced security.

**Standard Plan:** 80+ PoPs globally.

| Region | Cost/GB |
|---|---|
| North America | $0.02 |
| Europe | $0.02 |
| Latin America, Asia, Oceania, Middle East, Africa | $0.08 |
| Russia | $0.04 |

**Monthly free CDN credit by tier:**
- Basic (nginx/Apache): $0.02/month (~1 GB North America traffic)
- LiteSpeed Server: $0.10/month (~5 GB NA traffic)
- LiteSpeed Enterprise: $0.20/month (~10 GB NA traffic)

Purchased credit never expires and rolls over.

**The plugin itself is 100% free. No premium version of the plugin exists.**

---

## 4. Admin Panel & UX

### 4a. Overall Aesthetic and Layout

The UI lives under a "LiteSpeed Cache" top-level menu in the WordPress admin sidebar. The visual design is described by reviewers uniformly as "functional but dated" — it uses standard WordPress admin styling (no custom framework like WP Rocket's clean card UI). Navigation is horizontal tabs within each section.

### 4b. Menu Structure (v7.x)

```
LiteSpeed Cache (sidebar)
├── Dashboard
├── General
├── Cache
│   ├── Cache
│   ├── TTL
│   ├── Purge
│   ├── Excludes
│   ├── ESI (disabled by default)
│   ├── Object Cache
│   ├── Browser Cache
│   └── Advanced
├── CDN (new dedicated tab in v7.0)
│   ├── QUIC.cloud CDN
│   └── CDN Mapping
├── Image Optimization
├── Page Optimization
│   ├── CSS
│   ├── JS
│   ├── HTML
│   ├── Media
│   ├── VPI
│   ├── Localization
│   └── Tuning
├── Database
├── Crawler
└── Toolbox
    ├── Purge
    ├── Heartbeat
    ├── Import/Export
    ├── Debug
    └── .htaccess Edit
```

**Tab count:** ~30+ individual settings screens. This is a significant source of user overwhelm for beginners.

### 4c. Dashboard

The dashboard is a monitoring hub, not a control center:

- **Usage Statistics Panel:** QUIC.cloud service consumption (Image Opt %, Page Opt %, CDN bandwidth %, LQIP %) with monthly quota bars.
- **Performance Metrics:** Before/after PageSpeed score comparison (pulls data from PageSpeed Insights API on demand). Before/after load time.
- **Optimization Activity Queues:** Critical CSS queue depth, Unique CSS queue, LQIP queue, VPI queue — each with a "Force cron" button and historical stats.
- **System Status:** Active cache types (Public Cache, Private Cache, Object Cache, Browser Cache) shown as on/off indicators.
- **Crawler Status:** Active crawler count, current URL being crawled, last crawl timing.
- **Sync Button:** Refreshes all stats and recalculates PageSpeed score on demand.

### 4d. Onboarding Flow

**No guided setup wizard** (unlike WP Rocket's activation wizard or Perfmatters' quick-start). Instead:

1. Plugin is installed and cache is **automatically enabled** on LiteSpeed servers — zero-click start.
2. A prompt appears to request a QUIC.cloud Domain Key (renamed to Sodium-based auth token in v7.0). This unlocks QUIC.cloud services.
3. Users are guided to one of **5 optimization presets:**
   - Essentials
   - Basic
   - Standard
   - Advanced (most commonly recommended)
   - Extreme
4. Each preset applies a batch of pre-configured settings appropriate for that level. The "Advanced" preset is the community default.

**The key friction point:** Getting the QUIC.cloud Domain Key is step 2 of the process. New users who skip this miss out on Critical CSS, Unique CSS, and image optimization, which are the most impactful features. The connection flow involves registering at QUIC.cloud, linking via the plugin, and waiting for verification.

### 4e. Notable UX Patterns

- **Settings carry inline help text** via small info icons (question marks) beside each toggle. Hover/click for explanation.
- **"TEST" labels on risky settings:** Many JS/CSS combination settings are flagged "TEST THIS FIRST" because they commonly break sites. This is an honest UX choice that prevents support requests.
- **Stats always show paired before/after:** PageSpeed and load time comparisons make impact visible without leaving WP admin.
- **Toolbox > Purge has granular controls:** Purge All, Purge Front Page, Purge Individual URLs, Purge by Tag — more fine-grained than most competitors.
- **Admin bar shortcut:** While logged in on the frontend, a LiteSpeed Cache menu item in the WP admin bar allows single-click "Purge This Page" or "Purge All."
- **Heartbeat control** is directly in the Toolbox — one of the few plugins that exposes this prominently without requiring a separate plugin.

### 4f. Weaknesses in UX

- **No setup wizard** — cold start for non-technical users requires reading documentation.
- **QUIC.cloud stats dominate the dashboard** even if you are not using QUIC.cloud (nginx server). The entire monitoring pane is centered on a service many users cannot fully access.
- **30+ settings tabs** are intimidating and the information architecture does not flow logically for a beginner (why is "Browser Cache" nested inside the "Cache" menu instead of being a sibling?).
- **Documentation is developer-oriented.** The official docs assume you understand what ESI means, what Critical CSS generates, and why transients matter.
- **No live support.** Community forums + LiteSpeed Slack only.
- **Interface is "functional but dated"** — no modern card-based layout, no progress indicators, no onboarding checklists.

---

## 5. AI / Automation Assessment

**Short answer: No genuine AI or LLM features exist. The automation is rule-based and cloud-queue-based.**

The "automated" elements in LiteSpeed Cache are:

- **QUIC.cloud Critical CSS generation:** A server-side rendering process that visits a page URL via headless browser on QUIC.cloud infrastructure, captures the CSS rules that are actually applied to above-the-fold elements, and returns the result. This is deterministic rendering, not machine learning.
- **QUIC.cloud Unique CSS generation:** Similar process — renders the page, identifies used vs unused CSS selectors, strips unused. Same: deterministic, not AI.
- **Image compression:** Standard algorithms (mozjpeg, libwebp, libaom for AVIF). No ML-based compression or smart cropping.
- **Crawler scheduling:** Rule-based (sitemap URLs, configurable intervals, server load limits). No predictive scheduling.
- **Smart cache purging:** Tag-based event triggers (post updated → purge all pages tagged with that post ID). This is relational logic, not ML.

**Verdict:** LiteSpeed Cache has no AI, no LLM integration, no ML-based optimization. All automation is server-rendered deterministic processing or event-driven rule logic. The marketing uses words like "smart" and "intelligent" to describe tag-based cache purging — this is not AI in any meaningful sense.

---

## 6. Performance Methodology

### Architecture

LiteSpeed Cache's performance advantage comes from **three compounding layers:**

1. **Server-bypass full-page cache** — Served directly from LiteSpeed's in-memory store (similar to Varnish or Nginx FastCGI cache) without PHP execution. This is the primary source of sub-150ms TTFB.
2. **PHP object cache** — Via Redis/Memcached, eliminates repeated database queries. Works on all servers.
3. **Frontend optimization pipeline** — CSS/JS minify+combine, Critical CSS (eliminates render-blocking), Unique CSS (reduces payload), lazy load (defers offscreen assets), Instant Click (preloads on hover).

### Cache Hit Flow (LiteSpeed server)

```
Browser request
  → LiteSpeed Web Server
    → Check LSCache store
      → HIT: return cached HTML directly (no PHP, no MySQL)
      → MISS: WordPress PHP → generate → store in LSCache → return
```

### Cache Hit Flow (Nginx / PHP fallback)

```
Browser request
  → Nginx
    → PHP-FPM
      → WordPress → check cache file on disk → return or generate
```

The difference: LiteSpeed bypasses PHP-FPM entirely on cache hits. Nginx must still boot PHP-FPM to route the request to the cache check. This is why TTFB is typically 2-3x better on LiteSpeed.

### Benchmark Results (from hostingradar.io)

**On LiteSpeed server:**
- PageSpeed: 58 → 96/100
- Load time: 4.2s → 0.9s (78% improvement)
- TTFB: 890ms → 120ms

**On WooCommerce (LiteSpeed):**
- PageSpeed: 52 → 88/100
- Load time: 5.8s → 1.4s (76% improvement)
- DB queries: 284 → 12 (96% reduction via object cache)

**On Apache (non-LiteSpeed):**
- PageSpeed: ~89/100
- Load time: 1.6s
- 62% improvement over baseline

**On Nginx, competitor comparison (2026):**
- WP Rocket delivers 50-70% improvements with zero config errors
- LiteSpeed on Nginx is "mid-tier" — PHP-based caching loses its server-bypass advantage

---

## 7. Nginx Support — Detailed Breakdown

### What Works on Nginx

| Feature | Works on Nginx? | Notes |
|---|---|---|
| Full-Page Server Cache (LSCache) | NO | Requires LiteSpeed server binary |
| ESI (Edge Side Includes) | NO | LiteSpeed Enterprise only |
| Private Cache for logged-in users | NO | Requires LSCache engine |
| PHP Object Cache (Redis) | YES | Via Redis/Memcached, works anywhere |
| Browser Cache headers | YES | HTTP headers set by PHP |
| CSS/JS Minification + Combination | YES | PHP-powered |
| HTML Minification | YES | PHP-powered |
| Critical CSS via QUIC.cloud | YES | Cloud-processed, server-agnostic |
| Unique CSS via QUIC.cloud | YES | Cloud-processed |
| Image Optimization (WebP/AVIF) | YES | QUIC.cloud processes images |
| Lazy Load (images + iframes) | YES | PHP/JS |
| LQIP Placeholders | YES | QUIC.cloud (100 free/mo on Basic Tier) |
| Viewport Images (VPI) | YES | QUIC.cloud |
| Async CSS / Defer JS | YES | PHP/JS |
| Google Fonts Localization | YES | PHP |
| Database Optimization | YES | PHP/MySQL |
| Heartbeat Control | YES | PHP |
| CDN Integration | YES | Any CDN |
| QUIC.cloud CDN | YES | Works with any backend server |
| Cache Preload Crawler | PARTIAL | Crawler warms cache, but cache is PHP-based on nginx |
| Guest Mode | PARTIAL | Exists but serves PHP-based cache, not server-bypass |
| Instant Click | YES | JS-based prefetch |
| Smart Cache Purging (tag-based) | NO | Tag system is an LSCache server feature |
| WooCommerce ESI cart caching | NO | Requires ESI |

### Summary for Nginx Users

On nginx, LiteSpeed Cache becomes a capable but not exceptional plugin. The features that work (QUIC.cloud services, CSS/JS optimization, lazy load, image optimization) are things competitors like WP Rocket, Perfmatters, or even the free Autoptimize + ShortPixel combo also provide. The server-bypass cache, ESI, smart tag purging, and private caching — the four things that make LiteSpeed genuinely special — are entirely absent.

**For a plugin targeting nginx servers (like SwiftPress), this is the competitive gap to exploit.** LiteSpeed's nginx experience is a degraded, feature-restricted version. A plugin designed and optimized specifically for nginx can offer what LSCache cannot.

---

## 8. Version History Highlights

| Version | Date | Key Changes |
|---|---|---|
| 7.7 | Dec 2025 | Cron interval increased to 15min default; download button for logs; purge product cache on order cancel |
| 7.2 | 2025 | Cloudflare cache clear on Purge All; Cache PHP Resources toggle removed |
| 7.1 | 2025 | Allowlist support for Critical CSS |
| 7.0 | Mar 2025 | AVIF format support; new QUIC.cloud CDN tab; Sodium encryption replaces domain keys; .json config format replaces .ini; PHP 7.2+ required |
| 6.5.4 | Dec 2024 | Fixed Google Fonts broken with Async; removed mt_srand from hash generation |

---

## 9. Weaknesses / Honest Criticisms

### Technical

- **Server lock-in is the business model.** The plugin is free to drive LiteSpeed server adoption. On nginx/Apache, you get 40-50% of the features.
- **Object cache is NOT built-in.** Users must separately install Redis or Memcached daemon plus PHP extension — a significant barrier for shared hosting users. The plugin just configures the connection.
- **ESI requires Enterprise license** (commercial). OpenLiteSpeed (free) does not support ESI. This is a common gotcha.
- **Clustered/load-balanced environments:** Cache purge does NOT synchronize across nodes with third-party load balancers.
- **Plugin file weight:** 2MB decompressed vs 528KB for minimal alternatives. Each page load carries this overhead.
- **Security history:** 18 known CVEs. The 2024 critical vulnerability allowed unauthenticated account takeover of 6M+ sites via weak security hash (only 1 million possible values, no salting). The vulnerability was in the user simulation feature used for private caching.

### UX / Product

- **No live support.** Forums + Slack only. Enterprises requiring SLA are excluded.
- **Steep learning curve.** 30+ settings tabs with no wizard. Documentation is developer-oriented.
- **QUIC.cloud-centric dashboard** is noisy for nginx users who can only access Basic Tier.
- **Rapid release cycle causes bugs.** "Occasional bugs due to rapid development cycles."
- **WooCommerce cart caching requires manual exclusion configuration** on some themes — cart can display wrong items if not tuned.
- **No real-time support.** Community response times can be slow.
- **"Free" is conditional.** Meaningful use on non-LiteSpeed servers requires QUIC.cloud credits for image and page optimization at any reasonable volume. For large sites on nginx, the value proposition erodes.

### Marketing

- **"6-12x faster" claims** are server throughput benchmarks (requests/second under load), not real-world page load time improvements. Reviewers call this "hand-waving specsmanship."
- **QUIC.cloud free tier is extremely limited on Basic (nginx) Tier:** 200 page optimization requests/month and only 1 GB CDN traffic. Large sites hit this ceiling immediately.

---

## 10. Ideas Worth Borrowing (For SwiftPress — Inspiration Only)

### Idea 1: Optimization Preset Tiers (Low Effort)
LiteSpeed offers 5 presets (Essentials → Extreme). Each applies a tested combination of settings. For SwiftPress, implement 3-4 named presets with clear plain-English descriptions of what each enables and what the tradeoffs are ("Aggressive — may break complex JS; test before deploying"). This eliminates the cold-start problem for non-technical users while giving experts override capability.

**Why it matters:** The biggest barrier to cache plugin adoption is decision paralysis over 30+ toggles. Presets reduce the time-to-value to under 60 seconds.

### Idea 2: Dashboard Before/After Score with On-Demand Refresh (Medium Effort)
Pull PageSpeed Insights (or WebPageTest) data on demand from the plugin dashboard. Show before (cached score from first run) vs current score. Add a single "Refresh Score" button. This gives users evidence that the plugin is working without leaving WP admin.

**Why it matters:** Users who see a concrete improvement number become advocates. LiteSpeed does this and it's a major trust builder.

### Idea 3: Tag-Based Cache Invalidation on Nginx via PHP (High Effort)
LSCache's tag purge is server-native on LiteSpeed. On nginx, you can approximate it: when a post is updated, store a mapping of "which cached HTML files contain references to this post ID," then delete only those files. This is more expensive than server-native but still faster than purging the entire cache on every edit.

**Why it matters:** "Purge everything on any update" (what most PHP cache plugins do) kills cache effectiveness on sites with frequent edits. Smart partial invalidation is the killer feature nginx users can't get from LiteSpeed.

### Idea 4: QUIC.cloud-Style SaaS Critical CSS via AI (Medium Effort + BYO API Key)
LiteSpeed's Critical CSS is deterministic (headless browser render). The AI angle: use an LLM (Gemini Flash via OpenRouter, ~$0.0001/call) to analyze the CSS and HTML together, rank selectors by visual prominence, and generate a smarter critical path that also considers font loading and above-the-fold text contrast. This goes beyond what a headless browser renders and can catch edge cases (dark mode, font swap flicker).

**Why it matters:** Existing Critical CSS tools are mechanical; they only include what the headless browser sees at 1280x800. AI-assisted CCSS can reason about layout intent and produce better CLS scores.

### Idea 5: Instant Click with Predictive Prefetch (Medium Effort)
LiteSpeed's Instant Click is hover-triggered. Upgrade: track per-page internal link click frequency in localStorage (client-side, no server). After 3+ visits, prefetch the top 2 most-clicked next pages on load (not hover). This is machine-learned navigation pattern prediction without any ML infrastructure — pure JS.

**Why it matters:** Hover-triggered prefetch wastes bandwidth on accidental hovers. Visit-pattern-based prefetch targets pages users actually navigate to, reducing waste while improving perceived performance.

---

## 11. Sources

- [LiteSpeed Cache on WordPress.org](https://wordpress.org/plugins/litespeed-cache/)
- [LiteSpeed Cache for WordPress — LiteSpeed Technologies](https://www.litespeedtech.com/products/cache-plugins/wordpress-acceleration)
- [QUIC.cloud CDN Pricing](https://docs.quic.cloud/billing/cdn/)
- [QUIC.cloud Online Services Pricing](https://docs.quic.cloud/billing/services/)
- [QUIC.cloud Free Quota Tiers](https://docs.quic.cloud/billing/tiers/)
- [QUIC.cloud Services & Features](https://www.quic.cloud/quic-cloud-services-and-features/)
- [CCSS and UCSS Guide — QUIC.cloud](https://www.quic.cloud/ccss-and-ucss-a-handy-guide/)
- [LSCache FAQ — LiteSpeed Docs](https://docs.litespeedtech.com/lscache/lscwp/faq/)
- [LSCache Dashboard — LiteSpeed Docs](https://docs.litespeedtech.com/lscache/lscwp/dashboard/)
- [LSCache Beginner's Guide](https://docs.litespeedtech.com/lscache/lscwp/beginner/)
- [LiteSpeed Cache 7.0 Overview — webhosting.de](https://webhosting.de/en/litespeed-cache-7-0-is-the-big-update-in-march-2025/)
- [2025 So Far — LiteSpeed Blog](https://blog.litespeedtech.com/2025/07/22/2025-so-far/)
- [LiteSpeed Cache Review — hostingradar.io](https://www.hostingradar.io/en/blog/litespeed-cache-review)
- [LiteSpeed Cache Review — Darrel Wilson](https://darrelwilson.com/review/litespeed-cache-review/)
- [Best WordPress Cache Plugins 2026 — OnlineMediaMasters](https://onlinemediamasters.com/best-wordpress-cache-plugins/)
- [Ideal LiteSpeed Cache Settings — OnlineMediaMasters](https://onlinemediamasters.com/litespeed-cache-settings/)
- [Downside of LiteSpeed Cache — PagePipe](https://pagepipe.com/the-downside-of-litespeed-cache-plugin/)
- [LiteSpeed vs WP Rocket — RunCloud](https://runcloud.io/blog/litespeed-vs-wp-rocket-cache)
- [LiteSpeed Cache All You Need to Know — WP Marmite](https://wpmarmite.com/en/compare/best-wordpress-performance-plugins/litespeed-cache/)
- [Edge Side Includes — LiteSpeed Technologies](https://www.litespeedtech.com/products/features/edge-side-includes)
- [Improve TTFB with LiteSpeed Cache — boostedhost.com](https://boostedhost.com/blog/en/improve-ttfb-with-litespeed-cache-2025-esi-crawler-and-object-cache/)
