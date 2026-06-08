# NitroPack — Competitor Dossier
**Research date:** 2026-06-08
**Prepared for:** SwiftPress revamp competitive analysis

---

## 1. Market Positioning

NitroPack positions itself as the **"hands-off, all-in-one performance cloud"** — not a WordPress plugin but a SaaS optimization proxy. Its core pitch: install a lightweight connector, select a mode, and walk away. Their tagline family revolves around "pass Core Web Vitals in minutes" and "zero configuration required."

Target market is primarily non-technical WordPress site owners and WooCommerce merchants who want results without understanding performance engineering. They also target agencies with a white-label/revenue-share model.

**Key market claim (2026):** 250,000+ live websites accelerated; 52% of NitroPack-powered sites pass all Core Web Vitals (vs WP Rocket 48%, W3 Total Cache 40%, per Yottaa 2025 Web Performance Index).

---

## 2. Pricing Tiers (2026 — Current)

All plans bill per monthly pageviews. Prices shown are monthly billing / annual billing.

| Plan | Monthly | Annual/mo | Sites | Pageviews/mo | CDN BW/mo | Team |
|------|---------|-----------|-------|--------------|-----------|------|
| **Free** | $0 | $0 | 1 | 1,000 | 1 GB | 1 |
| **Starter** | $8 | $7 | 1 | 8,000 | 5 GB | 3 |
| **Plus** | $22 | $18 | 1 | 40,000 | 25 GB | 5 |
| **Pro** | $99 | $83 | 3 | 540,000 | 270 GB | 10 |
| **Agency** | ~$275 | ~$229 | 10+ | Shared pool | Shared | Unlimited |

Annual billing saves approximately 17%.

**Feature gating across plans (critical):**
- **Starter adds over Free:** Delayed scripts, LCP Preload, Cart Cache optimization, Optimize interactive elements
- **Plus adds over Starter:** All Starter features + higher limits
- **Pro adds over Plus:** Dynamic Queue, Font Subsetting, Extract Large Inline CSS, Google Tag Manager optimization, 3-site license, 10 team members
- **Agency:** White-label, client management portal, 15% revenue-share referral program, bulk pricing

**14-day money-back guarantee** on all paid plans. No vendor lock-in claim (original files untouched).

**Free plan gotcha:** Adds a NitroPack badge to site. Only 1,000 pageviews/month — effectively a demo tier, not production-viable.

**Pricing weakness:** Pageview-based pricing punishes traffic growth. A mid-traffic blog or e-commerce site can easily outgrow Plus ($22/mo) and face a $77/mo jump to Pro. WP Rocket is $59/year for a single site. NitroPack costs $264/year at Plus for one site — roughly 4.5x more expensive than WP Rocket for similar traffic.

---

## 3. Full Feature Set

### 3.1 Caching
- **Full-page HTML cache** — served from CDN edge nodes
- **Browser cache** (Apache only — NGINX requires manual server-level configuration)
- **Smart Cache Invalidation** — dependency-aware selective purging. When a post updates, only that post's cache and its related pages (homepage widget, category archive, etc.) are cleared. Unaffected pages remain served from cache. Works automatically — no manual configuration.
- **Cache Warmup** — automatically generates fresh cache for both desktop and mobile after invalidation. Covers all variation cookie combinations. Zero configuration.
- **Instant Cache Reoptimize** (Pro+) — reoptimizes changed resources without full cache clear
- **Dynamic Queue** (Pro+) — real-time prioritization: pages being actively visited by users get bumped to the front of the optimization queue. Released June 2025.
- **AJAX URL caching** — caches dynamic AJAX responses where safe
- **Cookie-based cache variations** — creates different cache files per cookie combination
- **Ignored query parameters** — recognizes UTM/campaign URLs as cache-equivalent; prevents fragmentation

### 3.2 Image Optimization
- **Lossy + lossless compression** — configurable quality levels (80% default in Medium/Strong/Ludicrous)
- **Automatic WebP conversion** — with backward compatibility fallback
- **Adaptive image sizing** — serves device-appropriate dimensions (not just responsive CSS, but actual resized assets)
- **Image lazy loading** — including CSS background images (claims to be first to introduce background image lazy loading)
- **Animated image optimization** — GIF/WebP animation optimization (Pro+)
- All image work runs on NitroPack cloud servers — no hosting CPU usage

### 3.3 Code Optimization
- **HTML minification and compression**
- **CSS minification**
- **JS minification**
- **Combine CSS** (not enabled by default in any mode — must enable manually)
- **Combine JS** (not enabled by default — must enable manually)
- **Remove unused CSS** (off by default, manual only)
- **Extract Large Inline CSS** (Pro+) — moves large inline `<style>` blocks to external files for better caching
- **Critical CSS generation** — unique per-page (not per-template), per layout. NitroPack fetches the page with JS disabled, extracts above-fold styles, inlines them in `<head>`. Done on their cloud.
- **Remove @font-face from Critical CSS** — prevents render blocking from font declarations
- **Defer render-blocking resources** — multiple strategies including custom resource loader script (Strong+)
- **Delay non-critical scripts** (Ludicrous mode) — defers JS execution until first user interaction (scroll/click). This is the source of the UX controversy.
- **DNS prefetching / preconnect hints**
- **Minify JSON-LD** — compresses schema markup
- **HTML normalization** — DOM cleanup
- **Google Tag Manager optimization** (Pro+)
- **Remove render-blocking resources** — with "resource loader script" approach
- **Keep HTML Comments** — optional toggle

### 3.4 Font Optimization
- **Font preloading** — links preloaded early in `<head>`
- **Font subsetting** (Pro+) — removes unused glyphs per-page. Each page loads only the characters it actually uses. Works automatically.
- **Font compression upgrade** — superior compression vs browser defaults
- **Font rendering override** — `font-display: swap` forced (Strong+)
- **Disable text flashing** — FOUT/FOIT prevention
- **Font loading strategy control** — configurable delivery method
- **Optimize Google-hosted fonts** — self-hosts or proxies Google Fonts for privacy + speed

### 3.5 CDN
- **Built-in global CDN** — powered by Amazon CloudFront (100+ PoPs), zero extra cost
- Automatically integrated — no configuration needed
- Adds canonical headers to CDN responses to avoid SEO duplicate-content issues
- CDN bandwidth metered against plan allowance (1GB free → 270GB Pro)
- **Not flexible:** CDN provider is fixed (CloudFront/NitroPack infrastructure). Cannot use your own Cloudflare, BunnyCDN, etc. as primary CDN — though Cloudflare can sit in front.

### 3.6 Lazy Loading
- Image lazy loading
- iFrame lazy loading
- Self-hosted video lazy loading
- Video facades (placeholder thumbnails that load actual player on interaction)
- HTML lazy loading (native `loading="lazy"` attribute injection)

### 3.7 WooCommerce / E-Commerce
- **Cart Cache** — continues serving optimized cached pages to visitors who have items in cart. Solves the classic "cart breaks caching" problem by treating cart data separately from page rendering.
- Stock levels, prices, currency — kept dynamic/fresh via smart invalidation
- WooCommerce-specific cache invalidation triggers

### 3.8 Advanced / Pro-Only
- **Dynamic Queue** — real-time optimization priority queue
- **Font subsetting** per page
- **Extract Large Inline CSS**
- **Google Tag Manager optimization**
- **Animated image optimization**
- **Instant Cache Reoptimize**

### 3.9 Developer / Configuration
- **Test Mode** — disables NitroPack for all visitors; appending `?testnitro=1` to any URL serves the optimized version. Safe for QA/staging testing without affecting real users.
- **Excluded URLs** — skip optimization for specific pages
- **Excluded resources** — assets to skip
- **Excluded operations** — individual optimizations to skip
- **Optimize-only URLs** — target specific URLs for optimization
- **Custom CSS** — inject per-site CSS
- **Additional domains** — optimize URLs from extra domains at zero added cost
- **Audit Log** — transparency trail
- **2FA** — account security

### 3.10 Integrations
- Cloudflare (sync cache purge)
- Sucuri (firewall compatibility)
- Reverse proxy support: Varnish, NGINX, Cloudflare proxy (manual IP list configuration required)
- Nginx Helper plugin integration (see Section 6)
- WP Engine, Kinsta, SiteGround, Cloudways (and 20+ other hosts) — pre-configured

---

## 4. Admin Panel / UX Analysis

### 4.1 Architecture: Two Interfaces

NitroPack has a **split admin model** that is a notable UX friction point:

1. **WordPress plugin page** — minimal. Contains: site connection status, optimization mode selector, cache warmup toggle, basic purge button. That's essentially it. ~6 controls visible.
2. **Cloud dashboard at nitropack.io** — the real control center. Left-side navigation with 5-6 sections.

Advanced users must leave WordPress and go to the nitropack.io app for any meaningful configuration. This creates context-switching friction.

### 4.2 Cloud Dashboard Layout

**Left-column navigation (5 main sections):**

1. **Dashboard** — Status overview, optimization mode display, key metrics widgets (cache hit ratio, optimized pages, CDN usage), quick cache purge button
2. **Cache Insights** — Per-URL optimization status table. Filterable by URL, status, device type (desktop/mobile). Each row shows whether a page is cached and its last optimization date. Graph of cache size over time. Cache hit ratio graph. Per-resource breakdown (HTML vs image vs JS/CSS cache sizes).
3. **Cache Settings / Settings** — The full settings panel organized by category:
   - General Settings tab
   - Caching subtab
   - Images subtab
   - HTML subtab
   - CSS subtab
   - JS subtab
   - Fonts subtab
4. **Analytics** — Pageview usage graphs, CDN bandwidth usage over time, resource-level CDN consumption breakdown
5. **Integrations** — Third-party connections (Cloudflare, Sucuri, Reverse Proxy config, Nginx Helper)

**Additional sections:** Team management, Audit Log, Plan/billing.

### 4.3 Optimization Modes (the Core UX Concept)

NitroPack's signature UX pattern is the **mode selector** — a single control that applies a pre-configured bundle of settings:

| Mode | What It Enables | Risk Level |
|------|----------------|------------|
| **Standard** | HTML preconnects, image optimization at 100% quality | Very low |
| **Medium** | Minify, combine CSS, critical CSS, lazy loading, image optimization at 80% | Low |
| **Strong** | Everything in Medium + resource loader script + `font-display: swap` | Medium |
| **Ludicrous** | Everything in Strong + delay all JS until user interaction | High — can break sites |
| **Custom** | Any combination the user manually configures | Varies |

**Strong is the effective default recommendation.** Ludicrous is the "marketing mode" — produces the best PageSpeed numbers but carries genuine UX risks (interactive elements like forms, sliders, popups are delayed until scroll/click).

**Combine JS and Remove Unused CSS are OFF in all presets** — these must be manually enabled and tested, as they break most sites.

### 4.4 Onboarding Flow

1. Create nitropack.io account (email/password or OAuth)
2. Add site URL in the NitroPack dashboard — get an API key + secret
3. Install WordPress plugin via standard WP plugin installer
4. On the WP plugin page, enter site ID + secret key to connect
5. Plugin page shows "connected" status and mode selector
6. Choose optimization mode (Strong is the recommended default)
7. Done — optimization begins automatically

**Total time claimed: under 5 minutes.** The review evidence backs this up — no .htaccess editing, no server configuration, no rewrite rules needed for basic setup.

**Onboarding feature added November 2025:** In-product onboarding guidance, likely a guided checklist or tooltip flow in the dashboard. Details not fully documented publicly.

### 4.5 Design Aesthetic

- Clean SaaS-style dashboard
- Left rail navigation
- Card-based metric widgets with graphs
- Toggle switches (on/off) for individual features rather than complex input forms
- Color-coded status indicators (green/yellow/red) for optimization health
- Mobile-responsive cloud dashboard
- Settings panel organized into logical tabs per resource type (HTML/CSS/JS/Images/Fonts)

### 4.6 UX Weaknesses
- **Split-dashboard friction:** Non-trivial settings require leaving WordPress admin. This is a deliberate architectural choice (cloud-based) but confuses WordPress users who expect everything in `/wp-admin`.
- **Mode opacity:** When you select "Strong," it's non-obvious exactly what 19 specific settings are being applied without reading docs.
- **Pageview overage ambiguity:** Users report confusion about what counts as a "pageview" for billing — cached vs. uncached, bot traffic, etc.
- **Site removal requires support:** Cannot self-service remove a site from your account — must contact support.
- **Limited in-WordPress feedback:** The WP plugin doesn't show you what's happening optimization-wise. You must check the cloud dashboard.

---

## 5. AI and Automation — Honest Assessment

### 5.1 What They Call "AI"

NitroPack's marketing frequently uses "AI" and "intelligent algorithm" language. Breaking this down honestly:

**Real algorithmic automation (not LLM/ML):**
- **Smart Cache Invalidation** — dependency graph analysis of WordPress content relationships (post → category → homepage widget → related posts). This is rule-based content relationship mapping, not machine learning. Sophisticated engineering, not AI.
- **Critical CSS generation** — automated headless browser crawl + CSS extraction per page. Automated pipeline, not AI.
- **Adaptive image sizing** — server-side image resizing based on device detection. Rule-based.
- **Cache Warmup** — automated crawler that generates cache after invalidation. Rule-based scheduling.
- **Dynamic Queue** — priority queue based on real-time traffic signals (pages currently being visited go first). Data-driven scheduling, not ML.

**What they have that's adjacent to AI:**
- **Fin AI assistant** for customer support — resolves 42.7% of support tickets automatically. This is an LLM-based support chatbot (Intercom's Fin or similar), not part of the product optimization itself.
- **Proprietary resource loading mechanism** — loads assets off the main thread using multi-core parallelism. Advanced engineering, not ML.

**No LLM/ML in the optimization pipeline itself.** Despite "AI" marketing language, there is no evidence of:
- Machine learning models for optimization decisions
- Predictive prefetching based on ML
- Personalized optimization per visitor
- Any OpenAI/Anthropic/etc. integration in the core product

**Verdict:** NitroPack's "AI" is expert-engineered automation with excellent defaults. That IS genuinely valuable. But it's not what most people mean by AI in 2026. A competitor offering real LLM-powered analysis (like explaining why a specific page is slow, or generating optimization recommendations) would have a genuine differentiator.

---

## 6. Performance Methodology

### 6.1 The Cloud Proxy Model

NitroPack operates as a cloud service, not a traditional plugin:

1. Site owner installs a lightweight WordPress connector plugin
2. When a page is first requested, the connector calls NitroPack's API
3. NitroPack's cloud servers fetch the page, run all optimizations (minification, critical CSS extraction, image compression, WebP conversion)
4. The optimized output is stored on NitroPack's CDN edge nodes
5. Subsequent visitors receive the optimized page from the CDN edge nearest to them
6. No optimization processing occurs on the hosting server — zero server CPU for optimization

**Advantages of this model:**
- Doesn't slow down the origin server
- Optimization quality isn't bottlenecked by hosting plan CPU limits
- CDN delivery is automatic and included

**Disadvantages:**
- Traffic is effectively routed through NitroPack's infrastructure (privacy/control concern)
- Pageview-metered billing model
- If NitroPack service is down, optimization degrades
- Original files untouched (they claim) but cache is on their servers

### 6.2 Critical CSS Approach

Generates unique Critical CSS per page (not per template) by:
1. Fetching page with JavaScript disabled (to avoid JS-injected styles)
2. Extracting only the CSS rules relevant to above-fold content
3. Inlining the result in `<head>`
4. Loading remaining CSS asynchronously

This is more precise than per-template Critical CSS (which WP Rocket uses) because templates can produce different above-fold content across pages.

### 6.3 The Ludicrous Mode Controversy

**The technique:** Delay ALL JavaScript execution until the first user interaction (scroll or click). This dramatically improves lab metrics (PageSpeed Insights, Lighthouse) because:
- Time to Interactive (TTI) measurement tools see fewer blocking resources
- LCP is faster with no JS competition for main thread
- But: interactive elements (forms, chat widgets, sliders, popup triggers) don't work until the user first interacts

**The cloaking allegation:** NitroPack was accused of serving a "clean" version to Googlebot (which triggers JavaScript differently than real users) to game PageSpeed scores, while real users got a degraded experience. This was specifically about the Ludicrous mode JS-delay behavior.

**NitroPack's response:**
- They launched Test Mode to let users QA before going live
- They produced a joint webinar with Google to address concerns
- They argue the JavaScript deferral is legitimate performance optimization
- The technique is not cloaking in the traditional sense (they're not detecting bots and serving different HTML)
- Google's John Mueller stated Lighthouse scores don't affect Search rankings

**Actual risk:** The legitimate concern is UX degradation — users on slow connections who don't scroll immediately may experience broken or non-functional widgets. NitroPack's guidance is to use Strong mode (not Ludicrous) for most production sites. This is the correct answer.

**Lesson for SwiftPress:** Never market "gaming Lighthouse scores." If deferring JS, be transparent about the tradeoffs and give users clear guidance on when it's appropriate.

### 6.4 Benchmark Results (Third-Party Tests, 2025-2026)

| Test Source | Before | After | Notes |
|-------------|--------|-------|-------|
| WPMarmite | Desktop 81, Mobile 55 (PSI) | Desktop 98, Mobile 74 | 19-plugin WP site, Ludicrous |
| Bloggingwizard | LCP 1.78s, Speed Index 1.44s | LCP 0.477s, Speed Index 0.536s | Page weight 1194KB → 387KB |
| BloggingX | 7s load time (blog post) | 3.0s | 57% speed improvement |
| Kripesh Adwani | 11.2s | 3.2s | GTmetrix test |

These are best-case results on test sites. Real-world gains depend heavily on theme, plugins, and starting state.

---

## 7. Nginx Support (Specific Analysis)

### 7.1 What Works on Nginx

Because NitroPack is cloud-based, most optimizations work regardless of web server:
- Full-page caching (delivered from CDN, not server-level)
- Image optimization, WebP conversion
- JS/CSS minification and combination
- Critical CSS generation
- All code optimizations
- CDN delivery
- Cache Warmup and Smart Invalidation

### 7.2 What Does NOT Work on Nginx (Apache-Only Features)

**Browser Cache headers** — NitroPack's browser caching rules use `.htaccess` directives. Nginx does not use `.htaccess`. This means:
- Browser cache rules must be **manually configured** by a server administrator in nginx.conf or a virtual host config
- Most shared Nginx hosting users cannot do this
- NitroPack's docs say: "contact your hosting provider and ask them to enable these options"

**Compression (gzip/brotli)** — Same issue. Nginx handles compression at the server level in config files, not `.htaccess`. Must be set up separately.

### 7.3 Nginx Helper Integration

NitroPack officially supports integration with the **Nginx Helper** WordPress plugin:
- Nginx Helper manages Nginx's FastCGI page cache
- NitroPack's integration hooks into Nginx Helper's cache purge events
- When NitroPack's Smart Invalidation triggers, it also signals Nginx Helper to purge the corresponding Nginx-level cache
- Result: both the NitroPack CDN cache and the server-side Nginx page cache stay in sync
- Described as improving Cache Hit Ratio and reducing server load during traffic spikes
- Setup is "out-of-the-box" once both plugins are active — no manual configuration documented

### 7.4 Reverse Proxy / Nginx as Proxy

If Nginx is used as a reverse proxy (common on Cloudways, Cloudflare, custom stacks):
- Navigate to NitroPack dashboard → Integrations → Reverse Proxy
- Provide: HTTP methods, list of reverse proxy server IPs
- NitroPack then syncs cache purge events with the reverse proxy layer

### 7.5 Nginx Summary Assessment

For SwiftPress comparison: NitroPack's Nginx support is **functionally adequate but not a strength**. Browser caching and compression require manual server config or hosting provider intervention. A plugin like SwiftPress that generates nginx.conf snippets or can set proper Cache-Control headers via PHP for nginx setups would have a genuine edge over NitroPack here.

---

## 8. Weaknesses and Honest Criticisms

1. **Pricing is expensive and punishes growth.** Pageview-based billing means a successful site faces escalating costs. Going from Plus ($22/mo) to Pro ($99/mo) is a $924/year jump. WP Rocket is $59/year flat.

2. **Free plan is virtually unusable.** 1,000 pageviews and the NitroPack badge make it a demo. Not suitable for any real site. Purely a lead-capture tier.

3. **No database optimization.** A significant gap compared to WP-Optimize or WP Rocket+Query Monitor setups.

4. **Can break sites.** Aggressive automation is double-edged. JS delay in Ludicrous mode breaks interactive elements. "Combine JS" and "Remove Unused CSS" are off by default for a reason — they break most sites when enabled.

5. **AdSense/ad revenue issues.** Multiple user reports of revenue drops after enabling NitroPack. Delayed JS loading interferes with ad scripts.

6. **Support quality complaints.** G2 and other review platforms show complaints about slow response times and "growing pains." The Fin AI chatbot resolves 42.7% of issues — meaning 57% require human follow-up with variable wait times.

7. **Split dashboard UX.** WordPress users must leave wp-admin for real configuration. This breaks the mental model of "everything is in my WordPress admin."

8. **Vendor lock-in (practical):** While they claim "no vendor lock-in" (files are untouched), your cache is on their servers, your CDN is their CDN, and your optimization pipeline depends entirely on their uptime. Switching costs are high in practice.

9. **Nginx limitations.** Browser cache and compression require manual server-level config. See Section 7.

10. **Limited CDN flexibility.** You cannot use a different CDN provider as primary delivery. The CDN is their infrastructure only.

11. **JS-heavy site incompatibility.** Sites with complex JavaScript (custom React/Vue frontends, heavy AJAX) may require expert developer intervention to configure NitroPack without breaking functionality.

12. **Site removal requires support.** Cannot self-service delete a site from account.

---

## 9. Feature Ideas Worth Borrowing (Legal — Inspiration Only)

### Idea 1: Per-Page Critical CSS (Not Per-Template)
NitroPack generates unique Critical CSS for each individual URL, not just each page type/template. This produces better FCP on pages where the same template yields different above-fold content (e.g., a WooCommerce product page where the hero image varies). SwiftPress could queue Critical CSS generation per URL on first visit (with a headless Chrome or Puppeteer call) and cache the result, storing it per URL rather than per post type.

### Idea 2: Dependency-Aware Cache Invalidation
Rather than "purge everything" or "purge only this post," NitroPack maps content relationships: post → its category archive → homepage's recent posts widget → any page showing this post in a related list. SwiftPress could build a lightweight dependency registry using WordPress hooks (post saved, term updated, etc.) and propagate invalidation only to cache keys that depend on the changed object. This prevents both stale cache (full-keep) and performance collapse (full-purge).

### Idea 3: Optimization Mode Presets with Explicit Tradeoff Labels
NitroPack's mode selector is powerful UX: one click applies a coherent bundle of settings. SwiftPress should have similar presets (Safe / Balanced / Aggressive) with each mode's settings listed explicitly in the UI — so users understand what "Aggressive" enables rather than getting a mystery bundle. The key addition SwiftPress could make: show a compatibility risk indicator per setting (green/yellow/red) based on detected plugins.

### Idea 4: Cache Insights Dashboard (Per-URL Status)
A per-URL cache status table with hit ratio graphs is valuable for debugging and demonstrating value to clients. SwiftPress could expose this via an admin screen: each URL row shows whether it's cached, when it was last generated, its file size, whether it's been warmed, and its PageSpeed score (pulled via PSI API). Useful for agencies showing clients the plugin is working.

### Idea 5: Cart Cache (WooCommerce)
NitroPack's Cart Cache is elegant: it finds a way to keep serving optimized cached pages to visitors who have items in cart, rather than bypassing cache entirely for logged-in/carted users (the typical WooCommerce caching problem). SwiftPress should implement a similar strategy — separate the dynamic cart fragment from the static page shell, and serve the page shell from cache regardless of cart state.

---

## 10. Sources

- https://nitropack.io/pricing/
- https://nitropack.io/features/
- https://nitropack.io/blog/how-nitropack-works/
- https://nitropack.io/blog/smart-cache-invalidation-by-nitropack/
- https://nitropack.io/blog/nitropack-speeds-up-250k-websites-results/
- https://support.nitropack.io/en/articles/8390264-reverse-proxy-integration-varnish-nginx-etc
- https://support.nitropack.io/en/articles/8613988-using-nitropack-with-nginx-helper-the-perfect-integration-for-sites-on-nginx-servers
- https://support.nitropack.io/en/articles/8390245-hosting-providers-compatibilities
- https://support.nitropack.io/en/articles/8390314-which-settings-are-enabled-in-each-optimization-mode
- https://support.nitropack.io/en/collections/6175714-features
- https://bloggingx.com/nitropack-review/
- https://bloggingwizard.com/nitropack-review/
- https://wpmarmite.com/en/nitropack-review/
- https://ecommercebonsai.com/nitropack-review/
- https://kripeshadwani.com/nitropack-review/
- https://cybernaira.com/nitropack-vs-wp-rocket/
- https://wpjohnny.com/nitropack-cloud-caching-and-cdn-service-review/
- https://bloggingx.com/nitropack-review/
- https://facileway.com/nitropack-pricing-plans/
