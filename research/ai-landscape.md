# SwiftPress AI Opportunity Brief — Performance/Cache Plugin Landscape 2026

**Author:** AI product strategist (research pass)
**Date:** 2026-06-08
**Subject:** Where AI is (and isn't) used in WordPress performance/cache plugins, and how SwiftPress can build a *real* AI advantage with a BYO-key, server-side, on-demand model.
**Audience:** SwiftPress owner (GPL plugin, nginx, already uses OpenRouter).

---

## 0. TL;DR (read this first)

1. **Almost nobody in the cache-plugin category ships a true LLM feature.** What competitors call "AI" is overwhelmingly (a) rule-based automation with manually-maintained safelists (WP Rocket, Perfmatters, 10Web, LiteSpeed) or (b) predictive-prefetch *machine learning* that guesses the next page (NitroPack "Navigation AI", Cloudflare "Speed Brain"). **Neither is a generative LLM, and none of them translate an audit into plain-language advice or apply fixes for you.**
2. **The open lane is the "AI Performance Diagnostic":** run a real audit (PageSpeed Insights API / your own metrics), feed the *numbers* to an LLM, and get back (a) a plain-language explanation a non-technical owner understands and (b) a concrete, validated settings diff that maps to actual SwiftPress toggles, applied with one click. **No competitor does this.** This should be the headline.
3. **CSS work (Critical CSS / Remove Unused CSS) is NOT an LLM job.** WP Rocket's own engineering blog confirms theirs is Puppeteer + a custom JS library + a hand-maintained safelist — pure algorithm, zero AI. SwiftPress should keep CSS algorithmic and let the LLM *explain* and *triage* it, not generate it.
4. **BYO-key is the right model and there is direct precedent** (AltText AI is BYO-key + credits). The hard parts are (a) never leaking the key to the browser, (b) never writing it into the world-readable `sp-config` drop-in, (c) hard cost ceilings, and (d) graceful degradation to the existing rule-based behaviour when no key is set. SwiftPress's codebase already strips `cloudflare_*` secrets before writing the config file (`Config.php` lines 630–634) — reuse that exact precedent for the LLM key.
5. **Cost is a non-issue if architected on-demand.** Gemini 2.5 Flash-Lite is ~$0.10/M input, ~$0.40/M output. A full diagnostic run is a few thousand tokens → well under $0.01. The danger is *per-pageview* calls (forbidden) and runaway loops — both solved with caching + a monthly spend cap.

---

## 1. Competitive landscape: real AI vs. "AI-washing"

| Product | Marketed as "AI"? | What it actually is | Delivery | True LLM? |
|---|---|---|---|---|
| **WP Rocket** | No (mostly) | Page cache + file optimization in-process. RUCSS/Used-CSS runs on their SaaS via **Puppeteer + custom JS lib + manual dynamic safelist** | SaaS (saas.wp-rocket.me) for CSS; rest in-process | **No** |
| **NitroPack** | Yes ("Navigation AI", "Fin AI") | "Navigation AI" = **ML predictive prefetch** (predict next page, prefetch it; shown at Google I/O 2024). "Fin AI" = a **support chatbot** (Intercom Fin) resolving ~42% of tickets. Core optimization (cache/img/CSS/CDN) is cloud automation, device/cookie-aware, **not** generative | Full SaaS / cloud proxy | **Partial** — ML for prefetch + an LLM *support bot*, but **not** for optimization decisions or user-facing diagnostics |
| **Perfmatters** | No | Script Manager (disable scripts per-page), defer/delay JS, RUCSS, lazy-load, local GA/fonts. Explicitly rule-based, "lightweight" positioning | In-process | **No** |
| **LiteSpeed Cache** | No (QUIC.cloud branding) | Server-level cache; QUIC.cloud online services do **image optimization (WebP/AVIF compression), Critical CSS, Unique CSS, VPI, LQIP** — all algorithmic image/CSS processing on their servers | LSCache (server) + QUIC.cloud (SaaS) | **No** |
| **10Web Booster** | Yes ("AI", "automated") | Minify/compress/cache + **Critical CSS generation per layout** + JS delay + Cloudflare Enterprise CDN. "AI" markets the *automation*, not a model. (10Web's separate AI Website Builder *does* use generative AI, but that's a different product from the Booster.) | SaaS-assisted (Critical CSS, CDN) | **No** (for the Booster) |
| **Cloudflare (Speed Brain)** | Partly | **Predictive prefetch** via the Speculation Rules API (prefetch most-likely next navigation). Roadmap mentions ML to improve prediction; today it's rule/heuristic-driven. Up to 45% LCP reduction on free plan | Edge | **Partial / roadmap** |
| **WordPress.com MCP / AI Engine / Elementor Angie** | Yes (genuinely) | **Real LLM agents** that write posts, manage media, fix alt text, change settings via MCP/natural language. *But these are site-management/content tools, not performance plugins.* | LLM via MCP/API | **Yes** — but **adjacent category**, not a cache competitor |

### What this tells us
- **The entire cache/perf category competes on rule-based automation and predictive prefetch.** The genuine LLM products (AI Engine, WordPress.com MCP, Elementor Angie) live in **content/site-management**, not performance.
- **NitroPack is the only perf vendor touching LLMs, and only for a support chatbot** — not for the optimization itself and not for user-facing diagnostics. That is the gap.
- **"Critical CSS / Remove Unused CSS" is universally algorithmic** (Puppeteer/headless render + selector diffing + manual safelists). Anyone implying an LLM does this is mistaken. This is decisive for feature scoping below.

---

## 2. Candidate AI features for SwiftPress — deep evaluation

For each: how it works technically, value, feasibility, rough cost per run (Gemini 2.5 Flash-Lite default: **$0.10/M in, $0.40/M out**; Flash if more reasoning needed: **$0.30/M in, $2.50/M out**), privacy (exact data leaving the site), and failure/fallback design.

SwiftPress setting keys referenced below are the real ones from `includes/utils.php` defaults and `includes/classes/Config.php`.

---

### 2.1 ★ HEADLINE — AI Performance Diagnostic ("Explain & Fix")

**The one feature that creates a category-of-one position.** Run an audit, have an LLM turn the raw numbers into plain English *and* a concrete, validated SwiftPress settings diff applied with one click.

**How it works technically (the pipeline):**
1. **Collect ground-truth metrics (no LLM yet).** On demand (button click), SwiftPress gathers a structured `audit` object:
   - **PageSpeed Insights API v5** (free, key-optional) for the homepage + 2–3 key URLs → CrUX field data + Lighthouse lab data (LCP, CLS, INP/TBT, TTFB, render-blocking resources, unused-CSS bytes, image-format opportunities, etc.). Lighthouse is consolidating audits into "insight groups" in 2025 (e.g. a single "CLS culprits" insight) — parse the `audits`/`insights` JSON, don't scrape the UI.
   - **SwiftPress's own state**: which of `enable_page_cache`, `minify_css`, `combine_css`, `critical_css`, `remove_unused_css`, `minify_js`, `combine_js`, `defer/delay JS`, `enable_font_optimization`, `self_host_google_fonts`, `font_preload`, `enable_cache_preload`, `enable_lcp_optimization`, `prefetch_dns`, `preconnect_resource` are on/off.
   - **Environment facts**: server is nginx (so `.htaccess` advice is suppressed and `nginx_rules()` guidance is shown instead), PHP version, detected page builder/theme, WooCommerce present?, object cache present?
2. **Build a compact, PII-free prompt.** Send only the *derived metrics and the current settings map* — never page HTML, never visitor data. Ask for **structured JSON output** (this is the single most important hallucination control in 2025 practice): a fixed schema with `summary` (plain language), `findings[]` (each: `issue`, `evidence_metric`, `severity`, `plain_explanation`), and `recommended_changes[]` (each: `setting_key`, `from`, `to`, `why`, `risk`, `confidence`).
3. **Constrain to a known action vocabulary.** The prompt includes the **whitelist of valid `setting_key`s and their allowed values**. The LLM may only propose changes drawn from that list — it cannot invent a setting. This turns "generate narrative" into "fill known fields", which is exactly what collapses the hallucination surface.
4. **Server-side validation gate (deterministic, post-LLM).** Before anything is shown as "apply-able", PHP validates every proposed change against the real schema: key exists? value in range? not mutually exclusive with another active setting? (e.g. don't enable `combine_css` *and* `remove_unused_css` in a conflicting way). Anything failing validation is **dropped**, not shown. The LLM proposes; **SwiftPress's own code disposes.**
5. **One-click apply = a diff the user confirms.** Render a human diff ("Turn ON Remove Unused CSS; turn ON font preload for your hero font; enable LCP optimization") with a single **Apply** button, plus per-item checkboxes. Apply writes through the *existing* settings save path (`save_configuration()`), so it inherits SwiftPress's current safety (secret-stripping, opcache invalidation, advanced-cache regeneration).
6. **Always reversible.** Snapshot settings before apply → "Undo last AI change" restores in one click. Re-run PSI after apply to show before/after deltas (closes the loop and proves value).

**Why an LLM genuinely helps here (vs. a static rules engine):**
- It **synthesizes across signals** and writes for *this* site's owner in plain language ("Your homepage takes 4.1s to show the main image because a Google Font is blocking rendering and your hero image isn't preloaded"). A static rules table can map "unused CSS bytes > X → enable RUCSS", but it can't *prioritize, explain the why in context, and triage trade-offs* in natural language — which is precisely the part non-technical owners pay for. The deterministic mapping is the safety floor; the LLM is the *communication and prioritization* layer on top.
- It degrades gracefully: **if no key, fall back to a built-in static rules table** that produces the same `recommended_changes[]` (minus the prose). So the feature exists for everyone; the LLM upgrades the experience.

**Value:** Very high. This is the #1 reason a non-technical owner would choose SwiftPress over WP Rocket. It reframes the plugin from "200 toggles I don't understand" to "tell me what's wrong and fix it." Nobody in-category offers it.

**Feasibility:** Moderate. PSI integration is well-trodden; the LLM call is trivial via OpenRouter. The real engineering is the **validation gate + action whitelist + undo snapshot** — but that's ordinary PHP, not ML.

**Rough cost/run:** Prompt ≈ 2–4k input tokens (metrics + settings + whitelist), output ≈ 1–2k tokens. On Flash-Lite: ~**$0.0005–0.001 per run**. Even on full Flash with reasoning: ~**$0.005–0.01**. Negligible, *because it's on-demand, not per-pageview.*

**Privacy — exactly what leaves the site:** Only **derived metrics + boolean/string settings values + environment facts (PHP version, server type, "WooCommerce: yes")**. No page HTML, no post content, no visitor/PII, no IP lists. URLs audited are the site's own public URLs (already public). This is a *data-minimized* call by design.

**Failure / fallback design:**
- *API error / timeout / no key:* fall through to the static rules engine; show "AI explanation unavailable — showing standard recommendations." Feature never hard-fails.
- *Hallucinated/invalid change:* killed by the validation gate before display. User never sees an un-appliable or out-of-range suggestion.
- *Plausible-but-wrong advice that passes validation:* mitigated by (a) confidence shown, (b) the change being a real, reversible SwiftPress toggle (not arbitrary code), (c) one-click undo, (d) re-audit showing it didn't help → user reverts. Worst case = a safe setting toggled and reverted.

---

### 2.2 AI Smart Cache Rules (exclusion detection)

Auto-detect URLs/pages that must **never** be cached (cart, checkout, login, my-account, member/restricted areas) and propose them as exclusions.

**How it works technically:**
- **Primary layer is deterministic and ships regardless of AI:** SwiftPress already excludes WP-admin, sitemaps, POST, query-strings, logged-in cookies in `nginx_rules()` / advanced-cache. Extend with a **detector** that enumerates: WooCommerce/EDD cart/checkout/account page IDs (from their options), `is_user_logged_in` gated pages, membership-plugin endpoints (MemberPress, Restrict Content, etc.), known cookie patterns. Most "exclusions" are knowable from the DB without any AI.
- **LLM as the gap-filler/explainer:** feed the LLM the **site's URL list + page titles + slugs + detected plugins** (NOT page bodies) and ask it to flag likely-dynamic/personalized routes the deterministic rules missed (e.g. a custom `/dashboard/`, `/account-area/`, `/quote-builder/`) and to **explain in plain words why** each should be excluded. Output is the same structured `exclusions[]` schema → user confirms → written to `rejected_uri` / `rejected_cookies` via existing config.

**Value:** High and *risk-reducing* — wrong caching of a cart page is the classic "my plugin broke checkout" disaster. Framing AI as the thing that *prevents* breakage (not causes it) is great positioning.

**Feasibility:** Easy–moderate. 80% is deterministic detection; the LLM is a thin "did we miss anything?" pass over slugs/titles.

**Rough cost/run:** ~1–3k input (URL list/titles) + small output → **<$0.001** on Flash-Lite. One-time/occasional, not per-request.

**Privacy:** URLs, slugs, page titles, active plugin list. All effectively public or low-sensitivity. No page content, no customer data.

**Failure/fallback:** No key → deterministic detector alone (still strong). Hallucinated exclusion = at worst an extra page not cached (safe — slower, never broken). Over-exclusion is the *safe* failure direction; the validation gate caps it (don't let the LLM exclude the homepage).

---

### 2.3 Critical CSS / Remove Unused CSS — **keep algorithmic, don't ask the LLM to write CSS**

**Verdict: this is NOT a good LLM feature, and the research proves why.** WP Rocket's own SaaS engineering write-up confirms their RUCSS is **Puppeteer (headless render) + a customized JS library + a manually-maintained dynamic safelist** — no AI. LiteSpeed/QUIC.cloud and 10Web do the same class of algorithmic render-and-diff. Generating or pruning CSS with an LLM is **slower, more expensive, and unsafe** (an LLM dropping a "used" selector breaks layout; token cost on large stylesheets is high; non-deterministic output is untestable).

**The right design:**
- **Do the CSS work algorithmically** (SwiftPress already has `critical_css` + `remove_unused_css` settings and bundles a CSS selector parser — Symfony CssSelector is in `includes/classes/Dependencies/Symfony/Component/CssSelector/`). Render the page (or use a render service), diff used vs. unused selectors, keep a safelist. This is the industry-correct approach.
- **Let the LLM do the human layer only:** (a) decide *when* RUCSS/Critical CSS is worth enabling for this site (it's part of the §2.1 diagnostic), (b) **explain a RUCSS regression** in plain words if something looks visually off ("your mega-menu styles were stripped — add `.mega-menu` to the safelist"), and (c) **suggest safelist entries** from class names rather than rewriting CSS. The LLM never emits CSS that goes live.

**Value:** Medium — but *correctly scoped* it removes a real support burden (RUCSS safelist tuning is WP Rocket's #1 support topic). **Mis-scoped (LLM writes CSS) it's a footgun.**

**Feasibility:** The algorithmic part is moderate–hard (real engineering, possibly a render service). The LLM "explain/safelist-suggest" wrapper is easy.

**Rough cost/run:** Only if used for explanation: class-name list + symptom → **<$0.001**. Sending full CSS to an LLM would be 50k–200k+ tokens (**$0.02–0.20+** and pointless) — **don't.**

**Privacy:** Class-name lists and selectors only (not visitor data). Low sensitivity.

**Failure/fallback:** Algorithmic path is the source of truth and ships with no key. LLM safelist suggestions are *additive and confirmed by user* — a bad suggestion just adds a harmless selector to the safelist.

---

### 2.4 Natural-language configuration ("speed it up but don't cache the members area")

Owner types an instruction; SwiftPress returns a **concrete settings diff to confirm**, never silent application.

**How it works technically:**
- This is the §2.1 pipeline run from a **free-text intent** instead of an audit. Prompt = user's sentence + current settings map + the **action whitelist** + constraints ("members area = exclude `/members/*`"). Force **structured JSON** `recommended_changes[]`. Same validation gate. Same confirm-diff UI. Same undo.
- Map fuzzy language → concrete keys: "speed it up" → enable safe defaults (`minify_css`, `defer JS`, `enable_cache_preload`, `font_display_swap`); "don't cache the members area" → add `/members/*` to `rejected_uri`. The LLM's job is **intent → whitelisted diff**; the deterministic gate guarantees only valid changes surface.

**Value:** Medium–high (delightful demo, strong for non-technical users, great marketing). Lower priority than §2.1 because it's the same engine with a chat box on top.

**Feasibility:** Easy *once §2.1 exists* (shared pipeline + gate + whitelist). Ship it as a follow-on.

**Rough cost/run:** Tiny — sentence + settings + whitelist ≈ 1–2k tokens → **<$0.0005**.

**Privacy:** The user's typed instruction + current settings. No content/visitor data. (Caveat: warn users not to paste secrets into the box.)

**Failure/fallback:** No key → disable the box, point to manual settings. Ambiguous/garbage input → LLM returns empty `recommended_changes` + a clarifying `summary`; nothing is applied. **Never auto-apply** — always confirm.

---

### 2.5 AI image alt-text generation + next-gen format guidance

**How it works technically:**
- **Alt-text (a genuinely good LLM/vision use case):** for images missing `alt`, send the **image (or a temporary public URL) + page title/filename as context** to a vision model (Gemini 2.5 Flash/Flash-Lite are multimodal and dirt-cheap; GPT-4o-mini, Claude Haiku also work via OpenRouter) → get a concise descriptive alt string → write to the attachment meta after user review (bulk + on-upload). This is exactly how shipping plugins (AltText AI, the GPT-Vision alt plugins) already work — **direct precedent, BYO-key, credit/token-metered.**
- **Next-gen format selection is NOT an LLM job** — WebP/AVIF conversion + "which format wins for this image" is decided **algorithmically** by encoding and comparing bytes/quality (what QUIC.cloud/LiteSpeed/ShortPixel do). SwiftPress has `enable_image_optimization`; keep the actual conversion algorithmic. The LLM could at most *explain* the recommendation in the diagnostic.

**Value:** Medium. Alt-text is real accessibility + SEO value and an easy upsell, but it's **tangential to "performance"** and the space is crowded. Treat as a **secondary module**, not the headline. (Format conversion belongs to the perf core, sans AI.)

**Feasibility:** Easy (vision call + media-library hooks). Crowded market → differentiate via "uses your existing OpenRouter key, no per-image SaaS fee."

**Rough cost/run:** Vision call per image ≈ small image tokens + short output → **~$0.0001–0.001 per image** on Flash-Lite. Bulk 1,000 images ≈ **$0.10–1.00**. Show a **cost estimate before bulk runs** and require confirmation.

**Privacy — important:** This is the **one feature where actual site content (the image pixels) leaves the site.** Must be explicit opt-in, clearly disclosed in readme + UI, and ideally let users exclude private/customer-uploaded images. Bigger GDPR surface than the metrics-only features.

**Failure/fallback:** No key → feature hidden. API error → leave alt empty, queue for retry, never write garbage. Hallucinated alt (wrong description) → **human-review-before-save** by default for bulk; on-upload mode shows the suggestion editable.

---

### Feature priority (recommendation)
1. **AI Performance Diagnostic (§2.1)** — headline, category-defining, lowest privacy risk, on-demand. **Build first.**
2. **AI Smart Cache Rules (§2.2)** — high value, risk-reducing, mostly deterministic. **Build second** (shares the gate).
3. **Natural-language config (§2.4)** — same engine + chat box. **Fast follow.**
4. **RUCSS/Critical CSS explainer (§2.3)** — algorithmic core + thin LLM explainer. **Ongoing.**
5. **Alt-text (§2.5)** — secondary module, separate opt-in (content leaves site). **Optional.**

---

## 3. Recommended overall AI architecture (BYO-key)

### 3.1 Key storage (encryption at rest)
- **Encrypt before storing.** Standard WP best practice: encrypt the key (e.g. `sodium_crypto_secretbox`/`libsodium`) using a secret derived from WordPress salts (`LOGGED_IN_SALT` / a dedicated constant in `wp-config.php`), and store the ciphertext in an `option`. The encryption secret lives in `wp-config.php` (filesystem, outside the DB) — **never** store the key and its decryption secret in the same place (the canonical WP guidance).
- **Best-tier option:** allow defining the key as a **PHP constant in `wp-config.php`** (e.g. `SWIFTPRESS_OPENROUTER_KEY`) — then it's never in the DB at all. Offer both (constant > encrypted option > none).
- **CRITICAL — never write the key into the cache drop-in.** `Config.php::save_configuration()` already **unsets `cloudflare_email/api_key/api_token/zone` before `save_to_file()`** (lines 630–634) precisely because `sp-config/*.php` is a predictable on-disk file. **The LLM API key MUST be added to that same exclusion list** so it never lands in `$GLOBALS['swiftpress_options']` on disk. This is the single most important code-level rule.

### 3.2 Where calls happen (server-side only)
- **All LLM/OpenRouter calls originate from PHP on the server** (admin-ajax / REST route, `manage_options` capability + nonce). **The key is NEVER sent to the browser** and never embedded in any enqueued JS. The browser calls *SwiftPress's* endpoint; SwiftPress calls OpenRouter. (This also satisfies the WP rule that external calls require explicit, authorized, admin-triggered consent.)
- Calls are **on demand** (button/cron-on-publish), **never on the front-end pageview path**. Front-end caching must remain zero-LLM.

### 3.3 Rate & cost controls
- **Monthly spend cap** (user-set, default e.g. $1–2/mo) tracked in an option; refuse calls past the cap with a clear message.
- **Per-action token ceilings** (max input/output) and **request debouncing** (one diagnostic per N minutes; disable button while running).
- **Model selector** defaulting to the cheapest viable model (Gemini 2.5 Flash-Lite: $0.10/$0.40 per M, 1M context, sub-250ms TTFT, ~400+ tok/s). Allow Flash for harder reasoning.
- **Estimate-before-bulk** for alt-text (show projected cost, require confirm).

### 3.4 Caching AI responses
- **Cache the diagnostic result** keyed by a hash of (audit metrics + settings snapshot). If nothing changed, show the cached explanation — **no new API call.** Invalidate on settings change or manual "re-run."
- **Cache alt-text** per attachment ID (never re-describe the same unchanged image).
- This turns "AI feature" into "AI-assisted, mostly cached" — keeping real-world spend near zero.

### 3.5 Graceful degradation (no key configured)
- Every AI feature has a **deterministic fallback**: the diagnostic falls back to a **static rules table** producing the same `recommended_changes[]` minus prose; smart-rules falls back to the deterministic detector; NL-config and alt-text are simply hidden. The plugin is **100% functional with zero AI** — AI is a pure upgrade layer, matching SwiftPress's "no-bloat" identity.

### 3.6 Structured output + validation (the safety spine)
- **Always request structured JSON** against a fixed schema (2025's primary hallucination control). Use OpenRouter's structured-output/JSON-mode where the model supports it.
- **Deterministic post-validation in PHP** against the real settings schema + an **action whitelist**. The LLM can only *propose* changes from a known vocabulary; SwiftPress code decides what is actually appliable. **Nothing the LLM says is trusted until validated.**
- **Confirm-before-apply** for any state change; **snapshot + one-click undo** always available.

---

## 4. Risks & mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **Cost surprise** (runaway/loop, bulk alt-text) | Medium | Medium | On-demand only (never per-pageview); response caching; monthly hard cap; per-action token ceilings; estimate-before-bulk; cheapest-model default (Flash-Lite). A diagnostic costs <$0.01; cache makes repeats free. |
| **Hallucinated fix breaks a live site** | Medium | **High** | LLM may only propose **whitelisted SwiftPress settings**, validated server-side before display; **confirm-before-apply**; **snapshot + one-click undo**; re-audit shows before/after. LLM never emits live CSS/JS/PHP. Worst case = a safe toggle, reverted. |
| **Privacy / GDPR (EU owner)** | Medium | High | **Data minimization by design**: metrics-only features send no PII/content. Alt-text (the one content-leaking feature) is explicit opt-in + disclosed. Execute a **DPA** with the provider (OpenAI DPA is opt-in; Gemini's DPA is auto-incorporated for EEA + SCCs; via OpenRouter, check the routed provider's terms). Note Google's 55-day abuse-monitoring retention (not used for training). Document everything in readme + a privacy notice; **disclose the external service** (WP guideline requirement). Offer EU-region/provider routing where possible. |
| **Dependence on a 3rd-party API** | High | Medium | BYO-key (no SwiftPress-run SaaS to fail/bill); **OpenRouter abstracts providers** so a model can be swapped without code changes; **every feature degrades to deterministic** behaviour if the API is down/absent. The plugin's core value never depends on the API being up. |
| **Plausible-but-wrong advice that passes validation** | Medium | Medium | Show confidence; keep changes reversible; re-audit loop exposes non-improvements; conservative defaults; human confirm. |
| **WP.org compliance** (phone-home / undisclosed external calls) | Medium | Medium–High | Admin-triggered, opt-in calls only; **document the external service + Terms + privacy in readme and the Settings page** (explicit WP guideline); no silent/automated data collection; key handling as §3.1. |
| **Key leakage** | Low | **High** | Encrypt at rest with a `wp-config.php` secret (or use a constant, key never in DB); **exclude the key from the `sp-config` drop-in** (extend the existing `Config.php` secret-strip list); server-side-only calls; never enqueue the key to JS; redact key from logs/exports (note: `import/export settings` must also strip it). |

---

## 5. Why this wins (positioning)

- **Category-of-one on the headline.** Competitors automate *and hide* the work (WP Rocket, NitroPack) or expose 200 toggles (Perfmatters). **None translate the audit into plain language and apply mapped fixes.** SwiftPress can own "the cache plugin that explains *why* your site is slow and fixes it in one click."
- **AI as an honest upgrade layer, not a dependency.** Deterministic fallbacks everywhere keep the "lean, no-bloat" identity intact for users without a key.
- **BYO-key + OpenRouter** sidesteps the SaaS billing/lock-in that defines WP Rocket and NitroPack, and the owner already runs OpenRouter — the build is mostly plumbing + a validation gate, not ML research.
- **Correct scoping** (LLM explains/triages; algorithms do CSS/images; deterministic gate guards apply) is what separates a credible product from an AI-washed footgun.

---

## Sources

- WP Rocket SaaS / RUCSS internals (Puppeteer + custom JS lib + manual safelist, no AI): https://wp-rocket.me/blog/saas-behind-the-scene/ , https://docs.wp-rocket.me/article/1529-remove-unused-css , https://wp-rocket.me/blog/wp-rocket-3-11/
- NitroPack Navigation AI (ML predictive prefetch, Google I/O 2024) + Fin AI support bot: https://nitropack.io/blog/wpengine-acquires-nitropack/ , https://www.allaboutai.com/ai-reviews/nitropack/ , https://therecursive.com/bulgarian-company-nitropack-acquired-by-us-wp-engine/
- ML predictive prefetch background: https://blog.tensorflow.org/2021/05/speed-up-your-sites-with-web-page-prefetching-using-ml.html
- Perfmatters (rule-based feature set): https://perfmatters.io/ , https://sahildadwal.com/perfmatters-review/
- LiteSpeed / QUIC.cloud image+CSS optimization (algorithmic; AVIF in v7): https://www.quic.cloud/docs/online-services/image-optimization/ , https://blog.litespeedtech.com/2025/03/26/avif-support-in-litespeed-plugin-v7/ , https://docs.litespeedtech.com/lscache/lscwp/imageopt/
- 10Web Booster (automation, Critical CSS, CDN): https://10web.io/page-speed-booster/ , https://help.10web.io/hc/en-us/articles/6428110822290-Intro-to-10Web-Booster
- Cloudflare Speed Brain (predictive prefetch, Speculation Rules, ML on roadmap): https://developers.cloudflare.com/speed/optimization/content/speed-brain/ , https://www.debugbear.com/blog/cloudflare-speed-brain
- PageSpeed Insights API / Lighthouse + 2025 "insight" consolidation: https://developers.google.com/speed/docs/insights/v5/get-started , https://developer.chrome.com/docs/lighthouse/overview/ , https://www.debugbear.com/blog/pagespeed-insights-api , https://empathyfirstmedia.com/google-lighthouse-performance-insight-audits/
- AI alt-text plugins (BYO-key, vision models, send image+context): https://wordpress.org/plugins/alttext-ai/ , https://wordpress.org/plugins/alt-text-generator-gpt-vision/
- Gemini 2.5 Flash / Flash-Lite pricing, context, latency (OpenRouter): https://openrouter.ai/google/gemini-2.5-flash , https://openrouter.ai/google/gemini-2.5-flash-lite , https://developers.googleblog.com/en/gemini-25-flash-lite-is-now-stable-and-generally-available/
- WordPress LLM agents / MCP (adjacent category, real LLM): https://wordpress.org/plugins/ai-engine/ , https://developer.wordpress.com/docs/mcp/ , https://thenextweb.com/news/wordpress-com-mcp-write-capabilities-ai-agent , https://elementor.com/products/angie-ai-for-wordpress/
- API-key encryption at rest in WordPress: https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/ , https://fullstackdigital.io/blog/how-to-safely-store-api-keys-and-access-protected-external-apis-in-wordpress/
- GDPR for LLM APIs (DPA/SCC, EU): https://www.datastudios.org/post/gemini-compliance-gdpr-hipaa-and-global-standards-in-2025 , https://www.januscompliance.co.uk/blog/can-i-use-chatgpt-api-and-stay-gdpr-compliant , https://bitecode.tech/en/blog/eu-gdpr-compliance-llm-provider/
- LLM hallucination guardrails / structured output (2025 best practice): https://www.datadoghq.com/blog/llm-guardrails-best-practices/ , https://www.parasoft.com/blog/controlling-llm-hallucinations-application-level-best-practices/
- WordPress plugin external-service / phone-home disclosure rules: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ , https://developer.wordpress.org/plugins/privacy/
- SwiftPress source (verified locally): `includes/utils.php` (settings defaults), `includes/classes/Config.php` (lines 630–634 secret-stripping precedent; `nginx_rules()`; `save_configuration()`), `includes/classes/Dependencies/Symfony/Component/CssSelector/*` (bundled CSS selector parser)
