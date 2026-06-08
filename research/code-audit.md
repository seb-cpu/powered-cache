# SwiftPress — Rigorous Code Audit (revamp-2026)

Branch: `revamp-2026` · ~33.8k LOC PHP (most of it vendored deps) · Namespace `SwiftPress` · PHP target 8.x · Builds via 10up-toolkit/webpack.

This audit is based on a direct read of the real source. File:line references are to the working tree.

---

## 1. Architecture & Request Flow

### 1.1 Bootstrap (`swiftpress.php`)
- Defines constants, requires the Composer autoloader if present, then registers its **own** `spl_autoload_register` mapping `SwiftPress\*` → `includes/classes/*` (PSR-4-ish). Vendored libs are Mozart-prefixed into `SwiftPress\Dependencies\*` (`composer.json` `extra.mozart`) so they don't collide with other plugins' copies.
- Two non-class packages are hard-required: deliciousbrains `wp-background-processing` (async queue base) and a symfony php80 polyfill.
- Dev-mode / `noswiftpress` bypass short-circuits the frontend before bootstrapping (`swiftpress.php:100-116`).
- Boot order (`swiftpress.php:120-130`): `Core\setup()` → `Admin\Dashboard\setup()` → `Admin\Notices\setup()` → `Install::factory()` → `AdvancedCache::factory()` → `Cron::factory()` → `Preloader::factory()` → `Async\SitemapPreloader::factory()` → `FileOptimizer::factory()` → `FontOptimizer::factory()` → `Extensions::factory()`.
- Every subsystem is a **singleton via static `factory()`** that calls `setup()` to register hooks. This is the dominant structural pattern across the codebase. There is no DI/container; coupling is through global WP hooks + `Utils\get_settings()`.

### 1.2 Settings model
- One option, `swiftpress_settings` (`Constants::SETTING_OPTION`), holds the entire config. `Utils\get_settings()` (`utils.php:45-163`) merges stored values over a hard-coded default array via `wp_parse_args`. Network installs use `get_site_option`.
- Settings are persisted **twice**: (a) in the WP option, and (b) flattened to a generated PHP file under `wp-content/sp-config/config-<host>.php` (`Config::save_to_file`, `Config.php:411-468`) so the page-cache drop-in can read config without a full WP load. The drop-in reads `$GLOBALS['swiftpress_options']` plus a handful of `$swiftpress_*` globals (rejected cookies/UA/URIs, vary cookies, cache query strings, slash check) from that file.

### 1.3 Page cache — two delivery paths + one PHP fallback
SwiftPress is an htaccess/nginx-fronted **file cache** with a PHP drop-in fallback, forked from tlovett1/simple-cache.

1. **advanced-cache.php drop-in (PHP path).** `Config::generate_advanced_cache_file()` (`Config.php:92-167`) writes `wp-content/advanced-cache.php`. That generated file: builds a `$config_locations` lookup (network → subdomain → host), `include`s the first existing `sp-config/config-*.php`, bails if `$GLOBALS['swiftpress_options']` is unset, then includes `includes/dropins/page-cache.php` (or a `SWIFTPRESS_ADVANCED_CACHE_DROPIN` override). `define_wp_cache()` (`Config.php:178-223`) rewrites `WP_CACHE` in `wp-config.php`.
   - `page-cache.php` runs at `advanced-cache.php` time (before WP fully loads). On a request it: rejects non-GET, admin, disallowed extensions, rejected UA/referrer/cookie/URI, logged-in users (unless `loggedin_user_cache`), mobile (unless `cache_mobile`), and disallowed query strings; then `swiftpress_serve_cache()` (`page-cache.php:444-519`) reads `cache_location/swiftpress/<host>/<path>/<index file>` (+ `meta.php` for stored response headers), handles 304 via `If-Modified-Since`, replays stored headers, optionally sets gzip encoding, `readfile()`s and exits.
   - On a MISS it registers `ob_start('swiftpress_page_buffer')` (`page-cache.php:212-436`): skips < 255 bytes, `DONOTCACHEPAGE`, password-protected, 404, search, non-200; writes `meta.php` (JSON of allow-listed headers, guarded by `<?php exit;`) and the index HTML (gzipped at level 3 if enabled). Index filename is computed by `swiftpress_index_file()` (`page-cache.php:562-658`) with `-https`, `-mobile`, `-user_<id>-<hash>`, vary-cookie hash, and a cache-query-string hash — this is the cache key.

2. **.htaccess rewrite (Apache fast path).** `Htaccess::rewrite_rules()` (`Htaccess.php:305-423`) emits `mod_rewrite` rules that serve `%{DOCUMENT_ROOT}/<cache_path>/%{HTTP_HOST}%{REQUEST_URI}/index{-https}{-mobile}.html{.gz}` directly when the file exists, bypassing PHP entirely for anonymous hits. Also emits `mod_expires`, gzip (`mod_deflate`/`mod_filter`), ETag removal, `Cache-Control`, and the `/_static/` file-optimizer rewrite. Wrapped in `# BEGIN SWIFTPRESS … # END SWIFTPRESS` markers (`Config::configure_htaccess`, `Config.php:260-320`).

3. **nginx (manual).** `Config::nginx_rules()` (`Config.php:479-585`) generates an equivalent `try_files` config the admin downloads and pastes into their server block (see §8).

**Purge flow.** `AdvancedCache` (`AdvancedCache.php`) hooks `transition_post_status`, comment status changes, theme switch, term changes, site updates. `Utils\get_post_related_urls()` (`utils.php:473-642`) computes the blast radius (permalink, REST routes, AMP, feeds, author/date/taxonomy archives, home, posts page) and either deletes synchronously or pushes to the async `CachePurger` queue (wp-background-processing). Admin-bar "Purge" actions are `admin_post_*` handlers.

### 1.4 File optimization — combine/minify request flow
Two cooperating halves:

- **Producer (inside WP).** When `combine_css`/`combine_js` are on, `FileOptimizer::setup_css_combine/js_combine` (`FileOptimizer.php:477-543`) swap `$wp_styles`/`$wp_scripts` for `Optimizer\CSS`/`Optimizer\JS` subclasses. Their `do_items()` (`CSS.php:57-218`, `JS.php:87-266`) walk the dependency graph, decide per-handle whether to concat (internal URL, real file under ABSPATH, `.css`/`.js`, not excluded, not conditional/inline/react), then build a combined URL. The file list is serialized as `path1,path2?m=<mtime>`; when `allow_gzip_compression` is on and it's shorter, it is compacted to `-<base64(gzcompress(list))>` (`CSS.php:182-185`, `JS.php:213-216`). `Helper::get_optimized_url()` (`Helper.php:98-110`) targets either `includes/file-optimizer.php??<list>&minify=N` or, when `rewrite_file_optimizer` is on, `/_static/??<list>` (rewritten to the script by Apache/nginx).
- **Consumer (standalone endpoint).** `includes/file-optimizer.php` is a **near-standalone script** (ported from Automattic/nginx-http-concat). It does NOT boot WordPress. It parses the `??` query, decodes the path list, validates each path, reads each file from `CONCAT_FILES_ROOT`, rewrites relative `url(...)`/`@import`/`@charset`, concatenates, optionally minifies via the Mullie minifier, sets a 1-year cache header, echoes, and writes a hashed cache file under `wp-content/cache/min/` for next time.
- **Minify-only / defer / delay / HTML-min** run in an output buffer (`FileOptimizer::process_buffer`, `FileOptimizer.php:187-194`) registered at `after_setup_theme` priority 999. Per-tag minify URLs are produced by `css_minify`/`js_minify` filters on the loader tags.

### 1.5 Font optimization (`FontOptimizer`, since 3.8)
Self-hosts Google/Bunny fonts: intercepts enqueued font stylesheets at `wp_enqueue_scripts` p999 and an output buffer for inline `<link>`s, fetches the remote CSS with a Chrome UA (to get woff2), downloads each `url()` font into `wp-content/cache/swiftpress/fonts/<md5>/`, rewrites the CSS to local URLs, injects `font-display: swap`, prints `rel=preload` hints, and strips Google `dns-prefetch`/`preconnect` resource hints. Cache is keyed by `md5(normalized CSS URL)`.

### 1.6 Preloading
`SitemapPreloader` (async, since 3.8) detects a sitemap (robots.txt → wp-sitemap.xml → sitemap_index.xml → sitemap.xml), recursively parses it (SimpleXML, capped by `MAX_URLS` + a fetch timeout), stores URLs in a custom `{$prefix}swiftpress_preload_urls` table, and crawls them in batches via cron with a configurable inter-request interval. A separate legacy `Preloader` + `Async\CachePreloader` handle the non-sitemap homepage/posts/tax preload. Progress is surfaced via AJAX (`ajax_preload_status`) and WP-CLI (`wp swiftpress preload status`).

### 1.7 Extras
- `Extensions\Cloudflare` (purge CF cache, real-IP restore), `Extensions\Heartbeat` (Heartbeat throttling), Varnish purge (IP-based), and a large `includes/compat/` directory with per-plugin/theme shims (Elementor, Divi, WPML, WooCommerce, AMP, cookie-consent plugins, etc.).
- `CLI` exposes `flush`, `flush fonts`, `preload status|refresh`, `status`.
- `Install` runs versioned migrations gated on `swiftpress_db_version` (currently `3.8`; plugin version `1.0.0` — see §4).

---

## 2. Strengths

- **Real two-tier cache architecture done right:** the htaccess/nginx fast path serves anonymous hits without PHP, and the PHP drop-in is a genuine fallback that works with zero server config. The drop-in correctly runs before WP, reads a flattened config file, and stores per-variant cache keys (SSL/mobile/logged-in/vary-cookie/query-string). This is the same design used by WP Super Cache / Powered Cache and is sound.
- **Cache-key correctness:** `swiftpress_index_file()` accounts for SSL, mobile, logged-in user, vary cookies, and explicitly-cached query strings, and stores response headers in a sidecar `meta.php` so non-HTML content types (feeds, wp-json) replay correctly. The `meta.php` is guarded with `<?php exit;` so a direct hit can't leak it.
- **Purge blast-radius is thorough:** `get_post_related_urls()` covers permalinks, REST routes, feeds, author/date/taxonomy/ancestor archives, AMP, and the static posts page — better than many competitors.
- **No dangerous sinks.** A full scan for `eval`, `create_function`, `assert`, `extract`, `unserialize`, `system`, `exec`, `proc_open`, `shell_exec`, `passthru`, `popen` across all first-party code returns **zero** hits. The only `base64_decode` is the benign concat-protocol decode (see §3.1).
- **Consistent auth on state-changing endpoints.** Every `admin_post_*` and `wp_ajax_*` handler verifies a nonce AND a capability (`manage_options`/`manage_network`/`activate_plugins`). The newer AJAX endpoints use the idiomatic `check_ajax_referer()` + `current_user_can()` pattern.
- **Parameterized SQL.** `SitemapPreloader` is the only component touching `$wpdb` directly and uses `$wpdb->prepare()` consistently (`SitemapPreloader.php:480-540`); table name comes from `$wpdb->prefix`, not user input.
- **Dependency isolation via Mozart.** Bundled libs are namespaced under `SwiftPress\Dependencies\*`, avoiding the classic "two plugins ship different minifier versions" fatal.
- **Sensible defaults + an extensive filter surface.** Nearly every behavior is wrapped in an `apply_filters('swiftpress_*')` / `do_action('swiftpress_*')` hook with docblocks. This makes the plugin highly extensible and — importantly for the revamp — gives AI features clean seams to attach to (see §6).
- **Multisite-aware throughout** (network vs per-site config files, capability gating, `switch_to_blog` on purge).
- **Good operational ergonomics:** WP-CLI surface, X-SwiftPress-Cache HIT/MISS headers with optional miss-reason header, structured logging behind `SWIFTPRESS_ENABLE_LOG` with per-IP filtering.

---

## 3. Security Review

### 3.1 `base64_decode` in the file optimizer — VERDICT: BENIGN (by design)

Exact line — `includes/file-optimizer.php:178`:

```php
if ( '-' == $args[0] ) {
    $args = @gzuncompress( base64_decode( substr( $args, 1 ) ) );

    // Invalid data, abort!
    if ( false === $args ) {
        maybe_add_debug_log( sprintf( "File Optimizer 400 - Invalid Data" ) );
        concat_http_status_exit( 400 );
    }
}
```

**What it decodes and why.** This is the consumer side of the Automattic `ngx_http_concat` "compact URL" protocol. When the producer (`Optimizer\CSS::do_items` / `Optimizer\JS::do_items`) builds a combined-asset URL, the file list `path1.css,path2.css?m=<mtime>` can get long; if `allow_gzip_compression` is on and the compressed form is shorter, the producer encodes it as `base64_encode( gzcompress( $path_str ) )` and prefixes `-` (`CSS.php:182-185`, `JS.php:213-216`). The endpoint detects the leading `-` and reverses it: `base64_decode` → `gzuncompress` → back to the comma-separated **file path list**. It decodes *data (a list of asset paths)*, never code, and is never `eval`'d, `include`d, or executed.

**Why it is safe.** The decoded value is only ever treated as a list of file paths, and each path is then run through the same hardening as the plaintext path form:
- `gzuncompress` returning `false` aborts with 400 (`file-optimizer.php:181-184`), so malformed base64 is rejected.
- Each path goes through `concat_get_path()` (`file-optimizer.php:131-143`) which **rejects `..` and null bytes** and prefixes the fixed `CONCAT_FILES_ROOT`, so path traversal outside the docroot is blocked.
- `file_exists()` + `concat_get_mtype()` enforce a **css/js-only MIME allow-list** (`$concat_types`, `file-optimizer.php:59-62`, checked at 263-267); anything else 400s.
- A `$concat_max_files = 150` cap (`file-optimizer.php:57`, 209-212) bounds amplification.

This matches the upstream Automattic implementation and is a well-understood, low-risk pattern. **Not flagged.** (The realistic worst case is an unauthenticated user crafting a `??-<base64>` URL to read/concat arbitrary **css/js files that already exist under the docroot** and have them cached — i.e. no disclosure beyond already-public static assets. See the hardening note below for the one residual sharp edge.)

Residual sharp edge (robustness, not a vuln): `$args[0]` at `file-optimizer.php:177` and the unconditional `$_SERVER['REQUEST_METHOD']` / `$_SERVER['SERVER_PROTOCOL']` / `$_SERVER['REQUEST_URI']` reads (lines 109, 157, 165) assume those keys exist. Under unusual SAPIs/fuzzing this yields PHP 8 warnings, and a `??-` with empty payload reaches `base64_decode('')`. Harmless but worth tightening in the rewrite.

### 3.2 Import / Export settings flow — MEDIUM
`process_form_submit()` handles import at `dashboard.php:182-188`:

```php
case 'import_settings':
    if ( $_FILES['import_file'] && ! empty( $_FILES['import_file']['tmp_name'] ) ) { // phpcs:ignore
        $import_data     = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore
        $import_settings = json_decode( $import_data, true );
        $options         = sanitize_options( $import_settings );
    }
    break;
```

Mitigations that ARE present: the whole `process_form_submit` is gated by `current_user_can('manage_options')` (+ `manage_network`) at lines 125-131 **and** a verified nonce `swiftpress_update_settings` at 133-134; the parsed JSON is fully re-run through `sanitize_options()` before being stored. So this is an admin-only, nonce-protected, sanitized import — not a direct RCE/stored-XSS vector.

Issues to fix in the revamp:
- **No file-type/size/upload-error validation.** It reads `tmp_name` directly with `file_get_contents` and never checks `$_FILES['import_file']['error']`, size, or that the content is JSON. A non-JSON upload makes `json_decode` return `null`, which is then passed to `sanitize_options($null)` → many **undefined-array-key + null-access warnings** (see §3.3 / §4). Use `wp_handle_upload`/`wp_check_filetype` semantics or at least validate `error === UPLOAD_ERR_OK`, cap size, and bail if `json_decode` yields a non-array.
- **`$_FILES['import_file']` is accessed without `isset()`** (line 183), so submitting `import_settings` with no file emits a warning before the `! empty()` guard short-circuits.

### 3.3 `sanitize_options()` reads many keys without `isset()` — MEDIUM (security-adjacent robustness)
`sanitize_options()` (`dashboard.php:283-399`) is the central sanitizer and is mostly correct in spirit (booleans via `! empty()`, `absint()`, `sanitize_text_field`/`sanitize_textarea_field`, `sanitize_email`, `esc_url_raw`, custom `sanitize_css`). **But** it dereferences ~30 keys unconditionally, e.g. `absint( $options['cache_timeout'] )` (291), `sanitize_textarea_field( $options['rejected_user_agents'] )` (294), `$options['cache_timeout'] > 0` (332), `sanitize_text_field( $options['cloudflare_api_key'] )` (360). On the normal form POST every key exists, so this is fine in the happy path. On the **import path** (a hand-crafted/partial JSON) every missing key throws a PHP 8 "Undefined array key" warning, and `sanitize_textarea_field(null)` is itself deprecated-passing-null in 8.1+. Harden by reading through a defaults-merged array (or `$options[$k] ?? ''`).

`sanitize_css()` (`utils.php:1059-1067`) is a reasonable CSS sanitizer (strip tags, neutralize stray `<`), used for `critical_css_appended_content`/`critical_css_fallback`.

### 3.4 Input handling hygiene on nonce reads — LOW/INFO
Several `admin_post` handlers read the nonce as `wp_verify_nonce( $_GET['_wpnonce'], ... )` **without** `wp_unslash`/`sanitize_text_field` (all `phpcs:ignore`d): `AdvancedCache::purge_page_cache_network_wide` (`AdvancedCache.php:142`), `dashboard.php` `purge_all_cache_action` (538), `download_rewrite_config` (608), `deactivate_plugin` (839), `Cloudflare::delete_cloudflare_cache` (`Cloudflare.php:123`). Nonces are alphanumeric so this is not exploitable, but it's inconsistent with the better-written handlers (`purge_page_cache` at 171 does unslash+sanitize) and should be normalized.

### 3.5 `get_client_ip()` trusts `X-Forwarded-For` — LOW
`utils.php:847-855` returns `wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])` unsanitized. It is only used by `Utils\log()` and the per-IP log gate, both behind `SWIFTPRESS_ENABLE_LOG`. Impact is **log injection / log-filter spoofing** when logging is enabled (an attacker controls the logged IP and can inject newlines into the log line at `utils.php:788`). Not a default-on issue. Sanitize/validate (`FILTER_VALIDATE_IP`) and strip CRLF in the rewrite.

### 3.6 advanced-cache.php drop-in generation — OK, with notes
`Config::advanced_cache_file_content()` (`Config.php:120-167`) builds the drop-in by string concatenation of **static** PHP — there is no user-controlled value interpolated into executable positions (paths come from `SWIFTPRESS_DROPIN_DIR`, a plugin constant). The generated file `include`s `sp-config/config-*.php` chosen by `$_SERVER['HTTP_HOST']` + first request-uri segment; the candidate set is constrained to the `sp-config/` directory with a fixed `config-<host>.php` shape, and a non-existent file simply fails the `@file_exists` check. `Host`-header poisoning at worst selects a different (non-existent) config filename → falls through to the default host config; it cannot escape the directory or include an arbitrary path. `save_to_file()` writes config via `var_export()` (safe serialization) and `opcache_invalidate`s. **No injection found.** Two hardening notes: (1) the generated drop-in does not itself re-check `ABSPATH` before including config files (it relies on `defined('ABSPATH')||exit` at the top, which is fine); (2) consider writing an `index.php` guard and `.htaccess deny` for `sp-config/` (currently only the cache dir is protected, `Config::protect_cache_dir`).

### 3.7 Output escaping in admin — mostly OK
The settings page and notices escape with `esc_html__`, `esc_attr`, `esc_textarea`, `esc_url`. A few intentional `echo`s of pre-escaped/translated strings are `phpcs:ignore`d (e.g. `maybe_display_message` success/error notices at `dashboard.php:487-502`, `Utils\settings_errors` at `utils.php:307`). The message text is from a fixed allow-list keyed by `$_GET['sp_action']`, so no reflected XSS — but `$_GET['language']` is interpolated into a success message via `esc_attr(urldecode_deep(...))` at `dashboard.php:468`; it is escaped, so safe, but it's the one place untrusted `$_GET` reaches notice HTML and deserves a second look in the rewrite. The combined-asset tags in `Optimizer\CSS::do_items` (`CSS.php:198`) build a `<link>` with `$href`/`$media`/`$idx` and echo it unescaped (`phpcs:ignore`); values are plugin-derived (mtime, optimized URL), not request input.

### 3.8 SSRF / XXE on sitemap & font fetch — LOW/INFO
- `SitemapPreloader::fetch_sitemap_content` uses `wp_remote_get($url, ['sslverify' => false])` (`SitemapPreloader.php:379-385`) on an **admin-supplied** `preload_sitemap` URL (or an auto-detected same-origin URL). Admin-only, but `sslverify=false` + an arbitrary URL is a mild SSRF/credential-leak smell; tighten to verified TLS and validate the host.
- XML is parsed with `simplexml_load_string` without `LIBXML_NOENT` (`SitemapPreloader.php:314`). On PHP 8 / libxml ≥ 2.9 external-entity expansion is off by default, so **practical XXE risk is negligible**; still, set the explicit flags in the rewrite for defense-in-depth.
- `FontOptimizer` fetches remote font CSS/files with a spoofed Chrome UA and writes them to disk; URLs are constrained to `fonts.googleapis.com`/`fonts.bunny.net` by `is_google_font_url()` before fetch, which bounds the SSRF surface well.

---

## 4. PHP 8.3 / WP 6.7+ Compatibility

**Headline mismatch:** `swiftpress.php:9` and `readme.txt` declare `Requires PHP: 7.4` and `Tested up to: 6.7`, while `composer.json` requires `php >=7.4`. The revamp targets PHP 8.x; bump the `Requires PHP` header to `8.0`/`8.1` and add an 8.3 CI matrix. Also note **plugin version is `1.0.0` but `SWIFTPRESS_DB_VERSION` is `3.8`** and migrations reference versions up to 3.8 — the version history was clearly rebased/renamed (this was "Powered Cache"); the mismatch is confusing but not breaking.

### Real PHP 8.x issues found
1. **Undefined array keys on settings never defined in defaults — HIGH (warning spam, every optimized request).**
   `FileOptimizer` reads `$this->settings['combine_google_fonts']` (`FileOptimizer.php:142`), `['use_bunny_fonts']` (205), and `['swap_google_fonts_display']` (638). **None of these keys exist** in `Utils\get_settings()` defaults (`utils.php:48-140`), none are produced by `sanitize_options()`, and none are on the settings form. On PHP 8.0+ each read emits `Warning: Undefined array key`. `combine_google_fonts` gates an `add_action`, so the Google-fonts-combine method (`combine_google_fonts`, line 549) is effectively dead/unreachable via the current UI as well. **This is a concrete bug, not theoretical.**
2. **`sanitize_options()` unguarded key reads + `sanitize_textarea_field(null)`** on the import path — see §3.3. PHP 8.1 deprecates passing `null` to non-nullable string params.
3. **Font cache directory mismatch — MEDIUM (functional bug).** `FontOptimizer` writes to `wp-content/cache/swiftpress/fonts/` (`FontOptimizer.php:136`), but the "Clear Font Cache" AJAX (`dashboard.php:694`, `ajax_clear_font_cache`) and `CLI::flush_fonts` (`CLI.php:120`) both delete `wp-content/cache/fonts/` (no `swiftpress/`). The font cache clear silently no-ops. `Install::protect_cache_dirs` (`Install.php:276-277`) correctly references `cache/swiftpress/fonts/`, confirming the AJAX/CLI paths are the wrong ones.
4. **WP_Scripts/WP_Styles subclassing under WP 6.7 — MEDIUM, monitor.** `Optimizer\CSS`/`JS` extend core `WP_Styles`/`WP_Scripts`, unset all declared properties in the constructor, and proxy everything through `__get/__set/__isset/__unset` to a wrapped `old_styles`/`old_scripts` (`CSS.php:31-47,266-292`; `JS.php:34-50,314-340`). Because they implement the magic methods, they avoid the PHP 8.2 "creation of dynamic property" deprecation — **but** this is fragile against core changes: WP periodically adds typed/initialized properties to `WP_Dependencies`/`WP_Scripts` (e.g. recent `$default_dirs`, script-module work), and the "unset then proxy" trick has broken combine features in similar plugins on WP upgrades. Add integration tests pinned to current WP and watch 6.7/6.8 dependency-API changes. `do_items()` also reads `$js_url_parsed['path']`/`$css_url_parsed['path']` without `isset` (`JS.php:127`, `CSS.php:95`) — a `data:`/mailto/opaque src yields a warning.
5. **Unguarded superglobal/array reads** sprinkled through the drop-in and file optimizer (`page-cache.php` `$_SERVER['REQUEST_URI']` in many spots; `file-optimizer.php` `$_SERVER[...]`, `$args[0]`). Many are `phpcs:ignore`d. They don't crash but generate 8.x warnings; in a drop-in those warnings can be emitted **before headers**, occasionally corrupting cached output. Worth a hardening pass.
6. **`is_writeable`/`is_writable` spelling** is used inconsistently (`run_diagnostic` uses `is_writeable`, `dashboard.php:753` etc.) — both are valid aliases in PHP 8.3, no action needed, just noting.

### WP 6.7+ specifics
- `load_plugin_textdomain` timing: i18n is hooked on `init` (`core.php:23`, `core.php:48-52`) which is correct for the WP 6.7 "translations must load on/after `init`" `_doing_it_wrong` notice. Good.
- No use of functions removed/deprecated through 6.7 was found in first-party code (no `FILTER_SANITIZE_STRING`, `each()`, `create_function`, `get_magic_quotes_*`, `libxml_disable_entity_loader`, `wp_make_content_images_responsive`, etc.).
- `wp-background-processing` (deliciousbrains) is bundled at a pinned dev commit; verify it's a version that fixed the WP 6.x `wp_unslash`/async-request `nonce` and batch-locking issues before shipping.

---

## 5. Technical Debt (with locations)

| Item | Severity | Location |
|---|---|---|
| `combine_google_fonts`/`use_bunny_fonts`/`swap_google_fonts_display` read but never defined in defaults, sanitizer, or UI → PHP 8 warnings + dead `combine_google_fonts()` path | high | `FileOptimizer.php:142,205,638` vs `utils.php:48-140` |
| Font-cache clear targets wrong dir (`cache/fonts/` vs actual `cache/swiftpress/fonts/`) → clear silently no-ops | high | `dashboard.php:694`, `CLI.php:120` vs `FontOptimizer.php:136` |
| `sanitize_options()` dereferences ~30 keys without `isset()`/defaults merge; breaks on import/partial input | medium | `dashboard.php:283-399` |
| Import reads `$_FILES['import_file']` with no `isset`/error/type/size checks; `file_get_contents(tmp_name)` then `json_decode` with no validation | medium | `dashboard.php:182-188` |
| Critical CSS + Remove-Unused-CSS are UI checkboxes and have "generation started" notices, but **no generation logic exists** (only flag checks). Dead/stub premium features. | medium | `settings-page.php:203-212`, `dashboard.php:456-459`, `CLI.php:264`; no generator class present |
| Plugin version (`1.0.0`) vs DB version (`3.8`) vs migration history mismatch; leftover "Powered Cache"→"SwiftPress" rename debris | medium | `swiftpress.php:29-30`, `Install.php` |
| `Optimizer\CSS`/`JS` "unset-all-props + magic-proxy" pattern is fragile vs WP core changes | medium | `CSS.php:31-47`, `JS.php:34-50` |
| `file-optimizer.php` **redefines** `SWIFTPRESS_FO_CACHE_DIR` with a different value than `swiftpress.php` (`CONCAT_FILES_ROOT.../cache/min/` vs `SWIFTPRESS_CACHE_DIR.'min/'`) and hardcodes `SWIFTPRESS_FO_DEBUG=false`; two sources of truth for the same constant | medium | `file-optimizer.php:76-78` vs `swiftpress.php:43-45` |
| `get_client_ip()` returns `null` (no explicit return) when neither header set; trusts XFF | low | `utils.php:847-855` |
| Inconsistent nonce input hygiene (`$_GET['_wpnonce']` unslashed in 5 handlers) | low | see §3.4 |
| Heavy `phpcs:disable`/`phpcs:ignore` usage masks real lint signal (escaping, input validation, silenced errors) across `Config.php`, `dashboard.php`, drop-in, file optimizer | low | many files (file-level disables) |
| `download_rewrite_config` only checks `! empty($_GET['server'])` then passes to `download_rewrite_rules` which compares against `'apache'`/`'nginx'` — fine, but silently exits with empty body for unknown server | low | `dashboard.php:607-625`, `Config.php:594-617` |
| `set_comment_cookie` writes `swiftpress_commented_posts[<id>]` cookie unsigned; drop-in trusts it to skip cache for commented posts (cache-bypass DoS-lite by setting cookie) | low | `AdvancedCache.php:378-382`, `page-cache.php:134-141` |
| Legacy/deprecated methods retained (`Config::htaccess_rules` deprecated 2.5, `AdvancedCache::get_accepted_query_strings`, `purge_post_on_comment_status_change`) | low | `Config.php:329`, `AdvancedCache.php:296,613` |

---

## 6. AI Integration Hooks (exact functions/places)

The plugin's singleton+filter design gives clean attachment points. Concrete targets:

1. **`Admin\Dashboard\run_diagnostic()` — `dashboard.php:742-829`.** Already an AJAX endpoint (`wp_ajax_swiftpress_run_diagnostic`) returning a `$checks[]` array (config writable, cache dir, htaccess, advanced-cache). This is the natural home for an **"AI Performance Advisor"**: after the mechanical checks, append AI-generated, prioritized recommendations (e.g. "enable js_delay; your homepage ships 14 render-blocking scripts"). It already has nonce+cap, JSON response plumbing, and a settings array in hand.
2. **`Admin\Dashboard\process_form_submit()` / `sanitize_options()` — `dashboard.php:123-274` / `283-399`.** The single settings choke point. Add an AI pass here (behind a new `swiftpress_form_action` like `ai_optimize` or a filter on `swiftpress_sanitized_options`, line 398) that proposes/auto-applies a tuned config from site characteristics. The existing `swiftpress_settings_saved` action (256) and `swiftpress_sanitized_options` filter (386-398) are ideal seams to record/learn from admin choices.
3. **Critical CSS generation — currently a stub.** `critical_css` (`settings-page.php:203`) and the `generate_critical`/`generate_critical_network` notices (`dashboard.php:456-457`) exist with **no generator**. This is the single highest-value AI slot: implement Critical/Above-the-fold CSS generation (LLM- or headless-render-assisted) as the missing `generate_critical` handler. Same for **Remove Unused CSS** (`remove_unused_css`, `ucss_safelist`/`ucss_excluded_files`) → `generate_ucss` (`dashboard.php:458-459`): an AI/coverage-driven UCSS engine.
4. **`FileOptimizer::process_buffer()` — `FileOptimizer.php:187-194`** and its sub-steps (`maybe_minify_html`, `maybe_defer_inline_scripts`, `maybe_delay_scripts`). The full HTML buffer is in hand here. AI hooks: **smart defer/delay classification** (replace the brittle regex heuristics in `should_defer_script` at `FileOptimizer.php:908-930` and `maybe_delay_scripts` at 694-852 with a learned classifier that decides which scripts are safe to defer/delay per theme), and **auto-exclusion suggestions** when a script breaks.
5. **`Optimizer\Helper::is_excluded_js/css`, `is_defer_excluded`, `is_delay_excluded` — `Helper.php:145-216`** and the `swiftpress_fo_*_do_concat` / `swiftpress_defer_exclusions` / `swiftpress_delay_exclusions` filters. An AI module can **auto-populate exclusion lists** (the textareas at `settings-page.php:244-273`) by analyzing console errors / dependency graphs, feeding results through these filters.
6. **`SitemapPreloader::get_preload_stats()` / `manual_refresh()` — exposed via `ajax_preload_status` (`dashboard.php:726`) and CLI.** AI can prioritize the preload **queue order** (predict high-traffic URLs to warm first) by hooking the URL-storage step and the batch cron.
7. **`FontOptimizer::print_preload_hints()` / `swiftpress_font_preload_count` filter — `FontOptimizer.php:487-518`.** AI can choose **which** fonts are truly above-the-fold (vs the current "first 2") per template.
8. **`sanitize_css()` — `utils.php:1059-1067`** is where `critical_css_appended_content`/`critical_css_fallback` land; an AI Critical-CSS generator should write through it.
9. **CLI surface (`CLI.php`).** Add `wp swiftpress optimize` (AI auto-tune) and `wp swiftpress analyze` commands alongside the existing `status`/`flush`/`preload`; the `status()` aggregation (`CLI.php:252-309`) is a ready feature-state snapshot to feed a model.

Recommended new seam for the revamp: a dedicated `AI` module (`AI::factory()`) with its own nonce-protected AJAX endpoints, plus a `swiftpress_ai_recommendations` filter, so AI logic is decoupled from the cache core and can be gated/licensed independently.

---

## 7. How nginx is handled today

`Config::nginx_rules()` (`Config.php:479-585`) **generates** an nginx config string (it does not write server files — nginx can't be auto-reconfigured from PHP). The admin downloads it via `admin_post_swiftpress_download_rewrite_settings` → `download_rewrite_config()` (`dashboard.php:607-625`, nonce `swiftpress_download_rewrite` + `can_control_all_settings`) → `Config::download_rewrite_rules('nginx')` (`Config.php:594-617`) which streams it as `swiftpress.conf`, and pastes it into their server block.

What the generated config does:
- Sets `$cache_uri`/`$pc_ssl`/`$pc_enc`/`$pc_ua`; forces `null cache` for POST and any non-empty query string (so dynamic requests skip cache).
- Optional CORS `add_header` for static assets when `enable_cdn` is on; browser-cache `expires 6M` for static types when `swiftpress_browser_cache` is true.
- File-optimizer `location /_static/ { fastcgi_pass … SCRIPT_FILENAME …file-optimizer.php }` when `rewrite_file_optimizer` is on (`Config.php:520-528`).
- Sets `$pc_ssl=-https` on HTTPS; `null cache` for wp-admin/login/feed/sitemap/etc.; `null cache` for rejected user agents (`AdvancedCache::get_rejected_user_agents()`) and for logged-in/commenter cookies (`get_rejected_cookies()`); `$pc_ua=-mobile` on `X-WAP-Profile`.
- The serving rule: `location / { try_files /wp-content/cache/swiftpress/$http_host/$cache_uri/index${pc_ssl}${pc_ua}.html[.gz] $uri $uri/ /index.php?$args; }` (`Config.php:565-577`), gated by the `swiftpress_mod_rewrite` filter, plus a trailing-slash `rewrite` when the permalink structure uses one.

Assessment & how to keep/improve it:
- The cache-path/key here is **kept in lockstep with the htaccess/PHP path** (same `swiftpress/$host/$uri/index{-https}{-mobile}.html[.gz]` shape) — that consistency is the thing to preserve in the rewrite. It is mirrored across `Config::nginx_rules`, `Htaccess::rewrite_rules`, and `swiftpress_index_file`/`swiftpress_serve_cache` in the drop-in; **a single source of truth for the cache-key template would eliminate drift risk** (today it's hand-duplicated in three places).
- Gaps vs the htaccess path: nginx config omits a **mobile UA `$pc_ua` derivation from `$http_user_agent`** (it only sets `-mobile` from `X-WAP-Profile`, line 555), so `cache_mobile_separate_file` won't actually vary the nginx cache by mobile the way Apache does (Apache derives it from the UA regex, `Htaccess.php:342-347`). It also omits gzip `$pc_enc` wiring into `try_files` even though `$pc_enc` is declared. Fix both in the revamp.
- The `fastcgi_pass unix:/var/run/fastcgi.sock` (line 524) is a **hardcoded socket path** that won't match most stacks (php-fpm pools vary). Make it a placeholder/setting.
- Improvement: ship a **copy-to-clipboard + validated snippet in the new UI**, generate per-detected-stack variants (php-fpm socket vs tcp), and consider an "nginx test" diagnostic. Long term, a small `map`/`if`-free config using `try_files` only is more nginx-idiomatic than the `if`-heavy output (nginx "if is evil") — worth modernizing.

---

## 8. Current Admin UI/UX — Blunt Assessment

**It is a stock, generic WordPress admin page — intentionally so (the file header says "native WordPress admin CSS classes only … No external frameworks").** `settings-page.php` is a single `<form>` of `.wrap` / `.form-table` / `<input type=checkbox>` / `<textarea class="large-text code">` / `submit_button()`. There is essentially no design: collapsible sections are bare `<h2>` headers with a `dashicons-arrow-down-alt2` caret, the "header" is an `<h1>` + two `.button` elements, and the only custom chrome is a hand-rolled progress bar (`.sp-progress-bar-*`) for preloading. It looks like every other utility plugin's settings screen circa 2015.

Specifics:
- **Information architecture is flat:** five accordion sections (Page Cache, File Optimization, Font Optimization, Cache Preloader, Advanced) on one page; "Advanced" is a dumping ground of 9 textareas (rejected UA/cookies/URIs, vary cookies, query strings, prefetch/preconnect) with zero guidance, validation, or examples beyond placeholders.
- **No status/overview surface:** there's no dashboard, no cache-size/hit-rate display, no "what is on" summary (the CLI `status` command has more situational awareness than the GUI). The diagnostic endpoint (`run_diagnostic`) exists but isn't surfaced as a polished health panel.
- **Feedback is classic `admin_notices`** keyed off `?sp_action=` (`dashboard.php:435-505`) — functional, unstyled.
- **A markup bug:** the Font Optimization section's `<div class="sp-section-body">` is not closed before the Cache Preloader section begins (the `<!-- /font-optimization -->` comment at `settings-page.php:357` closes only the outer div), so the DOM nesting is off — a sign the template was assembled quickly.
- **Dead controls:** Critical CSS / Remove Unused CSS checkboxes render but do nothing (no backend), which is a UX trust problem.

**Verdict:** maximally generic, no brand identity, no modern interaction model — exactly the stated revamp target. It is clean and accessible (proper labels, `role="presentation"` tables), which is a fine *baseline* to rebuild on, but it reads as a developer placeholder, not a product. The revamp should introduce a real dashboard (status + metrics), progressive disclosure with inline help, a proper design system (the user's stated taste runs strongly anti-default — see frontend-design skills), and surface the diagnostic + AI advisor prominently.

---

## 9. Prioritized Recommendations for the Revamp

**P0 — correctness/blockers**
1. Fix the **undefined settings keys** (`combine_google_fonts`, `use_bunny_fonts`, `swap_google_fonts_display`): add to `get_settings()` defaults + `sanitize_options()` + the UI, or remove the dead `combine_google_fonts()` path. (`FileOptimizer.php:142,205,638`)
2. Fix the **font-cache directory mismatch** so "Clear Font Cache" (AJAX + CLI) targets `cache/swiftpress/fonts/`. (`dashboard.php:694`, `CLI.php:120`)
3. Harden **`sanitize_options()`** to merge over defaults / use `?? ''`, and **validate the import upload** (`error`, size, type, `is_array(json_decode)`). (`dashboard.php:182-188,283-399`)
4. Decide on **Critical CSS / UCSS**: either implement the missing generators (prime AI feature) or remove the dead checkboxes/notices to stop shipping non-functional toggles.

**P1 — security/robustness hardening**
5. Sanitize `get_client_ip()` (`FILTER_VALIDATE_IP`, strip CRLF) and stop trusting raw XFF. (`utils.php:847`)
6. Normalize nonce reads to `wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), … )` across all handlers. (§3.4)
7. In the file optimizer, guard all `$_SERVER`/`$args[0]` reads and reject empty `??-` payloads before `base64_decode`. (`file-optimizer.php`)
8. Set explicit `simplexml_load_string(..., LIBXML_NONET)` and verified TLS (`sslverify=>true`) for sitemap fetches; validate the admin-supplied sitemap host. (`SitemapPreloader.php:314,379`)
9. Sign/HMAC the `swiftpress_commented_posts` cookie or stop using it as a cache-bypass signal. (`AdvancedCache.php:381`)

**P2 — architecture & maintainability**
10. **Single source of truth for the cache-key template** shared by `nginx_rules`, `Htaccess::rewrite_rules`, and the drop-in (`swiftpress_index_file`). Eliminates the mobile/gzip drift between Apache and nginx (§7).
11. Resolve the **duplicate `SWIFTPRESS_FO_CACHE_DIR` definition** and the version/DB-version mismatch; remove deprecated methods. (`file-optimizer.php:76-78`, `swiftpress.php:29-30`)
12. Replace the **WP_Styles/WP_Scripts unset+magic-proxy** combine implementation with a more robust approach (or pin tests to WP 6.7/6.8 and guard `$parsed['path']` reads). (`CSS.php`, `JS.php`)
13. Bump `Requires PHP` to 8.1, add a **PHP 8.1/8.2/8.3 + WP 6.7/6.8 CI matrix**; the heavy `phpcs:ignore` usage is hiding exactly the classes of issues above — re-enable rules incrementally.

**P3 — product/UX & AI**
14. Rebuild the admin as a real product UI: a **status dashboard** (cache size, hit/miss, feature state — reuse `CLI::status`/`run_diagnostic` data), progressive disclosure with inline help on the Advanced textareas, copy-to-clipboard validated nginx/Apache snippets with per-stack variants, and a polished health panel.
15. Land the **AI module** (`AI::factory()`, nonce-protected AJAX, `swiftpress_ai_recommendations` filter) attaching at the seams in §6 — Performance Advisor on `run_diagnostic`, auto-tune on the settings save flow, AI Critical-CSS/UCSS generators, and smart defer/delay classification replacing the regex heuristics.

---

### Appendix — files read for this audit
`swiftpress.php`, `includes/constants.php`, `includes/core.php`, `includes/utils.php`, `includes/file-optimizer.php`, `includes/dropins/page-cache.php`, `includes/admin/dashboard.php`, `includes/admin/notices.php`, `includes/admin/partials/settings-page.php`, `includes/classes/{Config,AdvancedCache,Htaccess,FileOptimizer,FontOptimizer,Install,CLI}.php`, `includes/classes/Optimizer/{CSS,JS,Helper}.php`, `includes/classes/Async/SitemapPreloader.php`, `includes/classes/Extensions/Cloudflare/{API,Cloudflare}.php`, `composer.json`, `readme.txt`. Vendored libraries under `includes/classes/Dependencies/*` and `includes/package/*` were treated as third-party and not audited line-by-line.
