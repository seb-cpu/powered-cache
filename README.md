# AICache

AI-assisted WordPress performance & caching by [Webs.ie](https://webs.ie).

Page cache (PHP drop-in + nginx/.htaccess rules), CSS/JS minify·combine·defer·delay,
Critical CSS / Remove Unused CSS, self-hosted Google Fonts with layout-shift-safe
loading, WebP image optimization, self-hosted GA/GTM/Meta Pixel, database cleanup,
WP-admin speed switches, bloat control — and an **AI Performance Diagnostic** that
reads your site's real metrics, explains the findings in plain language and applies
validated, reversible fixes with one click (BYO OpenRouter key, spend-capped,
no SaaS backend).

## Install

Upload the plugin folder to `wp-content/plugins/` and activate. Updates are served
from this repository's tagged releases (semver `vX.Y.Z`) via the built-in GitHub
updater. WP 5.7+ (tested up to 7.0), PHP 8.0+, nginx and Apache.

## Development

```bash
npm install
npm run build        # 10up-toolkit / webpack
```

WP-CLI: `wp swiftpress flush|status|preload|optimize-images`.

## Credits & license

GPL-2.0-or-later. Fork of [Powered Cache](https://github.com/skopco/powered-cache)
and [Simple Cache](https://github.com/tlovett1/simple-cache) (Taylor Lovett / 10up);
original copyright notices preserved. See `readme.txt` for the full feature list,
changelog and external-services disclosure.
