# SwiftPress Revamp 2026 — Report

> **Status:** ✅ **v1 built & verified.** Branch `revamp-2026`. Distributable: `swiftpress-revamp.zip`.
> Method: multi-agent **judge panel** (research → proposals → adversarial debate → judge → implement → verify).

---

## 1. Competitive Research — what the best do, and what we take

Eight competitors were each researched by a dedicated agent (full dossiers in [`research/`](research/)). Headline finding: **nobody in the cache-plugin category ships a real LLM feature** — "AI" elsewhere is rule-based automation (WP Rocket, Perfmatters, LiteSpeed) or ML predictive-prefetch (NitroPack, Cloudflare). The lane for an **AI Performance Diagnostic that explains *and* fixes** is wide open.

| Plugin | Model & price (2026) | Standout features | Real LLM AI? | nginx | What we take |
|---|---|---|---|---|---|
| **WP Rocket** | Premium $59–299/yr, no free | Zero-config activation; Remove Unused CSS (SaaS); **Rocket Insights** (in-panel score + recommendation→action); per-page metabox; delay JS; auto LCP-image detect | ❌ (Puppeteer + rule tables; honestly makes no AI claims) | Works (PHP path); auto-.htaccess on Apache only | Zero-config "here's what we fixed" panel; **recommendation→action flow** (→ our AI Diagnostic); per-page metabox; in-browser LCP probe (no SaaS) |
| **FlyingPress** | Premium ~$60/yr | Real-user speed focus; self-hosted Critical CSS; **Lazy Render**; host fonts/3rd-party locally; Bunny CDN; **Vitals tab** (CrUX in-panel); smart above-fold; save-without-purge | ❌ | Good | Smart above-fold auto-detect; **Vitals feedback-loop tab**; save settings without full purge; live optimization queue |
| **Perfmatters** | Premium ~$25/yr | **Script Manager** (per-page script/style unloading — killer); disable WP bloat; local GA; delay JS; docs-as-UX; conservative all-off defaults | ❌ (explicitly honest: zero AI) | Good | **Script Manager** (directly fixes happytails' dead GTM/UA); plugin-grouped asset view; documentation-as-UX |
| **LiteSpeed Cache** | Free (best on LiteSpeed server) + QUIC.cloud SaaS | Huge free set; QUIC.cloud image opt (WebP/AVIF) + Critical/Unique CSS + LQIP; crawler; **preset tiers** (Essentials→Extreme) | ❌ | Full cache needs LiteSpeed server; QUIC.cloud SaaS server-agnostic | **Optimization preset tiers**; before/after score + refresh; tag-based partial invalidation idea for nginx |
| **NitroPack** | SaaS freemium→paid by pageviews | Fully-automated cloud opt; **Navigation AI** (ML prefetch); Fin AI (support bot); cart cache; per-page Critical CSS; dependency-aware invalidation | ⚠️ Partial (ML prefetch + LLM *support bot* — **not** for optimization or diagnostics) | SaaS proxy (server-agnostic) | Per-page Critical CSS; dependency-aware invalidation; mode presets w/ explicit tradeoffs; **WooCommerce cart cache**; per-URL insights. ⚠️ Avoid their Lighthouse-cloaking history |
| **W3 Total Cache** | Free + Pro | Granular power; strong **nginx + Redis/Memcached** object cache; **setup wizard w/ live TTFB benchmark**; preview mode; extensions framework; purge logs | ❌ | Strong (native nginx) | **Setup wizard w/ live benchmark** + backend detection (Redis/APCu); preview/test mode; integrations auto-detect panel; purge audit log. ⚠️ Their UI is the complexity anti-pattern to avoid |
| **Autoptimize** | Free + addons | CSS/JS aggregate+minify; critical CSS power-up; image opt via ShortPixel; **per-post LCP preload metabox**; cache-info panel | ❌ | Good | Per-page LCP preload field; **cache-info diagnostic panel** (writable/path/size YES-NO); honest "power-up" monetization pattern (model for our BYO-AI); save+empty-cache button |
| **WP Super Cache** | Free (Automattic) | Simple page cache; modes; preload; **"Easy" tab + Test Cache button**; Contents (cache inventory) tab | ❌ | Weak (no nginx config help) | One-click **"verify cache is working"** test; cache inventory tab; **in-plugin nginx config generator** (their gap = our standout — we already generate nginx rules) |

### Recurring consensus (what to build because *everyone good* has a version of it)
1. **Optimization presets** (Safe / Balanced / Aggressive / WooCommerce) with explicit tradeoff + per-setting risk labels — the #1 cure for toggle paralysis.
2. **In-panel Performance Score** with before/after + on-demand refresh.
3. **Recommendation → action** (metric → cause → exact toggle → one-click) — for us this becomes the AI Diagnostic.
4. **Zero-config activation** with a transparent "here's what we already fixed" panel.
5. **Polished nginx config generator** with copy-to-clipboard — a gap across the field and our natural standout (Sebastian runs nginx).
6. **Per-URL cache inventory** (status / age / size / score).
7. **Script Manager** (per-page script/style unloading) — Perfmatters' killer feature, and a direct fix for happytails-class problems.
8. **Per-page metabox**, **setup wizard with live benchmark**, **integrations auto-detect**, **cache health panel** — we already have most of the backend (`run_diagnostic()`, `includes/compat/`, `POST_META_*`).

---

## 2. AI Recommendation — does AI help, and how

**Yes — and it's the single biggest differentiator available.** Full brief in [`research/ai-landscape.md`](research/ai-landscape.md).

### Headline feature: AI Performance Diagnostic ("Explain & Fix")
Run a real audit (PageSpeed Insights API / Lighthouse) → feed the **numbers + current settings + environment** (nginx, PHP, WooCommerce?) to an LLM → get back **(a)** plain-language findings a non-technical owner understands and **(b)** a concrete settings diff mapped to **real SwiftPress setting keys**, applied with one click → **snapshot + undo**, then re-audit to prove the gain. **No competitor does this.** It reframes the plugin from "200 toggles I don't understand" to "tell me what's wrong and fix it."

The safety spine (this is what separates a product from an AI-washed footgun):
- **Action whitelist** — the LLM may only propose changes drawn from a known vocabulary of real setting keys + allowed values; it cannot invent a setting.
- **Deterministic PHP validation gate** — every proposed change is validated server-side (key exists? value in range? not mutually exclusive?) *before* the user ever sees it. The LLM proposes; SwiftPress code disposes.
- **Confirm-before-apply + one-click undo**, applied through the existing `save_configuration()` path (inherits secret-stripping, opcache invalidation, advanced-cache regeneration).
- **Structured JSON output** against a fixed schema — the primary 2025 hallucination control.

### Scoping rule (decisive)
- **AI does:** explanation, prioritization, triage, natural-language config, exclusion suggestions, alt-text.
- **Algorithms do:** Critical CSS, Remove Unused CSS, image format conversion. *Asking an LLM to write/prune CSS is slower, costlier, and unsafe* — WP Rocket's own engineering confirms theirs is Puppeteer + safelists, zero AI. The LLM only *explains* a RUCSS regression or *suggests* a safelist entry.

### Architecture (BYO-key)
- **BYO OpenRouter key**, default model **Gemini 2.5 Flash-Lite** (~$0.10/$0.40 per M tokens). A full diagnostic ≈ **<$0.01**, **on-demand only (never per-pageview)**, response-cached on `hash(metrics+settings)` so repeats are free. Monthly spend cap + per-action token ceiling + debounce.
- **Server-side only** — the key never reaches the browser, never embedded in enqueued JS.
- **Key security (must-do):** add the key to the secret-strip list in `Config.php:630-634` (so it never lands in the world-readable `sp-config/config-<host>.php`) **and** the export-strip list; support a `SWIFTPRESS_OPENROUTER_KEY` wp-config constant (key never in DB); encrypt-at-rest with `sodium_crypto_secretbox` keyed off a WP salt.
- **Graceful degradation:** every AI feature has a deterministic fallback (the diagnostic falls back to a static rules table). The plugin is **100% functional with zero AI** — AI is a pure upgrade layer, keeping the "no-bloat" identity.

### Feature priority
1. **AI Performance Diagnostic** — headline, category-defining, lowest privacy risk. **Build first.**
2. **AI Smart Cache Rules** (auto-detect cart/checkout/login/member exclusions) — mostly deterministic, risk-reducing. Build second (shares the gate).
3. **Natural-language config** ("speed it up but don't cache the members area") — same engine + a chat box. Fast follow.
4. **RUCSS/Critical CSS explainer** — algorithmic core + thin LLM explainer.
5. **AI alt-text** — secondary module, separate opt-in (the one feature where content leaves the site → GDPR-disclosed).

### Privacy
Metrics-only features send **no PII, no page HTML, no visitor data** — only derived metrics + boolean/string settings + environment facts. Data-minimized by design. Alt-text is the lone content-leaving feature → explicit opt-in + disclosure.

---

## 3. Code Audit Summary

Full audit: [`research/code-audit.md`](research/code-audit.md). Highlights:
- **`base64_decode` in `file-optimizer.php:178` is BENIGN** — the ngx_http_concat "compact URL" protocol (a gzip+base64 list of CSS/JS *file paths*), never executed, path-traversal-guarded, css/js MIME allow-list. Verified independently.
- **Architecture is sound:** real two-tier cache (htaccess/nginx fast path serving anonymous hits without PHP + a PHP drop-in fallback that works with zero server config), correct cache-key variants, thorough purge blast-radius, consistent nonce+capability auth, parameterized SQL, Mozart-isolated deps. **Zero dangerous sinks** (no eval/exec/etc.).
- **P0 bugs to fix:** (1) undefined settings keys `combine_google_fonts`/`use_bunny_fonts`/`swap_google_fonts_display` → PHP 8 warnings + dead code; (2) "Clear Font Cache" silently no-ops (wrong directory); (3) Critical CSS / Remove-Unused-CSS are dead UI stubs (no generator); (4) `sanitize_options()` reads ~30 keys without `isset()`; (5) markup bug in the Font section.
- **The current admin is intentionally generic stock-WP** (`.form-table`/checkboxes, no dashboard/metrics) — exactly the revamp target.
- **nginx is real** (`Config::nginx_rules()` generates a full `try_files` config) — keep + improve (single source of truth for the cache-key template; fix mobile/gzip drift; parameterize the hardcoded fpm socket).

## 4. UI Revamp — the "Editorial Console"

The judge panel (3 independent design visions → adversarial cross-critique → synthesis) converged on a hybrid named the **Editorial Console**, and the owner chose its two taste forks: **Warm Editorial** art direction + **AI Brief-first** home. Full design dossiers in [`proposals/`](proposals/), the debate in [`debate/`](debate/).

**Aesthetic direction:** Warm Editorial — warm graphite base (`#14110e`), a single sodium-amber accent (`#f5a524`, ~5% of the surface), and a confident editorial voice. Deliberately *not* stock-WordPress, not a white+purple SaaS gradient, not rounded-xl-on-everything.

**Signature move:** the **AI Brief speaks in an editor's voice**, and the Performance Score is a large **typographic Fraunces numeral** with a draw-on-load arc — the number *is* the hero, not a flat badge.

**Typography (self-hostable, characterful — no Inter/Roboto for display):** Fraunces (display + score numeral, with its SOFT/optical axes), IBM Plex Sans (body), JetBrains Mono (all data/metrics/setting-keys).

**Design system:** [`assets/css/admin/swiftpress-app.css`](assets/css/admin/swiftpress-app.css) — a scoped (`.swiftpress-app`) token-driven stylesheet (6 background layers, status colors, Lighthouse score banding, toggles, chip-inputs, code blocks, toasts, command palette) with a dark theme authored first and a light theme via `[data-sp-theme]`. Respects `prefers-reduced-motion`; one choreographed load reveal then stillness. A proven, interactive **mockup** of the home screen is in [`mockups/dashboard-warm-editorial.html`](mockups/dashboard-warm-editorial.html).

**Information architecture** (left rail + slim top bar, ≤ a handful of destinations), rendered by [`includes/admin/app.php`](includes/admin/app.php), controller [`assets/js/admin/swiftpress-app.js`](assets/js/admin/swiftpress-app.js):
- **The Brief** (home) — a Pulse strip (instant, deterministic: score/LCP/CLS/INP/TTFB/cache), the AI narrative + Score instrument, **Explain & Fix** cards wired to the AI engine, the **Ask SwiftPress** command palette (⌘K), and the preset bar.
- **Tune** — every setting, grouped and explained with modern toggles + chip-inputs (no "Advanced textarea graveyard"); **surfaces the previously-hidden settings** (CDN/Cloudflare, image optimizer + format, LCP, self-host GA/FB Pixel, Heartbeat, resource hints). Posts through the *existing* save pipeline (`process_form_submit`) — zero new write code.
- **Server** — the nginx/Apache config generator as a hero (line-numbered, copy-to-clipboard, download, reload hint) using the plugin's real `Config::nginx_rules()`.
- **Copilot** — the BYO OpenRouter key panel (encrypted-at-rest, constant-override hint), model selector, monthly budget cap, and the data/privacy disclosure.

> **Stack note (transparent):** v1 ships the **lean** version — design-system CSS + a small vanilla controller (≈ the proven mockup), enqueued raw like the existing admin assets. This stays true to a performance plugin's brand and ships the headline fast. The panel chose "AI Brief-first → Preact"; upgrading the Brief to a **streaming Preact island** (richer token-by-token narrative) is a documented, low-risk fast-follow — the AI engine is UI-agnostic, so the same endpoints drop straight in.

## 5. New & Optimized Features

**AI Performance Diagnostic ("Explain & Fix") — the headline, category-defining feature.** New module [`includes/classes/AI/`](includes/classes/AI/) (10 classes): `AI` (admin-only AJAX registrar + single capability-before-nonce gate), `KeyStore` (sodium-encrypted BYO key, constant override, never to browser/config/export/logs), `PageSpeed` (PSI v5 → PII-free metrics), `Client` (OpenRouter, structured JSON output), `Schema` (the ~27-key action whitelist + JSON schema + prompt), `ValidationGate` (deterministic post-LLM filter — drops invalid, re-derives `from`, resolves conflicts), `Diagnostic` (pipeline + response cache), `Snapshot` (undo ring buffer), `Budget` (monthly cap + debounce + ledger), `RulesFallback` (deterministic floor so it works with **no key**). Apply reuses the existing `sanitize_options → update_option → save_configuration → swiftpress_settings_saved` path verbatim, so every AI change is snapshotted and reversible. **No competitor combines a real LLM with diagnostics.**
- **Optimization presets** (Safe / Balanced / Aggressive / WooCommerce) — explicit maps of real keys with a confirm step, applied through the real save path.
- **nginx config generator surfaced** as a first-class screen (was a buried download).
- **~10 previously-orphaned settings surfaced** in Tune (CDN/Cloudflare, image optimizer + preferred format, LCP optimization, self-host Google Analytics / Facebook Pixel, Heartbeat control, DNS-prefetch, link prefetch, emoji/embeds bloat control) — the backend already supported them; the old UI hid them.
- **Honest stub state:** Critical CSS / Remove-Unused-CSS render as "engine pending" instead of dead checkboxes (the generators are algorithmic and scoped as a fast-follow — correctly *not* an LLM job).

**Deferred to fast-follow (documented, scoped):** streaming Preact Brief, per-URL cache Inspect table, Script Manager (per-page unloading), per-page metabox, natural-language config (`ajax_nl_config`), AI alt-text (the one content-leaving feature — separate opt-in), and the algorithmic Critical-CSS/RUCSS generators.

## 6. Code Fixes & Hardening Applied

**P0 correctness (from the audit):**
- Defined the three undefined font keys (`combine_google_fonts`, `use_bunny_fonts`, `swap_google_fonts_display`) in defaults + sanitizer → silences PHP 8 "Undefined array key" warnings on every optimized request (`utils.php`, `dashboard.php`).
- `sanitize_options()` made null-safe (surgical `?? ''`/`?? 0`, *not* a defaults-merge — preserving the unchecked-checkbox semantics) so import/partial input never warns or passes `null` to string functions.
- Import flow hardened: `is_uploaded_file()` + `UPLOAD_ERR_OK` + size cap + `is_array(json_decode())` validation, and AI keys unset post-sanitize so imported JSON can't seed a key.
- Closed the unclosed `<div>` in the classic settings template (DOM now balances 26/26).
- (Font-cache-clear directory mismatch from the audit was already corrected in the working tree — verified, no change needed.)

**AI security hardening (the H1–H10 checklist from the adversarial review — all applied):** canonical `KeyStore::SECRET_KEYS` single source of truth; **no hardcoded encryption fallback** (refuses to store rather than use public key material); PSI key covered by the same strip set; append-only `swiftpress_secret_strip_keys` filter with a hardcoded baseline (a buggy/hostile filter can only *extend*, never un-strip); `guard()` reads POST or GET nonce; `max_length` on string-type whitelist keys; salt-rotation decryption-failure admin notice; key redaction before any log. The OpenRouter key is added to **both** secret-strip lists (`Config.php` drop-in + `dashboard.php` export) so it can never land in the world-readable `sp-config/*.php` or a settings export.

**Verification (performed locally in a clean WordPress 6.7 / PHP 8.3 Docker container — production untouched):**
- Every changed/new PHP file passes a real `php -l` (PHP 8.3); the JS passes `node --check`; `npm run build` is green.
- The plugin **installs and activates cleanly — zero fatals, an empty `debug.log`** (the P0 fixes removed the PHP 8 warning surface).
- The AI module **classes autoload** (`SwiftPress\AI\AI`, `KeyStore`, `Diagnostic`, `ValidationGate`) and **encryption is available** (sodium + WP salts).
- The **full AI diagnostic pipeline runs end-to-end** (`Diagnostic::run()`): with no key and PSI unable to reach a local URL it degrades exactly as designed — `psi_failed → RulesFallback → ValidationGate → normalized payload` (`{summary, findings, recommended_changes:{appliable,suggested}, metrics, meta}`) — confirming the JS↔backend contract and the graceful, zero-AI floor. The `ValidationGate` correctly dropped an invented setting key and kept the valid ones.
- The **Editorial Console renders** (`render()` produced ~7 KB of valid panel markup: shell, Brief, Score instrument, Pulse, presets).
- *(A live in-browser screenshot of the WP admin was blocked by a host-level `localhost` HTTP quirk unrelated to the plugin — `wp-cli` talks to the container directly, so the checks above are authoritative. The exact visual is the verified mockup in `mockups/`.)*

## 7. Build, Install & Test Instructions

**Build from source**
```bash
npm install
npm run build         # compiles editor bundles; the admin app CSS/JS ship raw from assets/
```
The distributable is already packaged at **`swiftpress-revamp.zip`** (≈0.66 MB) with a clean `swiftpress/` plugin root (no dev/research artifacts, no node_modules; bundled deps under `includes/classes/Dependencies/`).

**Install on WordPress**
1. WP Admin → Plugins → Add New → Upload Plugin → choose `swiftpress-revamp.zip` → Install → Activate.
2. Open **SwiftPress** in the admin menu → you land on **The Brief**.
3. (Optional, unlocks the AI explanations) **Copilot** → paste an OpenRouter key, or define `SWIFTPRESS_OPENROUTER_KEY` in `wp-config.php` (strongest — never touches the DB). Without a key the diagnostic still works via the deterministic rules fallback.
4. **Run Diagnostic** → review the plain-language findings → **Apply** the safe ones (each reversible) → optionally re-run to see the delta.
5. On nginx, open **Server**, copy the generated config into your server block, then `sudo nginx -t && sudo systemctl reload nginx`.

## 8. Before/After on happytailsdetective.co.uk

**Recommended live test (proposed, not auto-run — production safety).** The plugin already has the switches that map to happytails' Lighthouse problems: `js_delay` (dead UA/GTM JS), `enable_font_optimization` + `font_display_swap` (render-blocking Google Font), `add_missing_image_dimensions` (CLS 0.164), `minify_css`/`minify_js`. The AI Diagnostic surfaces exactly these and applies them in one click. Measure Lighthouse mobile before, apply, re-measure — the success metric is moving the **62 → goal 85+** and **LCP 8.0s → < 2.5s**. See §7 step 4. Server (51.83.192.23) has php8.3 + redis-cache; the new plugin coexists with redis-cache (object cache) — no drop-in conflict.
