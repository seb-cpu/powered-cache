# SwiftPress — AI Module Architecture (implementation-ready)

**Author:** AI systems architect (build pass)
**Date:** 2026-06-08
**Scope:** The *engine* behind the headline **AI Performance Diagnostic ("Explain & Fix")** and the follow-on **natural-language configuration** — not a UI vision. This specifies the PHP module, its classes, endpoints, key storage, the validation gate, the LLM/PSI wiring, caching, cost controls, undo, and a thin build-first slice.

**Grounding (all verified on disk):**
- Strategy: `research/ai-landscape.md` (headline = AI Performance Diagnostic; CSS stays algorithmic; BYO OpenRouter key; §3 architecture).
- Real hooks/seams: `research/code-audit.md` §6.
- `run_diagnostic()` AJAX endpoint — `includes/admin/dashboard.php:742-829` (nonce `swiftpress_run_diagnostic`, `wp_send_json_success($checks)`).
- The single settings write path — `process_form_submit()` / `sanitize_options()` — `includes/admin/dashboard.php:123-274` / `283-399`; switch on `$_POST['swiftpress_form_action']` (line 135); writes via `update_option(SETTING_OPTION, …)` (206) then `Config::factory()->save_configuration(…)` (209); fires `do_action('swiftpress_settings_saved', $old, $new)` (256); filter seam `swiftpress_sanitized_options` (398).
- Secret-strip precedent #1 (drop-in): `Config::save_configuration()` unsets `cloudflare_*` before `save_to_file()` — `includes/classes/Config.php:630-634`.
- Secret-strip precedent #2 (export): `process_form_submit()` blanks `cloudflare_*` in the `export_settings` branch — `dashboard.php:158-168`.
- Real setting keys + types: `Utils\get_settings()` defaults — `includes/utils.php:48-140`; value coercions in `sanitize_options()` — `dashboard.php:286-376`.
- Admin enqueue/localize pattern: `core.php:153-200` (`wp_localize_script('swiftpressSettings', …)`, nonce `swiftpress_settings_ajax`).
- Constants namespace: `includes/constants.php` (`SETTING_OPTION`, `MENU_SLUG`, `POST_META_*`).

> **Design stance.** The LLM is the *communication and prioritization layer*. It never writes live CSS/JS/PHP and never touches the option store directly. It only emits a structured proposal of **whitelisted setting keys**; deterministic PHP decides what is appliable; the existing save path applies it; a snapshot makes it reversible. "The LLM proposes; SwiftPress disposes."

---

## 0. TL;DR for the implementer

1. New module `SwiftPress\AI` under `includes/classes/AI/` — singleton `AI::factory()` registering admin-only AJAX (admin-ajax first; REST optional later). Boot it from `swiftpress.php` after `Admin\Dashboard\setup()`.
2. Six classes: `AI` (orchestrator/registrar), `KeyStore` (constant > encrypted option > none), `PageSpeed` (PSI v5 → ground-truth metrics object), `Client` (OpenRouter `wp_remote_post`, structured output), `Schema` (the action whitelist + JSON schema the LLM fills), `ValidationGate` (deterministic post-LLM filter), `Diagnostic` (pipeline glue: metrics → prompt → LLM → gate → cache), `Snapshot` (undo), `Budget` (cost/debounce/cache). (Six "primary" + Schema/Snapshot/Budget as collaborators.)
3. Key never reaches the browser, never lands in `sp-config/*.php`, never appears in export. Add `swiftpress_openrouter_key`-equivalent to **both** strip lists (Config.php:630 and dashboard.php:158). Key is stored in its **own option** (`swiftpress_ai_secrets`), encrypted with `sodium_crypto_secretbox` keyed off a wp-config salt — *not* in `swiftpress_settings`, so it can never ride the config/export path at all.
4. Apply reuses the real save path: validated diff → `sanitize_options()` → `update_option()` → `Config::save_configuration()` → `do_action('swiftpress_settings_saved')`. No new write path, no new safety surface.
5. Build-first vertical (≈3.5–5 dev-days): key field → diagnostic run (PSI + LLM) → findings list → one validated apply → undo. Everything else (NL-config, smart-cache-rules, alt-text) is a fast-follow on the same engine.

---

## 1. Module shape

### 1.1 Directory + class layout

```
includes/classes/AI/
  AI.php             SwiftPress\AI\AI            — singleton, hook/endpoint registrar, capability+nonce gate, dispatch
  KeyStore.php       SwiftPress\AI\KeyStore      — get/set/has/clear key; constant override; encrypt-at-rest
  PageSpeed.php      SwiftPress\AI\PageSpeed     — PSI v5 fetch → normalized Metrics object (ground truth)
  Client.php         SwiftPress\AI\Client        — OpenRouter chat/completions via wp_remote_post; structured output
  Schema.php         SwiftPress\AI\Schema        — action whitelist (key→type/range/conflicts) + JSON response schema + system prompt builder
  ValidationGate.php SwiftPress\AI\ValidationGate— deterministic PHP validation of LLM output → appliable diff
  Diagnostic.php     SwiftPress\AI\Diagnostic    — pipeline orchestration + response cache
  Snapshot.php       SwiftPress\AI\Snapshot      — settings snapshot store + restore (undo)
  Budget.php         SwiftPress\AI\Budget        — monthly spend cap, token ceilings, debounce, usage ledger
  RulesFallback.php  SwiftPress\AI\RulesFallback — static rules table producing recommended_changes[] with NO key
```

Follows the codebase's dominant pattern exactly (audit §1.1): every subsystem is a static-`factory()` singleton calling `setup()` to register hooks; no DI container; coupling via WP hooks + `Utils\get_settings()`. `AI` is the only class that registers hooks; the rest are plain collaborators it instantiates.

### 1.2 Boot

In `swiftpress.php` boot sequence (currently `…→ Admin\Dashboard\setup() → …`, audit §1.1), add after the dashboard:

```php
\SwiftPress\AI\AI::factory();
```

`AI::factory()->setup()` registers nothing on the front end (front-end cache path stays 100% zero-LLM — audit §1.3, landscape §3.2). It guards the whole registration behind `is_admin()` and `wp_doing_ajax()`.

### 1.3 Endpoints (each: nonce + `manage_options`, network-aware)

Registered in `AI::setup()`, mirroring `dashboard.php:55-59` and the localize nonce convention in `core.php:181-198`. **admin-ajax for v1** (it is where the existing diagnostic and clear-cache endpoints already live — minimum surface, reuses the page nonce). A REST namespace `swiftpress/v1/ai/*` is specified as an optional v1.1 (same handlers, `permission_callback` enforcing the capability) for cleaner JS and future external automation.

| AJAX action (`wp_ajax_*`) | Handler | Purpose | Gate |
|---|---|---|---|
| `swiftpress_ai_run_diagnostic` | `AI::ajax_run_diagnostic` | Run PSI + LLM (or rules fallback) → return `{summary, findings[], recommended_changes[], meta}` | nonce `swiftpress_ai` + `manage_options` (+`manage_network`) |
| `swiftpress_ai_apply` | `AI::ajax_apply` | Apply a user-confirmed subset of `recommended_changes[]` through the real save path; snapshot first | same |
| `swiftpress_ai_undo` | `AI::ajax_undo` | Restore the last pre-apply snapshot | same |
| `swiftpress_ai_save_key` | `AI::ajax_save_key` | Encrypt + store / clear the OpenRouter key; validate format; never echo it back | same |
| `swiftpress_ai_status` | `AI::ajax_status` | Lightweight: key present? (bool, never the value) source (constant/option/none), month-to-date spend, cap, last snapshot exists?, debounce remaining | same |
| `swiftpress_ai_nl_config` *(v1.1)* | `AI::ajax_nl_config` | Natural-language intent → same pipeline from free text instead of metrics | same |

**Single gate helper** used by every handler (normalizes the §3.4-audit nonce hygiene issue from the start):

```php
private function guard( string $nonce_action = 'swiftpress_ai' ): void {
    $cap = \SWIFTPRESS_IS_NETWORK ? 'manage_network' : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'swiftpress' ) ], 403 );
    }
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid request.', 'swiftpress' ) ], 400 );
    }
}
```

The AI JS bundle gets its own localized object so the AI nonce is independent of the settings-page nonce:

```php
// enqueued on MENU_SLUG page only, mirroring core.php:181
wp_localize_script( 'swiftpress-ai', 'swiftpressAI', [
    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
    'nonce'    => wp_create_nonce( 'swiftpress_ai' ),
    'restBase' => esc_url_raw( rest_url( 'swiftpress/v1/ai' ) ), // v1.1
    'hasKey'   => KeyStore::factory()->has_key(),                 // bool only
    'keySource'=> KeyStore::factory()->source(),                  // 'constant'|'option'|'none'
] );
```

### 1.4 Filter/action seams it exposes (so AI stays decoupled & licensable — audit §6 recommendation)

- `apply_filters( 'swiftpress_ai_recommendations', $changes, $metrics, $settings )` — final hook on the appliable diff (lets compat shims add/veto).
- `apply_filters( 'swiftpress_ai_model', 'google/gemini-2.5-flash-lite', $purpose )` — model override per call.
- `apply_filters( 'swiftpress_ai_action_whitelist', $whitelist )` — extend/restrict the allowable keys (e.g. Pro gating).
- `apply_filters( 'swiftpress_ai_system_prompt', $prompt, $context )`.
- `do_action( 'swiftpress_ai_applied', $applied_changes, $snapshot_id )` and `swiftpress_ai_undone`.
- `apply_filters( 'swiftpress_ai_psi_urls', [ home_url('/') ] )` — which URLs to audit.

---

## 2. Key storage (encryption at rest + never-leak)

### 2.1 Resolution order (best → none)

`KeyStore::get_key()` resolves in this order and returns the *plaintext* key only inside server PHP, never to a response:

1. **Constant** `SWIFTPRESS_OPENROUTER_KEY` defined in `wp-config.php` → return it. **Never in DB.** This is the recommended tier (landscape §3.1). `source() === 'constant'`; the UI shows "key set via wp-config" and disables the field.
2. **Encrypted option** `swiftpress_ai_secrets['openrouter_key_cipher']` → decrypt with `decrypt()` below. `source() === 'option'`.
3. None → `source() === 'none'`; every AI call degrades to `RulesFallback` (§6.4).

```php
public function get_key(): ?string {
    if ( defined( 'SWIFTPRESS_OPENROUTER_KEY' ) && SWIFTPRESS_OPENROUTER_KEY ) {
        return (string) SWIFTPRESS_OPENROUTER_KEY;
    }
    $store  = $this->is_network() ? get_site_option( self::OPTION, [] ) : get_option( self::OPTION, [] );
    $cipher = $store['openrouter_key_cipher'] ?? '';
    return $cipher ? $this->decrypt( $cipher ) : null;
}
```

### 2.2 Dedicated option — NOT in `swiftpress_settings`

The key lives in its **own** option `swiftpress_ai_secrets` (constant `AI\KeyStore::OPTION`), **never** in `SETTING_OPTION`. This is the single cleanest defense: because the key is not a member of `$settings`, it is *structurally impossible* for it to be flattened into `sp-config/config-<host>.php` by `Config::save_to_file()` (audit §1.2) or serialized by the `export_settings` branch (dashboard.php:150-181) — both of those operate on `$settings`/`SETTING_OPTION` only. Defense #2 and #3 below are belt-and-suspenders for any future code that might merge maps.

### 2.3 Encryption at rest (`sodium_crypto_secretbox`)

Authenticated symmetric encryption, keyed off a wp-config salt so the **decryption secret lives on the filesystem, never in the DB** (the canonical WP guidance, landscape §3.1).

```php
private function enc_key(): string {
    // Prefer a dedicated constant; fall back to a WP salt. 32 bytes via SHA-256.
    $material = defined( 'SWIFTPRESS_ENCRYPTION_KEY' ) ? SWIFTPRESS_ENCRYPTION_KEY
              : ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : ( defined('AUTH_SALT') ? AUTH_SALT : 'swiftpress-fallback' ) );
    return hash( 'sha256', 'swiftpress|ai|' . $material, true ); // 32 raw bytes
}

public function encrypt( string $plain ): string {
    $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );        // 24 bytes
    $cipher = sodium_crypto_secretbox( $plain, $nonce, $this->enc_key() );
    return base64_encode( $nonce . $cipher );                            // store nonce||cipher
}

public function decrypt( string $stored ): ?string {
    $raw = base64_decode( $stored, true );
    if ( $raw === false || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) return null;
    $nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
    $cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
    $plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->enc_key() );
    return $plain === false ? null : $plain; // false → salt changed/tamper → treat as "no key"
}
```

**Availability:** `sodium_*` is core PHP since 7.2 (target is 8.x — audit §4) and WP bundles the `sodium_compat` polyfill, so it is always present. Guard with `function_exists('sodium_crypto_secretbox')`; if (impossibly) absent, refuse to store an encrypted key and tell the user to use the `SWIFTPRESS_OPENROUTER_KEY` constant instead — never fall back to plaintext.

**Salt-rotation behavior:** if the site's salts are rotated, `decrypt()` returns `null` → `source()` reports `option` but `get_key()` yields null → graceful fallback to rules + a one-line admin notice "re-enter your OpenRouter key". No crash, no leak.

### 2.4 Validation + redaction at the boundary

- **Format check on save** (`ajax_save_key`): trim; accept `sk-or-…` (OpenRouter) shape via a loose regex `^sk-[A-Za-z0-9_\-]{20,}$`; reject otherwise with a clear message. Optionally a live 1-token ping to `GET https://openrouter.ai/api/v1/key` to confirm validity (behind a "test key" button, debounced).
- **Never echo the key.** `ajax_status` / `ajax_save_key` return only `has_key` (bool) and `source`. The settings field renders as an empty password input with a "key is set" pill when present; submitting empty = "keep existing", submitting a sentinel `__CLEAR__` = delete.
- **Log redaction:** the codebase has `Utils\log()` behind `SWIFTPRESS_ENABLE_LOG` (audit §2). `Client` must never log headers/body containing the key; a `redact()` helper masks `Authorization` and any `sk-…` substring before any `Utils\log()` call.

### 2.5 The two strip-list edits (CRITICAL — even though §2.2 already isolates the key)

Belt-and-suspenders, exactly as `research/ai-landscape.md` §3.1 demands, so a future refactor that *does* merge the key into `$settings` cannot leak it:

1. **Drop-in strip — `Config::save_configuration()` (`Config.php:630`).** Extend the private list:
   ```php
   $private_settings = [ 'cloudflare_email', 'cloudflare_api_key', 'cloudflare_api_token', 'cloudflare_zone',
                         'openrouter_key', 'openrouter_key_cipher', 'ai_openrouter_key' ];
   ```
2. **Export strip — `process_form_submit()` `export_settings` branch (`dashboard.php:158-168`).** Extend `$sensitive_options` with the same three keys, so even a hand-merged export blanks them.
3. **Import guard — `import_settings` branch (`dashboard.php:182-188`).** After `sanitize_options()`, explicitly `unset()` those three keys from the imported array so an attacker-supplied JSON can't seed a key into settings. (Also fixes the §3.2 robustness gap by validating `is_array(json_decode)` first.)

Add a `swiftpress_secret_strip_keys` filter around the drop-in list so the AI module can register its keys without the core needing to know about AI — cleaner than hardcoding cross-module knowledge into Config.php.

---

## 3. The validation gate (deterministic, post-LLM)

`ValidationGate::filter( array $llm_changes, array $current_settings ): array` is the safety spine (landscape §3.6). **Nothing the LLM emits is trusted until it survives this.** It runs entirely in PHP with zero model involvement.

### 3.1 The action whitelist (`Schema::action_whitelist()`)

A map of **only** real, AI-tunable setting keys → {type, allowed values/range, conflicts, risk, requires}. Sourced 1:1 from `Utils\get_settings()` (utils.php:48-140) and the coercions in `sanitize_options()` (dashboard.php:286-376). Booleans/scalars only — **no free-text keys** (rejected_uri/safelists are NOT auto-written in v1; they surface as *suggestions to copy*, never auto-applied, because text exclusions are the high-blast-radius footgun — landscape §2.2 over-exclusion caveat).

| setting_key | type | allowed | conflicts / requires | risk |
|---|---|---|---|---|
| `enable_page_cache` | bool | true/false | — | low |
| `gzip_compression` | bool | true/false | — | low |
| `cache_mobile` | bool | true/false | — | low |
| `cache_timeout` | int (min) | 60–43200 | — | low |
| `minify_css` | bool | true/false | — | low |
| `combine_css` | bool | true/false | warn if `remove_unused_css` on (RUCSS supersedes combine) | medium |
| `critical_css` | bool | true/false | requires generator present (else suggest-only) | medium |
| `remove_unused_css` | bool | true/false | mutually-soft-exclusive with `combine_css`; requires generator | medium |
| `minify_js` | bool | true/false | — | low |
| `combine_js` | bool | true/false | warn with `js_delay` | medium |
| `js_defer` | bool | true/false | — | medium |
| `js_delay` | bool | true/false | — | high |
| `js_delay_timeout` | int | 0–10000 (ms) | requires `js_delay` true | low |
| `enable_font_optimization` | bool | true/false | — | low |
| `self_host_google_fonts` | bool | true/false | requires `enable_font_optimization` | low |
| `font_preload` | bool | true/false | requires `enable_font_optimization` | low |
| `font_display_swap` | bool | true/false | requires `enable_font_optimization` | low |
| `enable_cache_preload` | bool | true/false | — | low |
| `enable_sitemap_preload` | bool | true/false | requires `enable_cache_preload` | low |
| `prefetch_links` | bool | true/false | — | low |
| `enable_lcp_optimization` | bool | true/false | — | medium |
| `enable_image_optimization` | bool | true/false | — | low |
| `image_optimizer_preferred_format` | enum | `''`,`webp`,`avif` | requires `enable_image_optimization` | low |
| `add_missing_image_dimensions` | bool | true/false | — | low |
| `disable_emoji_scripts` | bool | true/false | — | low |
| `disable_wp_embeds` | bool | true/false | — | low |
| `minify_html` | bool | true/false | — | low |

(~27 keys. The `cloudflare_*`, `varnish_ip`, `heartbeat_*`, tracking, and all free-text/exclusion keys are **excluded** from the auto-apply whitelist — they need human judgment or secrets; the LLM may *mention* them in findings prose but cannot propose them as appliable changes.)

### 3.2 The gate algorithm (drop-anything-invalid)

For each proposed `{setting_key, from, to, …}`:

1. **Key exists in whitelist?** No → **drop** (the LLM invented a setting; landscape §2.1 step 3).
2. **`to` is the right type and in range/enum?** No → **drop**. (Booleans coerced via the same `! empty()` semantics as `sanitize_options`; ints clamped/validated against range; enums checked against the allowed set.)
3. **`from` matches the *actual* current value?** If the LLM's `from` disagrees with `$current_settings[$key]`, **rewrite `from` to the real value** (never trust the model's claim of current state) and keep going. If `to === actual_current`, **drop** (no-op).
4. **`requires` satisfied?** If a change requires another setting (e.g. `self_host_google_fonts` requires `enable_font_optimization`) and that prerequisite is neither already on nor being turned on in this same batch, either **auto-add the prerequisite** (if it's whitelisted low-risk) or **drop** the dependent change. Decision recorded in the item's `gate_notes`.
5. **Conflicts / mutual exclusion?** Resolve deterministically:
   - If both `combine_css→true` and `remove_unused_css→true` are proposed, **keep RUCSS, drop combine_css** (RUCSS is the stronger optimization; mirrors landscape §2.1 step 4 example) and annotate.
   - Never allow a change that would disable `enable_page_cache` *and* enable a cache-dependent feature in the same batch.
6. **Capability/secret features:** any whitelisted key whose effect needs a secret or generator that isn't present (e.g. `remove_unused_css` when no UCSS generator exists — audit §5 notes these are currently stubs) is **downgraded from "apply" to "suggest"**: it appears in the UI as advice but its Apply checkbox is disabled with a reason ("requires the Critical-CSS engine — not yet available").
7. **Idempotency cap:** at most one change per `setting_key` (dedupe, last wins after sorting by confidence desc).

Output: `appliable[]` (passed, with corrected `from`), `suggested[]` (valid but not auto-appliable), `dropped[]` (with reason — surfaced only in a debug view, never shown as appliable). The UI's Apply button operates **only** on `appliable[]` items the user has checked.

### 3.3 Why this collapses the hallucination surface

The LLM is reduced from "free narrative + arbitrary actions" to "fill fixed fields from a closed vocabulary," and then PHP independently re-derives `from`, re-checks `to`, and enforces every conflict — so the *worst* a hallucination can do is propose a real, in-range, reversible toggle that the gate may still drop. Combined with snapshot+undo (§7) and re-audit, the residual risk is "a safe setting toggled and reverted" (landscape §2.1 failure design).

---

## 4. The structured-output JSON schema (what the LLM must fill)

Requested as strict JSON via OpenRouter's `response_format` (§5.3). The schema is intentionally flat and closed.

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["summary", "findings", "recommended_changes"],
  "properties": {
    "summary": {
      "type": "string",
      "description": "2-4 plain-language sentences for a non-technical site owner: what's slow and why."
    },
    "overall_assessment": {
      "type": "string",
      "enum": ["good", "needs_work", "poor"]
    },
    "findings": {
      "type": "array",
      "maxItems": 8,
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["issue", "evidence_metric", "severity", "plain_explanation"],
        "properties": {
          "issue": { "type": "string" },
          "evidence_metric": {
            "type": "string",
            "description": "Name+value of the metric proving it, e.g. 'LCP 4.1s', 'unused CSS 180KB', 'render-blocking: 6 resources'."
          },
          "severity": { "type": "string", "enum": ["critical", "high", "medium", "low"] },
          "plain_explanation": { "type": "string" }
        }
      }
    },
    "recommended_changes": {
      "type": "array",
      "maxItems": 12,
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["setting_key", "to", "why", "risk", "confidence"],
        "properties": {
          "setting_key": {
            "type": "string",
            "enum": ["enable_page_cache","gzip_compression","cache_mobile","cache_timeout",
                     "minify_css","combine_css","critical_css","remove_unused_css",
                     "minify_js","combine_js","js_defer","js_delay","js_delay_timeout",
                     "enable_font_optimization","self_host_google_fonts","font_preload","font_display_swap",
                     "enable_cache_preload","enable_sitemap_preload","prefetch_links","enable_lcp_optimization",
                     "enable_image_optimization","image_optimizer_preferred_format","add_missing_image_dimensions",
                     "disable_emoji_scripts","disable_wp_embeds","minify_html"]
          },
          "from": { "description": "model's guess of current value; IGNORED — PHP overrides from real settings",
                    "type": ["boolean","integer","string","null"] },
          "to":   { "type": ["boolean","integer","string"] },
          "why":  { "type": "string", "description": "one sentence tying this change to a finding's metric" },
          "risk": { "type": "string", "enum": ["low","medium","high"] },
          "confidence": { "type": "number", "minimum": 0, "maximum": 1 }
        }
      }
    }
  }
}
```

The `setting_key` **enum is the action whitelist inlined into the schema** (landscape §2.1 step 3) — the structured-output constraint stops most invalid keys at generation time; the gate (§3) is the deterministic backstop for models that ignore the enum or for stale enums. `from` is explicitly documented as ignored so the model isn't trusted for current state.

---

## 5. OpenRouter + PageSpeed Insights wiring (from PHP)

### 5.1 PageSpeed Insights v5 → the ground-truth Metrics object (`PageSpeed`)

`PageSpeed::audit( string $url, string $strategy = 'mobile' ): array|WP_Error`

- Endpoint: `https://www.googleapis.com/pagespeedonline/v5/runPagespeed`
- Query: `url`, `strategy=mobile` (default; also run `desktop` if budget allows), `category=performance`, optional `key=` (a **PSI** API key — separate from the LLM key; key-optional but recommended for rate limits; stored in `swiftpress_ai_secrets['psi_key']`, same encryption).
- `wp_remote_get( $endpoint, [ 'timeout' => 25, 'sslverify' => true ] )` (note: **verified TLS** — do not repeat the `sslverify=false` smell flagged in audit §3.8).
- Parse `lighthouseResult.audits` + `loadingExperience` (CrUX field data) into a compact, PII-free object — **parse JSON, never scrape UI**, and read the 2025 "insights" groups where present (landscape §2.1):

```php
$metrics = [
  'url'        => $url,
  'strategy'   => 'mobile',
  'perf_score' => (int) round( $lr['categories']['performance']['score'] * 100 ),
  'field'      => [ // CrUX, may be absent for low-traffic sites
     'LCP_ms' => $crux['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null,
     'INP_ms' => $crux['INTERACTION_TO_NEXT_PAINT']['percentile']  ?? null,
     'CLS'    => $crux['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ?? null,
  ],
  'lab'        => [
     'LCP_ms' => $audits['largest-contentful-paint']['numericValue'] ?? null,
     'TBT_ms' => $audits['total-blocking-time']['numericValue']      ?? null,
     'CLS'    => $audits['cumulative-layout-shift']['numericValue']  ?? null,
     'TTFB_ms'=> $audits['server-response-time']['numericValue']     ?? null,
     'SI_ms'  => $audits['speed-index']['numericValue']              ?? null,
  ],
  'opportunities' => [ // only id + savings, not full DOM details
     'render_blocking_count' => count( $audits['render-blocking-resources']['details']['items'] ?? [] ),
     'unused_css_bytes'      => $audits['unused-css-rules']['details']['overallSavingsBytes'] ?? 0,
     'unused_js_bytes'       => $audits['unused-javascript']['details']['overallSavingsBytes'] ?? 0,
     'unminified_css'        => (int) ! empty( $audits['unminified-css']['details']['items'] ?? [] ),
     'unminified_js'         => (int) ! empty( $audits['unminified-js']['details']['items'] ?? [] ),
     'next_gen_images_bytes' => $audits['modern-image-formats']['details']['overallSavingsBytes'] ?? 0,
     'image_dimensions'      => (int) ! empty( $audits['unsized-images']['details']['items'] ?? [] ),
     'font_display'          => (int) ! empty( $audits['font-display']['details']['items'] ?? [] ),
  ],
];
```

This object plus the **current settings map** and **environment facts** (server is nginx/apache via global `$is_apache`; PHP version; WooCommerce present; object cache present; detected via `includes/compat/`) is the *entire* payload. **No page HTML, no visitor data, no PII** (landscape §2.1 privacy).

### 5.2 The prompt (built by `Schema::build_messages()`)

- **System message:** role ("a WordPress performance expert configuring the SwiftPress cache plugin"), hard rules ("you may ONLY recommend changes whose `setting_key` is in the provided whitelist; output must match the JSON schema; `from` will be ignored; never recommend disabling page cache; explain in plain language for a non-technical owner"), and the inlined whitelist with each key's human meaning + risk.
- **User message:** JSON of `{ metrics, current_settings (whitelisted subset only), environment }`.
- Filterable via `swiftpress_ai_system_prompt`.

### 5.3 OpenRouter call (`Client::complete()`)

```php
$model = apply_filters( 'swiftpress_ai_model', 'google/gemini-2.5-flash-lite', $purpose ); // default per landscape §3.3
$body  = [
  'model'           => $model,
  'messages'        => $messages,
  'temperature'     => 0.2,
  'max_tokens'      => $this->budget->output_ceiling(),     // §6 token ceiling
  'response_format' => [
     'type' => 'json_schema',
     'json_schema' => [ 'name' => 'swiftpress_diagnostic', 'strict' => true, 'schema' => Schema::response_schema() ],
  ],
];
$resp = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
  'timeout' => 30,
  'headers' => [
     'Authorization' => 'Bearer ' . $key,                  // server-side ONLY (landscape §3.2)
     'Content-Type'  => 'application/json',
     'HTTP-Referer'  => home_url( '/' ),                    // OpenRouter attribution headers
     'X-Title'       => 'SwiftPress',
  ],
  'body'    => wp_json_encode( $body ),
  'sslverify' => true,
] );
```

- **Parse:** decode `choices[0].message.content` as JSON (it is JSON because of `response_format`); if a model ignored structured mode, attempt a single fenced-JSON extraction, else treat as failure → fallback.
- **Usage:** read `usage.prompt_tokens` / `completion_tokens` from the response and record spend in `Budget` (§6).
- **Errors/timeouts** (`is_wp_error`, non-200, malformed JSON): no exception bubbles to the user — `Diagnostic` falls through to `RulesFallback` and tags `meta.degraded = 'llm_unavailable'`.
- **Model abstraction:** because it's OpenRouter, swapping to `google/gemini-2.5-flash` (more reasoning, landscape pricing) or a Claude/GPT model is a one-line filter change, no code edits (landscape §4 dependency mitigation).

### 5.4 Why server-side only

The browser calls `admin-ajax.php?action=swiftpress_ai_run_diagnostic`; **SwiftPress** calls PSI and OpenRouter. The key is never enqueued, never in any JS, never in a response. This satisfies the WP rule that external calls be admin-triggered + authorized (landscape §3.2, risk table "WP.org compliance").

---

## 6. Response caching, cost controls, graceful degradation

### 6.1 Response cache keyed on hash(metrics + settings) — `Diagnostic`

```php
$cache_key = 'swiftpress_ai_diag_' . md5( wp_json_encode( [ $metrics, $whitelisted_settings, $model ] ) );
$cached = get_transient( $cache_key );
if ( $cached && empty( $force ) ) return $cached + [ 'meta' => [ 'cached' => true ] ];
// … run pipeline …
set_transient( $cache_key, $result, DAY_IN_SECONDS );
```

If nothing about the metrics or settings changed, the *same* explanation is shown with **zero** API spend (landscape §3.4). Invalidate on `swiftpress_settings_saved` (hook into the existing action at dashboard.php:256) and on explicit "re-run". This turns the feature into "AI-assisted, mostly cached."

### 6.2 Cost controls — `Budget`

- **Monthly spend cap.** Option `swiftpress_ai_secrets['monthly_cap_usd']` (default **$2.00**, landscape §3.3). A usage ledger `swiftpress_ai_usage` keyed by `YYYY-MM` accumulates `est_cost` from token counts × the model's per-token price table (Flash-Lite $0.10/M in, $0.40/M out — landscape §0/§3.3). Before any call, `Budget::can_spend()` checks projected cost; over cap → refuse with "Monthly AI budget reached (resets {date}); showing standard recommendations" and serve `RulesFallback`.
- **Per-action token ceilings.** `input_ceiling()` (~6k) clamps the prompt (metrics+settings+whitelist is ~2-4k — landscape §2.1, so this is generous), `output_ceiling()` (~1.5k) caps `max_tokens`. Oversized prompts are refused before sending.
- **Debounce.** A transient `swiftpress_ai_debounce` (default **60s**) blocks back-to-back diagnostics; the Run button disables while a run is in flight (landscape §3.3). NL-config gets a stricter per-minute limit.
- **No per-pageview calls — ever.** Registration is admin-only (§1.2); there is no front-end hook. This is the one hard rule (landscape §0/§3.2).
- **Estimate-before-bulk.** Not needed for the diagnostic (single call), but the same `Budget` API gates the future alt-text bulk feature (landscape §2.5) with a pre-run cost estimate + confirm.

### 6.3 A diagnostic's real cost

~2-4k input + ~1-2k output on Flash-Lite ≈ **$0.0005-0.001 per uncached run** (landscape §2.1). With the §6.1 cache, repeats are free. A $2/mo cap is effectively unreachable through normal use.

### 6.4 Graceful degradation — `RulesFallback`

With **no key** (or LLM down, or over budget), `Diagnostic` calls `RulesFallback::evaluate($metrics, $settings)` which produces the **same `recommended_changes[]` shape** (minus prose `summary`/`findings` richness — it emits terse templated strings) from a static rules table mapping metrics → toggles, e.g.:

- `opportunities.unminified_css` and `!minify_css` → propose `minify_css: true` (risk low, confidence 0.9).
- `unused_css_bytes > 50_000` and `!remove_unused_css` → propose `remove_unused_css` as **suggest-only** (generator gating, §3.2).
- `render_blocking_count >= 4` and `!js_defer` → propose `js_defer: true` (risk medium).
- `font_display` flagged and `!font_display_swap` → propose `enable_font_optimization`+`font_display_swap`.
- `TTFB_ms > 600` and `!enable_page_cache` → propose `enable_page_cache: true` (risk low, high confidence).
- `next_gen_images_bytes > 0` and `!enable_image_optimization` → propose `enable_image_optimization` + `image_optimizer_preferred_format: webp`.

These run through the **same ValidationGate** and **same apply path**, so the feature exists for everyone; the LLM is a pure upgrade layer over a deterministic floor (landscape §3.5). UI shows "AI explanation unavailable — showing standard recommendations."

---

## 7. Undo (snapshot + one-click restore + re-audit)

### 7.1 Snapshot before apply — `Snapshot`

`ajax_apply` flow:

1. Read current `$old = Utils\get_settings()`.
2. `Snapshot::push($old)` → store under option `swiftpress_ai_snapshots` as a small ring buffer (keep last 5) with `{ id, timestamp, settings, applied_changes, metrics_before }`. (Snapshots store **only** `SETTING_OPTION` values — never the key, which isn't in settings anyway.)
3. Build the new settings array = `$old` merged with the user-checked `appliable[]` changes; pass through `sanitize_options()` (dashboard.php:283) so it inherits all coercions; `update_option(SETTING_OPTION, $new)`; `Config::factory()->save_configuration($new, …)` (Config.php:627) — **the exact existing save path** (audit §6 seam #2), which already handles opcache invalidation, advanced-cache regeneration, and secret stripping.
4. `do_action('swiftpress_settings_saved', $old, $new)` so every existing side-effect (preload start/stop, cache flush on toggle, etc. — dashboard.php:212-244) fires identically to a manual save.
5. Fire `do_action('swiftpress_ai_applied', $applied, $snapshot_id)`.
6. Invalidate the §6.1 diagnostic cache.

### 7.2 One-click restore — `ajax_undo`

`Snapshot::restore($id)` re-applies the stored settings array through the **same** `sanitize_options()` → `update_option()` → `save_configuration()` → `swiftpress_settings_saved` path (so undo is just "apply the old map"), then fires `swiftpress_ai_undone`. UI shows "Reverted to settings from {time}."

### 7.3 Re-audit before/after (closes the loop, proves value — landscape §2.1 step 6)

- On apply, store `metrics_before` from the run that produced the proposal.
- After apply, the UI offers "Re-run audit" → a fresh `swiftpress_ai_run_diagnostic` (force-bypass cache) → show a compact **before/after delta** (perf score, LCP, TBT). If a change didn't help, the user undoes — worst case is a safe, reverted toggle (landscape risk table).

---

## 8. How the AI surface appears in the panel (UI-vision-agnostic)

The engine returns a single normalized payload (`{summary, findings[], recommended_changes:{appliable,suggested}, meta}`); the surface is just three states that drop into **any** of the three UI directions (React entry, or design-system-CSS + Alpine — the engine is identical):

1. **Entry point — a "Diagnose" affordance on the dashboard/health surface.** It naturally extends the existing mechanical `run_diagnostic()` health checks (dashboard.php:742): the deterministic checks render first; the AI panel sits beneath as an "AI Performance Advisor" card (audit §6 seam #1). One primary button: **"Analyze my site."**
2. **Result — an "Explain & Fix" card.** Top: the plain-language `summary` + overall pill (good/needs_work/poor). Middle: `findings[]` as a prioritized list (severity-colored, each with its `evidence_metric`). Bottom: `recommended_changes.appliable[]` as a **human diff** with per-item checkboxes ("Turn ON Defer JavaScript — *why:* 6 render-blocking scripts; *risk:* medium; *confidence:* 84%"), plus `suggested[]` rendered as advice with disabled Apply + reason. One primary button: **"Apply selected."** A persistent **"Undo last AI change"** appears whenever a snapshot exists (from `ajax_status`).
3. **Key + budget — a small "AI" settings sub-panel.** Password field (empty when set, with a "key is set via {source}" pill), monthly-cap field, model selector (default Flash-Lite), "Test key" button. When `keySource === 'constant'`, the field is read-only ("set in wp-config.php").

States the surface must handle from `meta`: `cached` (badge "cached result"), `degraded:'llm_unavailable'`/`'over_budget'`/`'no_key'` (banner "showing standard recommendations"), `debounced` (button cooldown). Because every state is data on the payload, the same engine powers a React `<AiAdvisor>` component or an Alpine `x-data` block with no backend change. The natural-language box (v1.1) is the **same result card** fed by a text input instead of the Analyze button.

---

## 9. Build-first slice (thinnest end-to-end vertical) + effort

**Goal:** prove the whole spine — *key → diagnostic → findings → one validated apply → undo* — with the narrowest possible footprint. Defer NL-config, smart-cache-rules, alt-text, REST, desktop polish.

**Ship in this order:**

1. **`KeyStore`** — constant override + `sodium` encrypt/decrypt + dedicated `swiftpress_ai_secrets` option + the **two strip-list edits** (Config.php:630, dashboard.php:158) + import unset. `ajax_save_key` / `ajax_status`. *(The security spine first.)*
2. **`PageSpeed::audit()`** — PSI v5 fetch → Metrics object (mobile only for v1; PSI key optional).
3. **`Schema`** — whitelist map (the ~27 keys in §3.1) + response JSON schema (§4) + message builder.
4. **`Client::complete()`** — OpenRouter `wp_remote_post` with `response_format`, usage parse, redacted errors.
5. **`ValidationGate::filter()`** — the §3.2 algorithm (key/type/range/from-rewrite/requires/conflicts/idempotency).
6. **`Diagnostic`** — glue + transient cache + `RulesFallback` so it works with no key from day one.
7. **`Snapshot` + apply** — `ajax_apply` (snapshot → sanitize_options → update_option → save_configuration → settings_saved) and `ajax_undo`. **Reuses the existing save path verbatim** — no new write code.
8. **`AI::factory()`/`setup()`** — register the 5 core AJAX actions behind the single `guard()`; enqueue+localize `swiftpressAI` on the menu page; boot from `swiftpress.php`.
9. **Minimal surface** — one "Analyze my site" button, a findings list, checkbox diff + "Apply selected", "Undo". Plain styling; the real UI revamp dresses it later (payload is stable).

**Explicitly out of v1:** NL-config, AI smart-cache-rules, alt-text/vision, REST namespace, desktop+mobile PSI dual-run, Critical-CSS/UCSS *generation* (those keys are suggest-only until the algorithmic generators land — audit §5).

**Effort estimate (one experienced WP/PHP dev):**

| Slice | Est. |
|---|---|
| KeyStore + strip-list edits + key AJAX | 0.5 day |
| PageSpeed (PSI parse to Metrics) | 0.5 day |
| Schema (whitelist + JSON schema + prompt) | 0.5 day |
| Client (OpenRouter + structured output + usage/errors) | 0.5 day |
| ValidationGate (deterministic filter) | 0.75 day |
| Diagnostic + cache + RulesFallback | 0.5 day |
| Snapshot + apply/undo (reusing save path) | 0.5 day |
| AI registrar + enqueue + minimal surface | 0.5 day |
| Wiring/integration/manual test on a real site | 0.5 day |
| **Total** | **≈ 4.75 dev-days (~1 working week)** |

Risk buffer: PSI field-data absence on low-traffic sites (handle nulls — done in §5.1) and structured-output quirks per model (single fenced-JSON fallback — §5.3) are the only real unknowns; both have deterministic fallbacks so they can't block the slice.

---

## 10. Mapping back to the codebase (quick reference for the implementer)

| Need | Reuse | File:line |
|---|---|---|
| Save path (apply) | `Config::save_configuration()` + `update_option(SETTING_OPTION)` | Config.php:627; dashboard.php:206-209 |
| Sanitize/coerce on apply | `sanitize_options()` | dashboard.php:283-399 |
| Fire all save side-effects | `do_action('swiftpress_settings_saved', $old, $new)` | dashboard.php:256 |
| Setting keys + defaults (whitelist source) | `Utils\get_settings()` | utils.php:48-140 |
| Value ranges (whitelist source) | `sanitize_options()` coercions | dashboard.php:286-376 |
| Secret-strip (drop-in) — ADD key | `$private_settings` list | Config.php:630 |
| Secret-strip (export) — ADD key | `$sensitive_options` list | dashboard.php:158-168 |
| AJAX/nonce/cap pattern | `run_diagnostic()` + `core.php` localize | dashboard.php:742-829; core.php:181-198 |
| Health-panel attach point | existing diagnostic checks array | dashboard.php:742 |
| Boot ordering | factory() boot list | swiftpress.php:120-130 |
| Per-page metabox keys (future) | `POST_META_DISABLE_*` | constants.php:17-21 |

---

## 11. Compliance & privacy footnotes (carry into readme + the AI sub-panel)

- **Disclose the external services** (OpenRouter + Google PSI) and link their terms/privacy in the readme and the AI settings panel — WP guideline requirement (landscape §4, risk "WP.org compliance"). Calls are **admin-triggered, opt-in, on-demand** only.
- **Data minimization:** the diagnostic sends only derived metrics + a whitelisted boolean/scalar settings map + environment facts (server type, PHP version, "WooCommerce: yes"). **No page HTML, no PII, no visitor data.** URLs audited are the site's own public URLs.
- **Key handling:** constant-or-encrypted-option, never in DB plaintext, never to the browser, never in `sp-config/*.php`, never in export, redacted from logs (§2).
- The single content-leaking feature (alt-text vision, landscape §2.5) is **not** in this engine's v1 and, when added, ships as a separate explicit opt-in module with its own disclosure.
