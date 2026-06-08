# Autoptimize — Competitor Dossier

**Research date:** 2026-06-08
**Plugin page:** https://wordpress.org/plugins/autoptimize/
**Official site:** https://autoptimize.com/
**Developed by:** Frank Goossens (Optimizing Matters, Belgium)
**Current version:** 3.1.15.1
**Active installs:** 900,000+
**WP.org rating:** 4.7/5 (1,286 five-star reviews out of ~1,425 total)

---

## 1. Positioning

Autoptimize occupies a very specific niche: **free front-end asset optimizer that deliberately does NOT do page caching**. It is the canonical "do one thing well" optimization plugin. The plugin is positioned as:

- The go-to free tool for CSS/JS aggregation, minification, and deferral
- A complement to caching plugins (WP Super Cache, W3 Total Cache, LiteSpeed Cache, WP Rocket), not a replacement
- A modular building block for custom performance stacks
- The developer-friendly option with a rich filter/hook API for extension

The official tagline leans on simplicity: "Speed up WordPress. For free."

**Market position in 2026:** Still relevant as a free layer-2 optimizer, but under pressure from all-in-one plugins (WP Rocket, LiteSpeed Cache, NitroPack) that bundle equivalent JS/CSS optimization alongside caching. Its 900K installs reflect legacy adoption; many users pair it with a caching plugin rather than switching to an all-in-one solution.

---

## 2. Pricing Tiers (2026)

### Free (Core Plugin — WordPress.org)
- Full CSS/JS/HTML minification and aggregation
- Google Fonts optimization (combine, swap, preconnect)
- Lazy loading for images and iframes (WebP/AVIF)
- WordPress emoji removal
- Query string removal from static resources
- Block library CSS removal
- Preconnect/preload resource hints
- Third-party async JS support
- Per-page/post optimization metabox
- LCP image preload field
- CDN base URL configuration
- Extensive developer filter API
- **No official free-tier support** — community forums only

### Autoptimize Pro (Subscription)
**Single Site:**
- €/$12.99/month
- €/$89/year

**5 Sites:**
- €/$39.99/month
- €/$379/year

**Pro adds:**
- Image optimization and CDN delivery (powered by ShortPixel)
- Automatic Critical CSS rule generation (server-side, per page type)
- Page caching
- "Pro Boosters": delayed loading of JavaScript, iframes, HTML triggered by user interaction
- Configuration Wizard with 5 optimization presets + settings backup/restore
- Priority support

**Accelera:** Premium professional services tier (bespoke performance consulting, pricing on request).

**Note on ShortPixel credits:** The free version integrates ShortPixel image optimization with 500 free credits on activation, with an additional 1,100 credits (1,000 one-time + 100/month) if signing up to ShortPixel from within the plugin. Beyond free credits, ShortPixel CDN/optimization is a paid add-on.

**criticalcss.com:** Used to be a separate power-up at ~£7/month. As of Autoptimize 2.7 (released ~2020), Critical CSS functionality was integrated natively into the free plugin — users can paste generated critical CSS manually. Fully automated generation requires the Pro plan.

---

## 3. Full Feature List

### CSS Optimization
- Minify CSS (removes whitespace, comments)
- Aggregate CSS files into a single file (opt-in; disabled on HTTP/2 servers by default since it degrades performance under multiplexing)
- Inline and defer CSS: inlines critical CSS in `<head>`, defers full stylesheet load to avoid render-blocking
- Convert image URLs to data:URIs (embed small images directly in CSS — reduces HTTP requests)
- Exclude specific CSS files from optimization via path/filename matching
- "Remove unused CSS" via partner plugin RapidLoad (separate paid plugin)
- Full CSS inlining option (embeds entire stylesheet in HTML — useful for very small CSS)

### JavaScript Optimization
- Minify JS files
- Aggregate JS files (optional; default off for HTTP/2)
- Inline JS aggregation (merges inline `<script>` blocks into aggregated file)
- Move JS to footer (render-unblocking)
- Async/defer non-aggregated JS
- JS error-handling wrapper
- Exclude specific scripts from aggregation (by filename or path, one per line)

### HTML Optimization
- Minify HTML (whitespace/comment removal)
- Keep HTML comments option (useful for conditional IE comments, etc.)

### Image Optimization
- Lazy loading for `<img>` and `<iframe>` tags (native + JS fallback)
- WebP format support via ShortPixel CDN integration
- AVIF format support toggle
- Image optimization via ShortPixel CDN (on-the-fly — first visit triggers optimization, cached thereafter)
- Exclusion field for specific images
- Compression quality selector: lossy, glossy, lossless
- Per-image LCP preload field in the post/page metabox

### Critical CSS
- Manual paste field: paste externally generated critical CSS into a text area
- Path-based rules: define different critical CSS per URL pattern
- Pro: Automatic rule generation (headless browser-based, per page type)
- Integration pathway with criticalcss.com service (API key field)

### Google Fonts
- Combine Google Fonts requests into a single request (reduces HTTP round trips)
- Add `font-display: swap` parameter to prevent invisible text during font load
- Add `preconnect` hints to `fonts.googleapis.com` and `fonts.gstatic.com`
- Remove Google Fonts entirely option (for sites self-hosting fonts)

### Extra / Misc Features
- WordPress core emoji CSS/JS removal
- Query strings removal from static resource URLs (avoids cache bypassing)
- WordPress block library (Gutenberg) CSS removal option
- Third-party domain preconnect declaration field
- Specific resource preload field (declare resources to `<link rel="preload">`)
- Async JS for non-aggregated scripts
- YouTube facade (replace YouTube embeds with a lightweight thumbnail that loads the iframe only on click)
- CDN base URL rewriting (rewrites Autoptimized file URLs to a CDN origin)
- Static file saving: saves aggregated CSS/JS as physical `.css`/`.js` files in `wp-content/cache/autoptimize/`
- 404 fallback: PHP fallback handler if static files are deleted or not found
- Disable optimization for logged-in users (reduces cache variations)
- Per-page/post metabox control (enable/disable JS/CSS optimization per post, add LCP preload)

### Developer API
- Over 30+ WordPress filters for deep customization
- `autoptimize_filter_show_partner_tabs`: hide partner/upsell tabs
- `autoptimize_filter_cache_create_static_gzip`: enable pre-gzip of cache files
- `autoptimize_filter_cachecheck_maxsize`: change cache size warning threshold
- `autoptimize_filter_js_exclude`, `autoptimize_filter_css_exclude`: programmatic exclusions
- Partner plugin architecture: power-ups can register as add-ons via hooks
- `autoptimize_helper.php_example`: example helper file showing common customization patterns

---

## 4. Admin Panel / UX

### Information Architecture

Settings are found at **Settings > Autoptimize** in the WP admin sidebar. The panel uses a **horizontal tab navigation** at the top of the settings page.

**Tab Structure:**
1. **JS, CSS & HTML** — primary tab; contains the bulk of optimization controls, organized into three collapsible sub-groups (JavaScript Options, CSS Options, HTML/Misc Options)
2. **Images** — image lazy loading and ShortPixel CDN integration
3. **Critical CSS** — paste field for critical CSS rules, path-based rule management
4. **Extra** — Google Fonts, emoji removal, query strings, block CSS, preconnect, preload, async JS, YouTube optimization
5. **Optimize More!** — partner/upsell tab listing complementary plugins (RapidLoad for unused CSS, other ShortPixel products); can be hidden with a filter
6. (Pro only) **Wizard** — configuration presets with backup/restore

### Default Settings Philosophy

Autoptimize adopts a **conservative, opt-in defaults** strategy to avoid breaking sites on first install:

- CSS optimization: **OFF by default** (enable manually)
- JS optimization: **OFF by default**
- Aggregation: **OFF by default** on HTTP/2-detected servers (intelligently detects server protocol)
- Inline JS/CSS aggregation: **OFF by default** (the most common cause of cache bloat)
- HTML minification: available but not prominently recommended due to edge-case breakage risks
- Images lazy loading: **OFF by default** (enable manually)

This means a freshly installed Autoptimize does nothing until the user actively enables features. This avoids the "plugin broke my site on install" complaint but also means **no immediate impact visible to new users** — a UX friction point.

### Key UX Patterns

**1. The "Save Changes & Empty Cache" dual-action button**
The primary submit button at the bottom of the settings page combines two operations: it saves settings AND clears the optimization cache simultaneously. This ensures optimized files are always regenerated after a settings change. However, it creates UX friction because: (a) users must scroll to the bottom of a long settings page to save, (b) cache clearing is implicit and not visually confirmed, and (c) it has spawned a secondary ecosystem of "Autoclear Autoptimize Cache" plugins to automate this.

**2. Cache Info display**
The settings page shows a "Cache Info" section displaying:
- Cache folder path
- Whether the folder is writable (with explicit YES/NO indicator — shows "Can we write? YES/NO")
- Current cache size
- Delete cache button

This is a useful diagnostic panel surfaced at a glance, not buried in a tools/status page.

**3. Exclusion-based configuration**
Rather than a visual dependency tree or conflict resolver, Autoptimize uses plain-text input fields (one entry per line) for excluding scripts and stylesheets. Advanced and flexible but requires the user to know filenames/paths. The recommended troubleshooting workflow is: enable feature → test → add exclusions for anything that breaks → repeat.

**4. Per-page metabox (opt-in)**
A checkbox in Misc Options enables a WordPress metabox on post/page edit screens, allowing per-post override of optimization settings and an LCP preload field. Useful for pages with custom JavaScript that conflicts with global aggregation, but requires navigating to each affected post.

**5. "Optimize More!" upsell tab**
A dedicated tab surfaces partner plugin recommendations and paid add-ons. This is a deliberate, transparent upsell mechanism. Users who don't want to see it can hide it via a code filter, which is good developer UX — the option to suppress commercial messaging is documented.

**6. Critical CSS paste-and-manage UX**
The Critical CSS tab has a multi-rule interface: users can define a "default" critical CSS block plus path-specific overrides (e.g., /shop/, /product/, specific post IDs). The UX correctly separates the concept of "what critical CSS is for a given page type" from the global optimization settings. However, generating the actual critical CSS is left entirely to the user (external tool, manual process) in the free version — a high friction point.

**7. No visual diff or before/after PageSpeed display**
Autoptimize provides no built-in performance comparison. There is no "test your site speed" panel, no PageSpeed/Core Web Vitals score shown, no before/after cache stats. Users must use external tools (GTmetrix, PageSpeed Insights) to measure impact.

### Onboarding Experience

There is no formal onboarding wizard in the free version. Users land directly on the settings tabs and must configure everything manually. The plugin's wordpress.org page and external guide sites (onlinemediamasters.com being the most widely cited) effectively serve as the onboarding documentation.

The **Pro Wizard** (launched January 2026) is the first formal guided onboarding in the plugin's history. It provides 5 optimization presets with automatic backup of prior settings, allowing users to switch between preset configurations safely. The presets themselves (names/details not publicly documented) represent the closest thing to "smart defaults with guardrails" in the Autoptimize ecosystem.

---

## 5. AI / Automation Assessment

**Real AI/ML:** None in the core free plugin.

**ShortPixel image optimization (integrated in free tier):**
ShortPixel markets its engine as "AI-powered" compression, implying ML-based quality selection. In practice, the "AI" refers to proprietary compression algorithms that make quality/size trade-off decisions per image — this is algorithm-driven automation, not conversational LLM or generative AI. The ShortPixel engine is genuinely sophisticated (it supports lossy/glossy/lossless modes and produces WebP/AVIF), but calling it "AI" is marketing language.

**Critical CSS generation:**
The criticalcss.com service (used by Autoptimize's automated critical CSS feature in Pro) uses a **headless browser** (Puppeteer/Chromium) to render pages and extract above-the-fold CSS. This is rule-based automation, not ML. There is no intelligence involved — it renders the page, measures viewport, collects styles applied to visible elements.

**RapidLoad AI (partner plugin, separate product):**
RapidLoad AI markets "AI diagnostics" and "AI Support Chat." The AI Support Chat appears to be an LLM-powered chatbot. The "AI Diagnostics" feature claims to analyze bottlenecks and provide actionable insights, but no technical specification is given about the model or methodology. The core optimization (unused CSS removal, critical CSS, JS deferral, image optimization) is conventional rule-based automation. Verdict: **the AI branding is 80–90% marketing** for the core features; the chat support may be a genuine LLM wrapper but is ancillary to optimization itself.

**Autoptimize Pro Boosters:**
"Delay JavaScript execution until user interaction" — this is a JS interception pattern (attach event listener to `mousemove`/`touchstart`/`keydown`, then load deferred scripts on first interaction). Pure automation, no AI component.

**Conclusion:** Autoptimize has zero genuine AI/ML in its current feature set. ShortPixel integration and critical CSS automation are sophisticated rule-based tools marketed under AI branding. This creates a real opportunity for SwiftPress to differentiate with actual on-demand LLM-powered features.

---

## 6. Performance Methodology

### Core Approach
Autoptimize operates entirely at the **WordPress output buffer level** — it hooks into PHP output buffering, parses the generated HTML, finds CSS/JS references and inline code blocks, processes them (minify, aggregate, save), and rewrites the HTML with new file references before sending to the browser.

This is a **PHP-first approach** — all optimization happens server-side in PHP on first page view. Subsequent requests are served static cached files from `wp-content/cache/autoptimize/`.

### Key Methodological Decisions

**1. Aggregation vs. HTTP/2**
The developer (Frank Goossens) publicly addressed the HTTP/2 aggregation dilemma in a blog post. The conclusion: HTTP/2 multiplexing reduces but does not eliminate the value of file concatenation. Autoptimize now **defaults to no aggregation on HTTP/2 servers** (detected via server environment). On HTTP/1.1 servers, aggregation remains the default for CSS (when enabled). Users can override defaults either way.

**2. Minification library**
Uses JSMin and similar libraries for JS minification. Not the most aggressive minifier (e.g., Terser or esbuild for JS are more powerful) but PHP-native and reliable. CSS minification is similarly modest.

**3. Static file caching**
Optimized files are saved as `.css` and `.js` files in a flat-ish directory structure under `wp-content/cache/autoptimize/`. This allows web servers (both Apache and Nginx) to serve them directly as static assets, bypassing PHP entirely on cached requests.

**4. Cache invalidation**
Cache is invalidated on: manual "Empty Cache" button click, WordPress plugin/theme update (via action hooks), and when settings change (via the combined save+empty button). There is no TTL-based expiration or automatic periodic purge.

**5. The inline aggregation trap**
"Aggregate Inline JS" and "Aggregate Inline CSS" options attempt to merge `<script>` and `<style>` blocks directly in HTML into the aggregated file. This frequently causes cache explosion because inline code often contains dynamic values (nonces, post IDs, timestamps), creating a unique cache entry per page. This is the #1 cause of the "cache is 900MB" complaint. The developer documented this as a "canary in the coal mine" behavior — cache growth signals a configuration problem.

**6. Critical CSS methodology**
Free: user generates critical CSS externally (manually or via third-party service), pastes CSS text into a settings field, configures path-based rules. This is entirely manual.
Pro: headless browser renders each page type, extracts viewport-visible CSS, stores per-rule. Automatic but not real-time — rules are generated on demand and cached.

**7. Image optimization**
On-the-fly ShortPixel CDN: first visitor to a page triggers image optimization requests to ShortPixel API. Subsequent visitors see optimized images from ShortPixel's CDN. Original files on the server are never modified. This means **first-visitor experience is unoptimized** until ShortPixel has processed the image.

### Performance Impact (Measured)
- File aggregation: 11 CSS files + 15 JS files → 1–2 files (48% file size reduction measured in one test)
- PageSpeed improvement with WP Super Cache + Autoptimize vs. baseline: 67% load time improvement
- vs. WP Rocket (2026 benchmark): 140ms worse INP, slower LCP due to lack of automatic critical CSS in free tier

---

## 7. Nginx Support

### Summary
**Nginx is supported but requires manual server configuration.** Autoptimize does not and cannot write nginx config files. All features that would normally require `.htaccess` on Apache must be configured manually by a server admin on nginx.

### What Works Without Configuration
- Core optimization (PHP-based parsing and minification) — works identically on any server
- Static file generation in `wp-content/cache/autoptimize/` — PHP creates files, works on any server
- ShortPixel CDN integration — fully server-independent
- Google Fonts, emoji removal, etc. — all PHP-side, server-agnostic

### What Requires Manual Nginx Configuration

**1. Serving cached static files**
Autoptimize generates static `.css` and `.js` files. To serve them efficiently, nginx needs a location block:

```nginx
location ~* /wp-content/cache/autoptimize/.*\.(js|css)$ {
    try_files $uri =404;
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary Accept-Encoding;
}
```
Without this, WordPress/PHP will handle these requests, adding unnecessary overhead.

**2. Gzip/Brotli compression of static files**
Autoptimize optionally pre-generates `.gz` files alongside CSS/JS (via a filter: `autoptimize_filter_cache_create_static_gzip`). For nginx to serve these pre-compressed files, `gzip_static on;` must be set in the nginx config. Without this, on-the-fly gzip must be configured separately.

**3. 404 fallback**
If a cached file is missing, Autoptimize includes a PHP-based 404 fallback handler. On Apache, this is configured automatically via `.htaccess` rewrite rules. On nginx, the equivalent `try_files` or `error_page 404` directive must be manually added:

```nginx
location ~* /wp-content/cache/autoptimize/.*\.(js|css)$ {
    try_files $uri @autoptimize_fallback;
}
location @autoptimize_fallback {
    rewrite ^/(.*)$ /wp-content/cache/autoptimize/ao_fallback.php?ao_type=css&ao_file=$1 last;
}
```

**4. Expiry headers for static assets**
Autoptimize generates cache-busted filenames (hash-based), so long expiry headers are safe. Apache `.htaccess` handles this automatically via mod_expires. On nginx, the admin must add expiry headers manually in the static files location block.

### Practical Reality for Nginx Users
On managed hosting (Runcloud, GridPane, Serverpilot, Cloudways), Autoptimize typically works without issues because the control panels pre-configure appropriate nginx rules. On raw VPS nginx installations, users frequently encounter:
- 404 errors on optimized CSS/JS files (missing try_files fallback)
- No compression of optimized assets (missing gzip_static)
- Cache control headers not set (poor browser caching scores)

Autoptimize's documentation does not prominently address nginx-specific configuration. Users must find community articles or support forum threads.

---

## 8. Known Weaknesses

1. **No page caching** — The #1 limitation. Requires pairing with a separate caching plugin. Creates multi-plugin dependency and conflict risk.

2. **No built-in performance measurement** — No Core Web Vitals score, no PageSpeed display, no before/after comparison. Users fly blind without external tools.

3. **Cache bloat from inline aggregation** — "Aggregate Inline JS/CSS" options cause cache explosion on dynamic sites (900MB+ cache common for sites with unique inline scripts per page).

4. **Critical CSS manual process** — Free tier critical CSS generation requires external tools and manual pasting. Competitors like WP Rocket automate this entirely. The manual workflow is high-friction and error-prone.

5. **HTTP/2 aggregation ambiguity** — The plugin's value proposition (file concatenation) is partially undermined by HTTP/2 multiplexing. On HTTP/2 servers, aggregation is disabled by default but users are not clearly guided on what to do instead.

6. **JS aggregation breaks sites** — JavaScript load order and execution timing issues are the most common source of site breakage. The troubleshooting workflow (disable → exclude → re-enable) requires technical knowledge not all users have.

7. **No free support** — Zero official support for the free tier. WordPress.org forums are monitored but response is inconsistent. The developer is responsive but has limited bandwidth.

8. **Security history** — 12 documented vulnerabilities since the plugin's history, including a CVSS 9.1 RCE via race condition (fixed in 2.7.8) and a CVSS 8.8 unauthenticated stored XSS (April 2026). Multiple XSS vulnerabilities across contributor+ and admin permission levels suggest recurring input validation discipline issues in the minification codebase.

9. **No nginx documentation** — nginx setup requires manual server config that is not documented in the plugin itself.

10. **No wizard in free tier** — The configuration wizard (5 presets, backup/restore) is Pro-only as of 2026. Free users must self-configure from scratch.

11. **First-visitor image optimization lag** — ShortPixel CDN integration optimizes images on first request. The first visitor always gets the unoptimized original.

12. **Redundancy with all-in-one caching plugins** — WP Rocket, LiteSpeed Cache, and NitroPack include equivalent JS/CSS optimization. Autoptimize becomes redundant for users of those plugins, shrinking its addressable market over time.

---

## 9. UX/Feature Ideas Worth Borrowing for SwiftPress

### Idea 1: Dual-role save button with cache awareness
The "Save Changes & Empty Cache" button pattern ensures the user never has stale cached assets after a settings change. SwiftPress should similarly couple settings saves to automatic cache invalidation. The UX could be improved by showing a brief confirmation: "Settings saved. 47 cached files cleared."

### Idea 2: Per-page/post metabox with LCP preload field
Autoptimize's per-post metabox lets users declare a specific image URL to be preloaded for LCP on that specific page. This is a surgical, post-level control that all-in-one plugins often miss. The LCP preload field costs ~1 hour to implement but can materially improve Core Web Vitals scores on image-heavy pages.

### Idea 3: Exclusion field design (text-area, one-per-line)
Simple, no-UI-overhead exclusion management. No drag-drop lists, no dropdowns. A plain textarea where users enter filenames/paths one per line. This is low-friction for developers and technically capable users. SwiftPress should implement similar exclusion fields for its JS/CSS optimization features.

### Idea 4: Cache Info diagnostic panel
Surfacing writable status, cache path, and current cache size on the settings page — rather than buried in a tools/status screen — gives admins immediate system awareness. The "Can we write?" YES/NO indicator is particularly useful for catching permission issues before they cause silent failures.

### Idea 5: Partner tab with clear disclosure + filter to suppress
Autoptimize monetizes through a visible "Optimize More!" tab listing paid add-ons but provides a developer filter to suppress it. This is honest monetization — visible but not intrusive, and respectable enough to give developers control. SwiftPress could adopt a similar pattern for surfacing the AI power-up (BYO OpenRouter key) without making it feel predatory.

---

## 10. Concrete UX/Feature Ideas to Steal (Inspiration, Not Code)

### Steal 1: The "5 Optimization Presets" Wizard Pattern
**What:** Autoptimize Pro's 2026 wizard offers preset configurations (conservative, balanced, aggressive) with automatic backup of prior settings before applying a preset.

**Why it matters for SwiftPress:** Most users don't know which optimization combination is safe for their site. Presets with human-readable names ("Safe", "Standard", "Aggressive", "WooCommerce") reduce cognitive load and support tickets. Backup/restore means users can experiment without fear.

**Implementation for SwiftPress:** Build a wizard tab that: (1) detects server type (nginx/apache), (2) checks if other caching plugins are active, (3) offers 4–5 labeled presets with descriptions, (4) snapshots current settings to DB before applying. This is a medium-effort feature (2–3 days) but dramatically improves onboarding.

### Steal 2: Per-Page LCP Image Preload Field
**What:** A simple input field in a post/page metabox where the editor specifies the URL of the LCP image to preload.

**Why it matters:** LCP is the highest-weighted Core Web Vitals metric for SEO. A manually specified preload tag is more reliable than automatic LCP image detection (which can get it wrong or miss lazy-loaded images). This is a low-effort feature that many premium plugins ($49–$249/year) offer.

**Implementation for SwiftPress:** Register a metabox on all public post types. Input = single URL field. On front-end render, inject `<link rel="preload" href="[url]" as="image" fetchpriority="high">` in `<head>`. ~4 hours of dev time. Pair with SwiftPress's LCP detection to auto-suggest the likely LCP image as the default value — this is where the AI advantage comes in.

### Steal 3: Cache Size Warning System with Actionable Diagnosis
**What:** Autoptimize sends an email warning when cache exceeds 500MB and surfaces the cache size in the settings panel. The developer even wrote a blog post explaining that large cache size = a specific misconfiguration (inline aggregation enabled on a dynamic site).

**Why it matters:** Cache bloat is a real problem that silently fills disk space. Most plugins either ignore it or just show a number. Autoptimize's insight — that cache size growth is a symptom, not a root cause — is valuable. SwiftPress can automate the diagnosis.

**Implementation for SwiftPress (with AI advantage):** Track cache size growth rate. When it exceeds a threshold (e.g., 200MB or grows >50MB/week), trigger an AI analysis call (LLM via OpenRouter) that examines: cache file count, unique vs. repeated patterns, enabled settings. Return a plain-language recommendation: "Your cache contains 4,200 unique files. This is caused by dynamic inline script values being aggregated. Disable 'Aggregate Inline JS' to fix this." This costs ~$0.0001 per diagnosis call and creates a genuinely useful, unique feature.

---

## Sources Consulted

- https://wordpress.org/plugins/autoptimize/
- https://autoptimize.com/pro/
- https://autoptimize.com/
- https://onlinemediamasters.com/autoptimize-settings/
- https://kinsta.com/blog/autoptimize-settings/
- https://wbcomdesigns.com/autoptimize-review/
- https://fatlabwebsupport.com/blog/website-optimization/autoptimize-review/
- https://www.hostingradar.io/en/blog/autoptimize-review
- https://www.nichepursuits.com/autoptimize-review/
- https://blog.futtta.be/2025/12/30/aopro-soon-to-have-a-wizard-tab/
- https://blog.futtta.be/2022/05/05/what-to-do-when-autoptimize-breaks-your-site/
- https://blog.futtta.be/2015/12/18/http2-cssjs-concatenation-and-autoptimize/
- https://github.com/futtta/ao_critcss_aas
- https://github.com/centminmod/autoptimize-gzip
- https://shortpixel.com/knowledge-base/article/how-does-shortpixel-ai-cdn-work/
- https://wpscan.com/plugin/autoptimize/
- https://wordpress.org/plugins/unusedcss/
- https://www.infineural.com/wp-rocket-vs-autoptimize-for-core-web-vitals/
- https://debugpress.com/a-step-by-step-guide-to-minifying-assets-with-the-autoptimize-plugin/
- https://bobcares.com/blog/autoptimize-cache-size-warning/
