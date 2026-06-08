# SwiftPress AI Architecture — Adversarial Critique

**Author:** Security / product reviewer (debate pass)
**Date:** 2026-06-08
**Source documents reviewed:** `proposals/ai-architecture.md`, `research/ai-landscape.md`, `research/code-audit.md`
**Stance:** Argue the weaknesses. Confirm what is genuinely solid. Flag everything that must be hardened before building.

---

## Executive Summary

The architecture proposal is substantially well-reasoned. The "LLM proposes; SwiftPress disposes" stance is correct; the dedicated-option isolation for the key, sodium encryption, and structured-output + validation-gate combination represent a defensible security posture. **Most of what is proposed should be built.** However, there are seven concrete issues that need hardening before a single line of the AI module ships, plus four scope/cost items that need a decision. None are blockers in the sense that they require redesign — they are missing guardrails, incomplete specs, and one genuine data-leakage gap.

---

## 1. SECURITY

### 1.1 Key isolation: SOLID with one gap

**What is solid.** Keeping the key in its own `swiftpress_ai_secrets` option (separate from `SETTING_OPTION`) is the correct structural defense. If the key is never a member of `$settings`, it structurally cannot ride the export or `Config::save_to_file()` path. The belt-and-suspenders strip-list edits to `Config.php:630` and `dashboard.php:158` are belt-and-suspenders in the right spirit. The constant-override tier is clean.

**Gap 1 — The strip-list edits are named inconcistently.** The proposal (§2.5) lists three key aliases to strip: `openrouter_key`, `openrouter_key_cipher`, `ai_openrouter_key`. The encryption stores `openrouter_key_cipher` inside the array under `swiftpress_ai_secrets` — but if the AI module ever exposes a settings UI field with a name like `ai_openrouter_key` for the normal WP settings form, that name must be consistent across: the `swiftpress_ai_secrets` option schema, the `$private_settings` list in Config.php, the `$sensitive_options` list in dashboard.php, and the import unset. The proposal does not pin this to one canonical constant. Before building, define `KeyStore::SECRET_KEYS = ['openrouter_key_cipher']` and have both strip lists pull from it — otherwise a future dev adds a field with a fourth alias and one list falls behind.

**Gap 2 — The `swiftpress_secret_strip_keys` filter is a double-edged sword.** The proposal adds a filter so the AI module can register its key names without modifying Config.php. This is architecturally clean. But it also means a malicious or buggy third-party filter callback could _remove_ keys from the strip list. The filter should be applied only for _extension_ (append-only), and the core set (cloudflare keys + AI keys) must be hardcoded as the non-filterable baseline that runs after the filter output. Document this explicitly.

**Gap 3 — The PSI key shares `swiftpress_ai_secrets` but is not mentioned in the strip lists.** Section 5.1 states `psi_key` is stored in the same option. The export/import strip must also cover `psi_key_cipher` (or whatever alias is used). Easy to miss. Add it to the canonical list now.

### 1.2 Encryption at rest: SOLID with one operational risk

The `sodium_crypto_secretbox` design is correct: authenticated symmetric encryption, nonce prepended to ciphertext, key derived from `wp-config.php` salt. The fallback to "no key" on salt-rotation is the right behavior.

**Risk — The `LOGGED_IN_SALT` fallback is not stable across salt regeneration.** The proposal correctly notes that salt rotation renders the stored cipher unreadable and prompts a re-enter. What is not addressed: WordPress hosting panels (Kinsta, WP Engine, Cloudways, Flywheel) periodically auto-rotate salts as a security feature, sometimes without warning. This is a UX hazard — the site owner's AI key silently stops working after a host security event, with no explanation beyond a vague admin notice. The admin notice should be explicit: "Your OpenRouter key could not be decrypted. This usually means WordPress security salts were rotated. Please re-enter your key." Consider adding the timestamp of last successful decryption to the stored option so the notice can say when it last worked.

**Risk — `'swiftpress-fallback'` as the final fallback material.** If neither `SWIFTPRESS_ENCRYPTION_KEY` nor any WP salt constant is defined (an unusual but possible edge case on a stripped-down wp-config), the encryption key is derived from a hardcoded public string. Any stored cipher is then breakable by anyone who has read access to the DB and knows the plugin name. This should refuse to encrypt rather than degrade — the proposal says "never fall back to plaintext" for the case where `sodium_*` is absent, but it says nothing about the key-material fallback. The condition should be: if `SWIFTPRESS_ENCRYPTION_KEY` is not defined AND no WP salt constant is defined, refuse to store an encrypted key and direct the user to define `SWIFTPRESS_OPENROUTER_KEY` in `wp-config.php`. Remove the hardcoded fallback string entirely.

### 1.3 Endpoint authorization: SOLID

The unified `guard()` helper is exactly right. A single helper called at the top of every handler, checking capability before nonce, with correct network-multisite branching (`manage_network` vs `manage_options`) — this is the pattern missing from parts of the existing codebase (audit §3.4) and the proposal correctly fixes it from day one. No issues.

**One minor note:** the proposal uses `$_POST['nonce']` uniformly for all AJAX handlers. If any handler is ever called via GET (e.g. for a status check), the guard silently passes an empty string to `wp_verify_nonce`, which fails. `ajax_status` is a read-only query — it will almost certainly end up as a GET. The guard should check both `$_POST['nonce'] ?? $_GET['nonce'] ?? ''` or, better, accept the method in the guard signature and document that state-changing handlers must be POST.

### 1.4 Log redaction: SPECIFIED but not enforced by design

The proposal requires a `redact()` helper in `Client` that masks `Authorization` headers and `sk-…` substrings before any `Utils\log()` call. This is correct. **But:** the existing `Utils\log()` function (`utils.php`, behind `SWIFTPRESS_ENABLE_LOG`) does not have a redaction layer — it is a raw file write. If a developer adds a `Utils\log()` call anywhere in the AI module _without_ going through the `redact()` helper, the key leaks to the log file. The correct fix is to add redaction at the `Utils\log()` level, not just in the Client. A `SWIFTPRESS_LOG_REDACT_PATTERNS` global array that `log()` applies before writing would protect against future slips. This is not a blocker but it is a defense-in-depth gap that will eventually bite someone.

### 1.5 The NL-config endpoint's exclusion-writing surface: MISSING SPEC

The proposal (§1.3) notes that `ajax_nl_config` (v1.1) handles "natural-language intent → same pipeline." But section 2.3 of the landscape document acknowledges that exclusion/safelist keys (`rejected_uri`, `rejected_cookies`, `ucss_safelist`) are _not_ in the v1 action whitelist "because text exclusions are the high-blast-radius footgun." The v1.1 NL-config box, however, includes an example: `"don't cache the members area"` → `rejected_uri` populated (landscape §2.4). This creates an inconsistency: the diagnostic validation gate blocks exclusion writes, but the NL-config pathway implies they should be allowed. Before v1.1, this must be resolved: either NL-config also excludes free-text exclusion keys (the safer choice, and it shows the suggestion as text to copy), or NL-config gets a second, stricter validation gate with regex-validated URI patterns and an explicit length/count cap. Leaving this unresolved risks the v1.1 implementation inheriting the diagnostic gate without realizing it blocks the feature's main use case.

---

## 2. COST

### 2.1 Monthly cap: SOLID in design, missing in enforcement detail

The `$2.00` default monthly cap stored in `swiftpress_ai_secrets['monthly_cap_usd']` is the right posture. The Flash-Lite pricing math is correct: a diagnostic run is sub-cent, so $2/mo is effectively unreachable through normal use.

**Gap — The usage ledger relies on self-reported token counts from the API response.** `usage.prompt_tokens` / `completion_tokens` from OpenRouter's response are used to compute cost. OpenRouter's token counts are model-accurate but _billed on the upstream provider's count_, which can differ (especially for models with internal reasoning tokens not surfaced in the public `usage` object). For Flash-Lite this is negligible. For any model with thinking/reasoning steps (Gemini 2.5 Flash with thinking, o3-mini, Claude 3.7 Sonnet with extended thinking) the billed tokens can be 5–20× the surface tokens. The `swiftpress_ai_model` filter lets an admin or developer switch to a reasoning model — the budget system must either query OpenRouter's `/api/v1/generation` endpoint to get actual billed cost (available post-call) or refuse to apply the cap to reasoning models and warn that cost accounting is approximate. A simple guard: if the model name contains `/thinking` or the response includes a non-zero `usage.completion_tokens_details.reasoning_tokens`, flag the estimate as approximate.

**Gap — No cap on the _PSI_ request count.** PSI API has a free-tier rate limit of 25 requests/day (unauthenticated) or 400/day with an API key. The debounce (60s) prevents rapid consecutive runs but nothing prevents 25 diagnostic runs in a single day on a low-traffic site without a PSI key. The PSI key is optional but the UX should make clear that without it, the diagnostic can exhaust the anonymous daily quota. Add a counter to the usage ledger for PSI calls.

### 2.2 Debounce: MOSTLY SOLID

The 60s debounce transient blocks back-to-back runs. This is sufficient for the on-demand diagnostic. The proposal also mentions NL-config gets a stricter per-minute limit — good. **One gap:** the debounce is per-site, not per-user. On a multisite install with multiple network admins, Admin A and Admin B could each trigger a diagnostic within 60s of each other, doubling the spend. For v1 this is acceptable (diagnostic cost is trivial); for v1.1 with NL-config (potentially more conversational/iterative), add per-user-ID debounce.

### 2.3 No loop/retry risk: SOLID

The proposal explicitly has no retry logic. `Diagnostic` falls through to `RulesFallback` on error — it does not retry. No async queue, no cron-triggered LLM call. The only scheduled LLM pathway is not present in v1. This is the correct design.

---

## 3. PRIVACY / GDPR

### 3.1 Data minimization: SOLID for the diagnostic

The Metrics object sent to OpenRouter contains: derived performance numbers (LCP ms, TBT ms, etc.), opportunity byte-counts, boolean/enum settings values, and environment facts (server type, PHP version, WooCommerce yes/no). No page HTML, no visitor IPs, no post content, no customer data. The URL audited is the site's own public homepage — already public. This is a well-executed data-minimization design.

**Gap — `home_url('/')` is the audited URL, but PSI may include the full URL in its response, which is then cached as part of the cache key (`hash(metrics + settings)`).** If the site owner has a URL structure that reveals PII (e.g. `https://my-site.com/?member_id=123` as the home URL — pathological but possible), that URL is in the `metrics['url']` field and stored in the transient cache. This is a low-probability edge case but worth noting: the URL should be sanitized/normalized before inclusion in any stored value.

**Gap — OpenRouter as a routing layer introduces provider-specific privacy terms.** The proposal cites the need for a Data Processing Agreement. OpenRouter routes through Google (Gemini), and Google's API terms for Gemini include a 55-day retention period for abuse monitoring (not training, for Google AI Studio API). OpenRouter's own privacy terms are separate. For an EU-based owner (Sebastian, GDPR-relevant), the correct chain is: SwiftPress → OpenRouter (US, DPA available on request per their terms) → Google (EU SCCs auto-incorporated for EEA). The readme and AI settings panel **must** link to both OpenRouter's privacy policy and the downstream model provider's terms, and explicitly state "performance metrics (no personal data) are processed." The proposal mentions this at §11 but does not specify what the disclosure must say — the implementer needs a concrete template.

**Gap — The NL-config input box (v1.1) is a PII surface.** The landscape §2.4 notes: "warn users not to paste secrets into the box." This is insufficient for GDPR compliance. The input box processes user-entered text. If an admin types a URL containing a session token, a customer name, or any identifier, that text leaves the site. The UI must include: (1) a visible notice that text typed here is sent to the OpenRouter API, (2) a link to the privacy policy, and (3) explicit confirmation before the first NL-config call (not just a toggle). This is an opt-in within the already-opted-in AI module.

### 3.2 Alt-text (v1.x) privacy: CORRECTLY DEFERRED

The proposal correctly identifies alt-text as "the one feature where actual site content (the image pixels) leaves the site" and defers it to a separate opt-in module. This is the right call and well-documented. No critique — just confirm this must remain a separate, independently disclosed, separately enabled module and must not be accidentally bundled into the v1 AI toggle.

---

## 4. VALIDATION SOUNDNESS

### 4.1 The validation gate: GENUINELY SOLID

The `ValidationGate::filter()` algorithm is the strongest part of the proposal. The key design decisions are all correct:
- Key-in-whitelist check eliminates invented settings.
- Type + range check with the same semantics as `sanitize_options()` (same coercions) means the gate and the save path agree.
- Overwriting `from` with the real current value (not the LLM's claim) eliminates the LLM's ability to lie about current state.
- Conflict resolution (RUCSS wins over combine_css) is deterministic.
- Capability/generator gating downgrades unapplicable items to "suggest" instead of hiding them.

**One gap — the whitelist has ~27 keys but the `sanitize_options()` function covers more.** Specifically: `cache_query_strings`, `extra_browser_cache_content_types`, `varnish_ip`, `heartbeat_*`, `tracking_*`, `cloudflare_*`, `rejected_uri`, `rejected_ua`, `rejected_cookies` are all in `sanitize_options()` but correctly excluded from the whitelist. The concern is the `swiftpress_ai_action_whitelist` filter — it is explicitly designed to let Pro extensions _add_ keys. If a Pro module adds a key that is a free-text field (e.g. a CDN configuration string), the gate's type-check would accept any string. The gate algorithm (§3.2) handles booleans/ints/enums well but has no length cap or character validation for string-type keys. Add a `max_length` constraint to the whitelist schema for any string-type key, and enforce it in the gate.

**Gap — The structured-output enum enforcement is model-dependent.** The proposal notes: "the gate is the deterministic backstop for models that ignore the enum or for stale enums." This is correct. However, if a model partially respects the schema — e.g., produces a `setting_key` that is a near-miss (`enable_pagecache` instead of `enable_page_cache`) — the gate drops it silently. This is the intended behavior. But the UX then shows fewer recommendations than expected, with no explanation. Consider adding a fuzzy-match log (in the `dropped[]` array, debug-only) that notes "setting_key 'enable_pagecache' not in whitelist; nearest: 'enable_page_cache'" so a developer debugging model drift can diagnose it quickly.

### 4.2 Snapshot + undo: SOLID

The `Snapshot` ring buffer (last 5, stored in option, `SETTING_OPTION` values only — not the AI key) and the undo path that reuses the same `sanitize_options() → update_option() → save_configuration() → swiftpress_settings_saved` flow is correct. The before/after re-audit to prove value is a good UX decision.

**One gap — what happens if the user applies AI changes, then makes manual changes, then undoes?** The undo restores the pre-AI-apply snapshot, overwriting the manual changes. This is the expected behavior for a "undo AI" action, but it will surprise some users. The UI must be explicit: "This will revert ALL settings to the state before the AI changes, including any manual changes made since then." A diff preview on the undo confirmation would be ideal.

### 4.3 `from` field trust: SOLID

The proposal explicitly documents that `from` is "model's guess of current value; IGNORED — PHP overrides from real settings." This is the correct design. The gate overwrites `from` with the actual `$current_settings[$key]` value. Confirmed solid.

---

## 5. WP.ORG COMPLIANCE

### 5.1 External service disclosure: PARTIALLY ADDRESSED

The proposal (§11) states: "disclose the external services (OpenRouter + Google PSI) and link their terms/privacy in the readme and the AI settings panel." This is correct in principle. WP.org guidelines (plugin review rule 7) require:

1. The readme.txt must document every external service, what data is sent, and link to the terms and privacy policy.
2. The service calls must be opt-in and user-initiated.
3. No silent phone-home.

The proposal satisfies requirements 2 and 3 (admin-triggered, opt-in, on-demand). Requirement 1 needs a concrete block in `readme.txt`:

```
== External Services ==

This plugin optionally connects to the following external services when the AI Performance Diagnostic feature is used:

* Google PageSpeed Insights API (https://developers.google.com/speed/docs/insights/v5/get-started)
  - Used to collect performance metrics for the audited URL.
  - Data sent: the URL of your site's homepage (a public URL). No personal data is sent.
  - Terms: https://developers.google.com/terms
  - Privacy: https://policies.google.com/privacy

* OpenRouter API (https://openrouter.ai)
  - Used to generate plain-language explanations of performance findings.
  - Data sent: derived performance metrics (scores, byte counts) and a list of which SwiftPress features are enabled. No page content, no visitor data, no personal information is sent.
  - Terms: https://openrouter.ai/terms
  - Privacy: https://openrouter.ai/privacy
```

This block must be in `readme.txt` before the plugin can be submitted to WP.org. The proposal should spec this out rather than leaving it to the implementer to discover during review.

### 5.2 Opt-in implementation: SOLID

The key-entry requirement is the implicit opt-in (no key = no LLM call). PSI is called as part of the diagnostic which is also button-triggered by an admin. No automated or background AI calls exist in v1. This is WP.org compliant.

**Edge case:** the `swiftpress_settings_saved` hook invalidates the diagnostic cache. If a future feature triggers `swiftpress_ai_run_diagnostic` in response to `swiftpress_settings_saved` (e.g. "re-analyze after every save"), that would be an implicit, potentially non-admin-triggered call. The proposal does not propose this, but the filter surface (`swiftpress_ai_psi_urls`, `swiftpress_ai_recommendations`) would make it easy for a developer to implement accidentally. Document in the filter docblocks: "never hook this filter to trigger a diagnostic run automatically."

---

## 6. SCOPE VERDICT

### 6.1 Build-first slice: CORRECTLY SCOPED

The v1 vertical (key → PSI → LLM → findings → validated apply → undo) is the right scope. It proves the full spine, ships a complete user-facing feature, and correctly defers NL-config, alt-text, REST namespace, and dual desktop/mobile PSI runs. The 4.75-day effort estimate is reasonable for an experienced WP dev who has read this document.

**One adjustment:** the estimate treats `ValidationGate` as 0.75 days. This is probably the trickiest single piece — the gate must handle all the conflict-resolution cases, the requires-chain resolution, and the string-type key guard noted above. Budget 1.0–1.25 days. Total ~5.0–5.5 days is more realistic.

### 6.2 What is missing from the build-first slice that should NOT be deferred

Three items from the proposal are architecturally required but their absence from the effort table suggests they may be left out:

1. **The readme.txt external-service disclosure block (§5.1 above).** This is a WP.org submission blocker, not a nice-to-have. It is a 30-minute writing task but must be done before any beta release.
2. **The `sanitize_options()` hardening** (code-audit §3.3, P0 recommendation #3). The AI apply path uses `sanitize_options()` as its sanitizer. If `sanitize_options()` still dereferences keys without `isset()`, the AI module inherits that PHP 8 warning surface on every apply. This should be fixed in the same sprint — it's on the P0 list already.
3. **The font-cache-dir bug fix** (code-audit §4.3, P0 recommendation #2). Not directly related to AI, but if the AI diagnostic recommends "enable font optimization" and the user applies it, the "clear font cache" AJAX silently no-ops. The user experience breaks at the step right after the AI feature succeeds. Fix it in the same sprint.

### 6.3 What is in the build-first slice that could be deferred

`RulesFallback` (§6.4) is the right architectural call for graceful degradation, but it adds non-trivial surface area (a static rules table that must stay in sync with the whitelist, tested independently). For an internal beta / first technical review, the fallback could be simplified to: no key → show a message "Enter your OpenRouter key to run the AI diagnostic." The full `RulesFallback` is a v1.1 item. This recovers 0.5 days from the estimate.

---

## 7. CONFIRMED SOLID — WHAT TO BUILD WITH CONFIDENCE

These design decisions are genuinely strong and should not be second-guessed:

1. **"LLM proposes; SwiftPress disposes."** The constraint that the LLM fills fixed fields from a closed vocabulary, validated deterministically in PHP, is the correct 2025 approach. No competitor does this in the perf space.
2. **Dedicated `swiftpress_ai_secrets` option.** Structural isolation is better than strip-list-only defense.
3. **`sodium_crypto_secretbox` with wp-config salt derivation.** Industry-standard, already polyfilled by WP, correct key-in-filesystem / cipher-in-DB split.
4. **Single `guard()` helper on every endpoint.** Capability before nonce, network-aware. Fixes the pre-existing codebase hygiene issue from the start.
5. **Reusing the existing `sanitize_options() → update_option() → save_configuration() → swiftpress_settings_saved` save path for AI apply.** Zero new write-path surface. All existing side-effects (cache flush on toggle, opcache invalidation, etc.) fire identically.
6. **Response cache keyed on `hash(metrics + settings)`.** Turns a $0.001/run feature into a free-on-repeat feature. Critical for cost.
7. **`RulesFallback` same shape as LLM output.** The feature ships for everyone; the LLM is a pure upgrade. Consistent with the "no-bloat" identity.
8. **`Snapshot` ring buffer, undo via the same save path.** Worst-case outcome of a bad AI recommendation is a safe, reverted toggle. This is the correct failure design.
9. **`swiftpress_ai_model` filter defaulting to Flash-Lite.** OpenRouter abstraction means model migration is a one-line filter change. The default is the right cost/capability trade-off for this use case.
10. **PSI-only data in the LLM prompt (no HTML, no PII).** Data minimization achieved at the architecture level, not as an afterthought.

---

## 8. HARDENING CHECKLIST (before first line of code)

In priority order:

| # | Item | Section | Effort |
|---|---|---|---|
| H1 | Canonicalize secret key aliases to one constant list (`KeyStore::SECRET_KEYS`); have both strip lists pull from it | §1.1 Gap 1 | 1h |
| H2 | Remove the hardcoded `'swiftpress-fallback'` enc-key material; refuse to store if no salt/constant available | §1.2 Risk 2 | 1h |
| H3 | Add PSI key (`psi_key_cipher`) to the strip-list canonical set | §1.1 Gap 3 | 30m |
| H4 | Make `swiftpress_secret_strip_keys` filter append-only (hardcode baseline, filter only extends) | §1.1 Gap 2 | 1h |
| H5 | Add per-user debounce for v1.1 NL-config; document multisite limitation for v1 | §2.2 | 1h |
| H6 | Add a `max_length` constraint to the whitelist schema for any string-type key; enforce in gate | §4.1 Gap | 1h |
| H7 | Draft the `readme.txt == External Services ==` block; include before any public release | §5.1 | 30m |
| H8 | Fix `guard()` to check `$_POST['nonce'] ?? $_GET['nonce'] ?? ''` for read-only handlers | §1.3 Note | 30m |
| H9 | Add dropped-with-fuzzy-match annotation in `ValidationGate` debug output | §4.1 Gap 2 | 1h |
| H10 | Explicit admin notice text for decryption failure due to salt rotation | §1.2 Risk 1 | 30m |

Total hardening effort before build: approximately 8 hours. None require architectural changes.

---

## 9. RISK TABLE (revised)

| Risk | Architecture doc rating | This review rating | Delta |
|---|---|---|---|
| Key leakage to browser | Mitigated | Mitigated | — |
| Key in sp-config drop-in | Mitigated | Mitigated | — |
| Key in export | Mitigated | Mitigated | — |
| Key in logs | Partial (Client-level redact) | MEDIUM — `Utils\log()` has no global redaction layer | Worse |
| Salt rotation loses key | Acknowledged | MEDIUM UX — notice needs better copy; hardcoded fallback material must be removed | Worse |
| Hallucinated/invalid setting applied | Mitigated by gate | Mitigated | — |
| Spend runaway | Mitigated | Mitigated — minor gap on reasoning-model token accounting | Slightly worse |
| PSI rate limit exhaustion | Not addressed | LOW — add PSI call counter | New |
| PII in NL-config input | Noted in landscape | MEDIUM — needs explicit pre-send disclosure, not just a warning | Worse |
| WP.org compliance | Noted | MEDIUM — readme block is unspecified; must be drafted | Worse |
| Over-exclusion from NL-config | Not resolved | MEDIUM — whitelist inconsistency with landscape §2.4 example | New |
| Undo wipes manual changes | Not addressed | LOW — needs UI warning, not architecture change | New |
| Alt-text privacy | Correctly deferred | Correctly deferred | — |
| API dependency | Mitigated (OpenRouter, fallback) | Mitigated | — |
