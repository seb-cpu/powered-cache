# SwiftPress Admin Revamp — Vision: **MISSION CONTROL**

> *"Not a settings page. A flight deck for your site's speed."*

A complete redesign proposal for the SwiftPress admin, grounded in the real codebase
(`includes/utils.php` settings, `dashboard.php` AJAX surface, `Config::nginx_rules()`,
`run_diagnostic()`, `SitemapPreloader::get_preload_stats()`) and the competitor +
AI research in `F:/Claude/powered-cache-revamp/research/`.

This is **one of three** competing visions. Mission Control deliberately stakes out the
**dark-first, telemetry-instrument, lightweight-vanilla** corner of the design space so it
cannot be confused with the other two proposals (which target a light editorial/SaaS
aesthetic and a heavier React component system respectively). Where they go bright,
component-rich, and framework-backed, Mission Control goes **dark, instrument-grade, and
near-zero-JS** — the brand argument being that *a performance plugin's own admin must be the
fastest screen in wp-admin.*

---

## 1. Aesthetic direction + the ONE signature move

### 1.1 Direction

**Industrial / utilitarian, elevated to premium, with restrained retro-futuristic HUD accents.**
The reference points are not "SaaS dashboard" — they are the **glass cockpit** of a modern
aircraft, a **Teenage Engineering** device, a **Braun measuring instrument**, and a
**Bloomberg terminal**. The admin is a *cockpit for site speed*: dark, dense, status-encoded,
every number readable at a glance, nothing decorative that isn't also informative.

Concretely that means:

- **Dark-first** (a genuine instrument-panel charcoal, not "dark-mode-as-afterthought black"). A light "Daylight" theme ships too, but the design is *authored* dark and the marketing screenshots are dark.
- **Monospace for every datum.** If it's a number, a key, a path, a header value, a status code — it's set in mono. Prose is a precise neutral sans. This single rule does enormous identity work and is the opposite of the rounded, friendly SaaS look the other visions can take.
- **Status encoded as light.** Green / amber / red are *the* accent system, used the way a cockpit uses annunciator lamps — sparingly, meaningfully, and with a faint purposeful glow only on live/active elements. Idle UI does not glow.
- **Hairline structure.** 1px hairline borders and a fine grid, not big drop-shadowed cards. Elevation is communicated by surface lightness and a single tight shadow, not by floating.
- **Cut-corner / notched shape language** (see §5) instead of the default `rounded-xl` everywhere — a small, consistent "panel notch" that reads as instrument-bezel hardware.

### 1.2 The signature move

**THE SPEEDOMETER — a live performance-telemetry instrument as the heart of the dashboard.**

The Performance Score is **not** a number-in-a-circle (WP Rocket's "Rocket Insights score"
is a flat grade; NitroPack shows card widgets). In Mission Control the score is rendered as a
**precise radial gauge — a speedometer** — drawn as crisp SVG with a graduated tick scale
(0–100), a needle that **sweeps from 0 on every load** with eased motion, a colored arc that
fills to the score, and a faint focused glow at the needle tip. The gauge sits **ringed by six
live readouts** in monospace — **LCP · CLS · INP · TTFB · CACHE HIT-RATE · CACHE SIZE** — each
a mini-instrument with its own threshold coloring and sparkline.

The whole assembly is **"THE INSTRUMENT CLUSTER."** It is the first thing you see, it animates
on entry, it updates live during a diagnostic run (needle re-sweeps to the new score, deltas
flash), and it is unmistakably *this product*. No competitor in the dossiers renders speed as an
instrument; everyone else renders it as a badge or a card. That gap is the whole point.

> A reviewer should be able to glance at one screenshot of the cluster — dark panel, swept
> needle, six mono readouts ringing it, a single green annunciator — and know instantly which
> of the three visions this is. That is the bar for "non-interchangeable."

---

## 2. Design tokens — DARK theme ("Flight Deck")

Authored as CSS custom properties under a single root scope (`.swiftpress-app`) so it can never
leak into the rest of wp-admin. Light theme overrides live under `.swiftpress-app[data-theme="day"]`
(abbreviated at the end). Everything below is real, copy-pasteable, and internally consistent.

```css
/* ============================================================
   SwiftPress · MISSION CONTROL · Design Tokens — DARK ("Flight Deck")
   Scope everything under .swiftpress-app so wp-admin is untouched.
   ============================================================ */
.swiftpress-app {

  /* ---- COLOR · background layers (darkest → lightest) -------- */
  /* Cool, slightly blue-black charcoal — "instrument panel", not pure black. */
  --sp-bg-void:        #0a0c10;  /* page backdrop behind panels            */
  --sp-bg-base:        #0e1116;  /* app canvas                             */
  --sp-surface-1:      #141821;  /* primary panel surface                  */
  --sp-surface-2:      #1b212c;  /* raised surface (gauge well, inputs)    */
  --sp-surface-3:      #232b38;  /* hover / active row, popovers           */
  --sp-surface-inset:  #0b0e13;  /* recessed wells (readout cutouts)       */

  /* ---- COLOR · hairlines & borders -------------------------- */
  --sp-line:           #2a3340;  /* default hairline                       */
  --sp-line-strong:    #3a4555;  /* emphasized divider / focus ring base   */
  --sp-line-faint:     #1c2530;  /* barely-there grid lines                */

  /* ---- COLOR · text tiers ----------------------------------- */
  --sp-text-hi:        #e8edf4;  /* primary text, headings                 */
  --sp-text:           #b8c2cf;  /* body                                   */
  --sp-text-mute:      #7d8a9a;  /* secondary / labels                     */
  --sp-text-dim:       #54606e;  /* captions, units, disabled              */
  --sp-text-onaccent:  #08120a;  /* text on a solid accent fill            */

  /* ---- COLOR · the accent (signal cyan / "phosphor") --------- */
  /* The brand light. Used for the needle, active states, focus. */
  --sp-accent:         #34e1c4;  /* phosphor cyan-green                    */
  --sp-accent-bright:  #5cf0d8;  /* hover / peak                           */
  --sp-accent-deep:    #14b59a;  /* pressed / arc base                     */
  --sp-accent-glow:    rgba(52, 225, 196, 0.45); /* ambient glow color     */

  /* ---- COLOR · status annunciators -------------------------- */
  --sp-good:           #3fd07f;  /* green  · pass / healthy                */
  --sp-good-dim:       rgba(63, 208, 127, 0.14);
  --sp-warn:           #f0b429;  /* amber  · needs improvement             */
  --sp-warn-dim:       rgba(240, 180, 41, 0.14);
  --sp-crit:           #ff5c5c;  /* red    · fail / error                  */
  --sp-crit-dim:       rgba(255, 92, 92, 0.14);
  --sp-info:           #4aa8ff;  /* blue   · informational / links         */
  --sp-info-dim:       rgba(74, 168, 255, 0.14);

  /* ---- COLOR · data-viz ramp (gauge arc, sparklines) -------- */
  --sp-ramp-0:         #ff5c5c;  /* 0–49   poor                            */
  --sp-ramp-1:         #f0b429;  /* 50–89  okay                            */
  --sp-ramp-2:         #3fd07f;  /* 90–100 good (Lighthouse banding)       */

  /* ---- TYPE · families -------------------------------------- */
  /* Display: geometric, technical, has CHARACTER (not Inter/Roboto). */
  --sp-font-display: "Space Grotesk", "Chakra Petch", system-ui, sans-serif;
  /* Body: a precise neutral grotesk for prose & labels.        */
  --sp-font-body:    "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
  /* Mono: THE data face — every number, key, path, header.     */
  --sp-font-mono:    "JetBrains Mono", "IBM Plex Mono", ui-monospace, monospace;

  /* ---- TYPE · scale (1.250 major-third, 16px base) ---------- */
  --sp-text-2xs:  0.6875rem;  /* 11px · units, micro-labels             */
  --sp-text-xs:   0.75rem;    /* 12px · captions, table meta            */
  --sp-text-sm:   0.8125rem;  /* 13px · dense body, controls            */
  --sp-text-md:   0.9375rem;  /* 15px · default body                    */
  --sp-text-lg:   1.125rem;   /* 18px · panel titles                    */
  --sp-text-xl:   1.5rem;     /* 24px · section headers                 */
  --sp-text-2xl:  2.125rem;   /* 34px · readout values                  */
  --sp-text-3xl:  3.25rem;    /* 52px · gauge score numeral             */

  --sp-tracking-tight:  -0.01em;
  --sp-tracking-wide:    0.08em;  /* mono labels / annunciators (caps)  */
  --sp-tracking-data:    0.02em;  /* tabular numbers                    */
  --sp-leading-tight:  1.15;
  --sp-leading-body:   1.55;

  /* ---- SPACING · 4px rhythm --------------------------------- */
  --sp-space-1:  0.25rem;  /*  4px */
  --sp-space-2:  0.5rem;   /*  8px */
  --sp-space-3:  0.75rem;  /* 12px */
  --sp-space-4:  1rem;     /* 16px */
  --sp-space-5:  1.5rem;   /* 24px */
  --sp-space-6:  2rem;     /* 32px */
  --sp-space-7:  3rem;     /* 48px */
  --sp-space-8:  4rem;     /* 64px */

  /* ---- RADIUS · restrained; the "notch" is the real shape --- */
  --sp-radius-xs:   2px;   /* inputs, chips                          */
  --sp-radius-sm:   4px;   /* buttons                                */
  --sp-radius-md:   6px;   /* small panels                           */
  --sp-radius-lg:   8px;   /* large panels (then corner-notched)     */
  --sp-radius-pill: 999px; /* status dots, toggles                   */
  --sp-notch:       10px;  /* clip-path corner cut — the bezel motif */

  /* ---- ELEVATION · tight, dark, single-source -------------- */
  --sp-shadow-1: 0 1px 0 0 rgba(255,255,255,0.02) inset,
                 0 1px 2px rgba(0,0,0,0.40);
  --sp-shadow-2: 0 1px 0 0 rgba(255,255,255,0.03) inset,
                 0 4px 14px rgba(0,0,0,0.45);
  --sp-shadow-pop: 0 12px 40px rgba(0,0,0,0.55);
  /* Purposeful glow — ONLY on live/active instruments.         */
  --sp-glow-accent: 0 0 0 1px rgba(52,225,196,0.30),
                    0 0 18px -2px var(--sp-accent-glow);
  --sp-glow-good:   0 0 14px -3px rgba(63,208,127,0.55);
  --sp-glow-crit:   0 0 14px -3px rgba(255,92,92,0.55);

  /* ---- MOTION ----------------------------------------------- */
  --sp-ease-out:    cubic-bezier(0.16, 1, 0.3, 1);   /* reveals     */
  --sp-ease-inout:  cubic-bezier(0.65, 0, 0.35, 1);  /* needle      */
  --sp-dur-fast:    120ms;
  --sp-dur-med:     280ms;
  --sp-dur-slow:    560ms;
  --sp-dur-sweep:   1100ms;   /* gauge needle sweep on load          */

  /* ---- GRID ------------------------------------------------- */
  --sp-maxw:        1320px;   /* content max width                   */
  --sp-rail:        232px;    /* left nav rail width                  */
  --sp-gutter:      var(--sp-space-5);
}

/* ---- LIGHT theme ("Daylight") — overrides only ------------- */
.swiftpress-app[data-theme="day"] {
  --sp-bg-void:       #e7eaef;
  --sp-bg-base:       #eef1f5;
  --sp-surface-1:     #ffffff;
  --sp-surface-2:     #f6f8fb;
  --sp-surface-3:     #eef2f7;
  --sp-surface-inset: #f0f3f7;
  --sp-line:          #d7dde6;
  --sp-line-strong:   #c2cad6;
  --sp-line-faint:    #e6ebf1;
  --sp-text-hi:       #0e1116;
  --sp-text:          #2c3744;
  --sp-text-mute:     #5a6776;
  --sp-text-dim:      #8a96a4;
  --sp-accent:        #0fae93;   /* darker phosphor for contrast on white */
  --sp-accent-bright: #14c4a6;
  --sp-accent-deep:   #0a8c76;
  --sp-shadow-1: 0 1px 2px rgba(16,24,40,0.06);
  --sp-shadow-2: 0 4px 16px rgba(16,24,40,0.10);
  --sp-glow-accent: 0 0 0 1px rgba(15,174,147,0.25); /* no bloom in daylight */
}

/* Respect reduced-motion: kill sweeps & glows, keep state changes. */
@media (prefers-reduced-motion: reduce) {
  .swiftpress-app * { animation: none !important; transition-duration: 1ms !important; }
}
```

**Font rationale (real, open-source/Google faces):**

| Role | Face | Why this one (and not Inter/Roboto) |
|---|---|---|
| **Display** | **Space Grotesk** (OFL, Google Fonts) | Slightly mechanical, square-ish grotesk with real personality in the `a`/`g`/`G`; reads "technical instrument," not "generic SaaS." Alt/companion **Chakra Petch** for an even more HUD/cockpit feel on hero numerals if we want to push the retro-futuristic dial. |
| **Body** | **IBM Plex Sans** (OFL) | A precise, engineered neutral with IBM's lineage — pairs naturally with Plex Mono, legible at 13px in dense tables, never twee. The "precise neutral" the brief asks for. |
| **Mono** | **JetBrains Mono** (OFL) | The data face. Tall x-height, unambiguous `0/O`, `1/l/I`, generous tabular spacing — perfect for LCP/CLS values, setting keys, header dumps, the nginx snippet. This is what makes "monospace for data" feel intentional rather than lazy. |

All three are self-hosted through SwiftPress's **own** `FontOptimizer` (dogfooding: the
performance plugin self-hosts its admin fonts with `font-display: swap` and preloads the two
weights the cluster needs). No Google CDN call, no FOIT.

---

## 3. Information architecture

### 3.1 Why a real IA (and not five accordions)

The current UI is one page, five `<h2>` accordions, with **Cloudflare, image optimizer,
Critical CSS, self-host GA/FB, Heartbeat, Varnish, LCP, prefetch/preconnect all either buried
or entirely absent** (the audit confirms many are in `get_settings()` defaults but never
surfaced). NitroPack's research shows the cardinal sin is a **split brain** where status lives
somewhere you can't see it. Mission Control fixes both: **one cockpit, everything in wp-admin,
a left nav rail, and a status surface that is always the home screen.**

### 3.2 Navigation rail (left, fixed, icon + label)

A slim 232px rail, dark, with a hairline right border. Sections are *systems*, not a settings
dump. Each rail item carries a tiny live status dot (green/amber/red/grey-off) so the rail
itself is a mini status board.

```
┌──────────────────────┐
│  ◢ SwiftPress        │   ← wordmark, mono "v1.0.0" + "nginx" stack pill
│  ──────────────────  │
│  ◉ DASHBOARD         │   ← the Instrument Cluster (home)
│  ◉ CACHE             │   ← page cache, mobile, logged-in, expiration, exclusions
│  ◉ OPTIMIZE          │   ← CSS/JS minify·combine·defer·delay, Critical CSS, RUCSS
│  ◉ MEDIA & FONTS     │   ← image optimizer+format, fonts (self-host/preload/swap)
│  ◉ PRELOAD           │   ← preloader + sitemap + live progress (reuses preload AJAX)
│  ◉ DELIVERY          │   ← CDN/Cloudflare, Varnish, prefetch/preconnect, link-prefetch
│  ◉ INTEGRATIONS      │   ← tracking self-host (GA/FB), Heartbeat, detected-plugin compat
│  ◉ AI DIAGNOSTIC     │   ← Explain & Fix (headline) + NL config, glowing when key set
│  ◉ TOOLS             │   ← nginx/Apache generator, import/export, dev mode, diagnostics
│  ──────────────────  │
│  ⚙ URL INVENTORY     │   ← per-URL cache table (status/age/size/score)
│  ──────────────────  │
│  ◐ THEME  ⏻ FLUSH ALL│   ← theme toggle (Flight Deck/Daylight) + global purge
└──────────────────────┘
```

**Top bar** (spans content area, sticky): breadcrumb / current system name · global
**Cache armed/disarmed** master state · **Last audit: 2h ago** · **[ RUN DIAGNOSTIC ]** primary
button (always reachable) · a thin live **annunciator strip** (Cache ● Preload ● CDN ● AI).

This maps cleanly onto the real settings groups in `utils.php` — nothing is invented, everything
orphaned gets a home:

| Orphaned/buried today | New home |
|---|---|
| `enable_cdn`, `enable_cloudflare`, `cloudflare_*`, `enable_varnish`, `varnish_ip` | **DELIVERY** |
| `prefetch_dns`, `preconnect_resource`, `prefetch_links` | **DELIVERY** (Resource Hints) |
| `enable_image_optimization`, `image_optimizer_preferred_format`, `add_missing_image_dimensions` | **MEDIA & FONTS** |
| `critical_css*`, `remove_unused_css`, `ucss_*` | **OPTIMIZE** (CSS Delivery) |
| `enable_google_tracking`, `enable_fb_tracking` | **INTEGRATIONS** (Self-Hosted Tracking) |
| `enable_heartbeat`, `heartbeat_*` | **INTEGRATIONS** (Heartbeat) |
| `enable_lcp_optimization` | **OPTIMIZE** (LCP) + surfaced as an AI recommendation |
| `async_cache_cleaning`, `dev_mode`, `cache_footprint`, import/export | **TOOLS** |

---

## 4. The Dashboard HOME screen

The home screen is **THE INSTRUMENT CLUSTER**. Above the fold: the speedometer + six ringed
readouts. Below: a two-column "systems status + recommendations" band, then a "what we already
fixed" strip for the zero-config moment. Realistic values throughout — never lorem.

### 4.1 ASCII wireframe (dark)

```
╔══════════════════════════════════════════════════════════════════════════════════╗
║  DASHBOARD                       Cache ● ARMED   Last audit 2h ago   [ RUN DIAGNOSTIC ]║
╠══════════════════════════════════════════════════════════════════════════════════╣
║                                                                                    ║
║   ┌─ INSTRUMENT CLUSTER ──────────────────────────────────────────────────────┐  ║
║   │                                                                            │  ║
║   │     LCP            CLS                    ╭───────────╮            INP      │  ║
║   │   ┌───────┐     ┌───────┐                ╱   86      ╲          ┌───────┐  │  ║
║   │   │1.9 s ▲│     │0.04  ●│               │   ◜◜◜▟▛◝◝   │         │142ms ●│  │  ║
║   │   │ GOOD  │     │ GOOD  │              │  · PERF SCORE ·│        │ GOOD  │  │  ║
║   │   │▁▂▃▂▁▂ │     │▁▁▂▁▁▁ │               │   ╲  needle ╱ │        │▂▃▄▃▂▃ │  │  ║
║   │   └───────┘     └───────┘                ╲ 0 ······ 100╱         └───────┘  │  ║
║   │                                           ╰───────────╯                     │  ║
║   │     TTFB                              ┌── GRADE: GOOD ──┐          CACHE     │  ║
║   │   ┌───────┐                           │ field · 28d CrUX │       ┌───────┐  │  ║
║   │   │ 210ms │       HIT-RATE  94.2% ◉   └──────────────────┘       │ 318 MB│  │  ║
║   │   │ GOOD  │       ████████████░  live                            │1,204 f│  │  ║
║   │   └───────┘                                                      └───────┘  │  ║
║   └────────────────────────────────────────────────────────────────────────────┘  ║
║                                                                                    ║
║   ┌─ SYSTEMS ───────────────────────┐   ┌─ RECOMMENDATIONS (3) ────────────────┐  ║
║   │ ● Page Cache .......... ARMED   │   │ ▲ Enable LCP optimization            │  ║
║   │ ● File Optimize ....... PARTIAL │   │   hero image not preloaded · +0.4s   │  ║
║   │ ● Fonts (self-host) ... ARMED   │   │              [ explain ]  [ apply → ]│  ║
║   │ ● Preload ............. 71% ▸   │   │ ▲ 6 render-blocking scripts          │  ║
║   │ ○ CDN / Cloudflare .... OFF     │   │   defer JS · est. −320ms TBT         │  ║
║   │ ● Object Cache: Redis ✓ │       │   │              [ explain ]  [ apply → ]│  ║
║   │ ○ Critical CSS ........ OFF     │   │ ◦ 14 unused-CSS rules · RUCSS        │  ║
║   │   server: nginx · PHP 8.3       │   │              [ explain ]  [ apply → ]│  ║
║   └─────────────────────────────────┘   └──────────────────────────────────────┘  ║
║                                                                                    ║
║   ┌─ ZERO-CONFIG: WHAT SWIFTPRESS ALREADY HANDLED ─────────────────────────────┐  ║
║   │ ✓ Page cache armed   ✓ Gzip on   ✓ Mobile cache   ✓ Emoji scripts removed  │  ║
║   │ ✓ Heartbeat throttled 60→120s    ✓ 1,204 URLs preloaded from sitemap        │  ║
║   └────────────────────────────────────────────────────────────────────────────┘  ║
╚══════════════════════════════════════════════════════════════════════════════════╝
```

### 4.2 Behavior

- **On load:** the rail items stagger in (8ms apart), the cluster panel fades up, then the
  **needle sweeps 0 → 86** over ~1.1s with `--sp-ease-inout`, the arc fills behind it, the six
  readout values count up, and sparklines draw left-to-right. One choreographed reveal, then
  stillness. (All disabled under `prefers-reduced-motion`.)
- **Live data:** HIT-RATE and CACHE SIZE poll the existing infrastructure (a thin extension of
  the `swiftpress_preload_status` pattern / a new `swiftpress_metrics` AJAX action reading cache
  dir size + the `X-SwiftPress-Cache` HIT/MISS counters). The needle is **field-data driven**
  (CrUX via PSI) with a lab fallback; the badge states which.
- **Recommendations** are produced by `run_diagnostic()` extended with the AI advisor (or the
  deterministic rules fallback when no key is set — see §5.3). Each row's `apply →` writes the
  exact `setting_key` through the existing save path.

---

## 5. How this vision surfaces each required feature

### 5.1 Performance Score widget — **the speedometer** (signature)

Covered above and implemented in §6. The single most important differentiator: **score as a
swept instrument**, ringed by **LCP/CLS/INP/TTFB/HIT-RATE/SIZE** mini-instruments, mono
everywhere, glow only on the live arc/needle. WP Rocket shows a flat "Insights" grade; NitroPack
shows generic metric cards; **nobody shows an instrument.** It animates on entry and re-sweeps
when a diagnostic finishes, so the act of optimizing is *visceral* — you watch the needle climb.

### 5.2 Optimization presets — **MODE SELECTOR (throttle quadrant)**

Presets are framed as **flight modes**, presented as a horizontal **segmented throttle** at the
top of OPTIMIZE (and offered in the setup wizard):

```
  ┌─────────┬──────────┬────────────┬───────────────┐
  │  SAFE   │ BALANCED │ AGGRESSIVE │  WOOCOMMERCE  │      ◄ current: BALANCED
  └─────────┴──────────┴────────────┴───────────────┘
   ↑ selecting a mode shows, BEFORE apply:
   • a diff of exactly which setting_keys flip (mono, +green / −red)
   • a per-setting RISK chip:  ●safe  ▲caution  ◆can-break-themes
   • one line of tradeoff prose ("Aggressive enables JS delay — test interactive widgets")
```

Each preset is a named map of the real keys (e.g. **Balanced** = `minify_css`, `combine_css`,
`js_defer`, `enable_font_optimization`, `self_host_google_fonts`, `font_display_swap`,
`enable_cache_preload`; **Aggressive** adds `js_delay`, `remove_unused_css`, `critical_css`,
`enable_lcp_optimization`; **WooCommerce** force-adds cart/checkout/my-account to `rejected_uri`
and disables page cache for those). The **risk labels are per-setting and explicit** — directly
answering the research consensus. Applying a mode routes through `sanitize_options()` →
`save_configuration()` and snapshots first, so it is undoable.

### 5.3 AI Performance Diagnostic — **"EXPLAIN & FIX" (the headline)**

A dedicated **AI DIAGNOSTIC** system, and the `[ RUN DIAGNOSTIC ]` button on every screen feeds
it. The flow visually *is* mission control: **acquire telemetry → analyze → recommend → apply →
re-acquire.**

```
┌─ AI DIAGNOSTIC ────────────────────────────────────────────────────────────┐
│  TARGETS:  ◉ homepage   ◉ /shop/   ◉ /blog/sample-post/      [ + add URL ]   │
│  MODEL:    gemini-2.5-flash-lite ▾     SPEND: $0.02 / $2.00 this month       │
│                                                       [ ⏱ RUN EXPLAIN & FIX ]│
├─────────────────────────────────────────────────────────────────────────────┤
│  ◖ ACQUIRING  ── ▮▮▮▮▮▮▮▯▯▯  PSI field+lab · settings snapshot · environment │
│                                                                             │
│  PLAIN-LANGUAGE FINDINGS                                                     │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ "Your homepage takes 3.4s to show its main image because a Google      │ │
│  │  font is render-blocking and the hero image isn't preloaded."          │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  VALIDATED CHANGE SET                          conf.   risk     evidence    │
│  ☑ self_host_google_fonts      OFF → ON        92%     ●safe    LCP 3.4s    │
│  ☑ font_preload                OFF → ON        88%     ●safe    render-block│
│  ☑ enable_lcp_optimization     OFF → ON        81%     ▲caution LCP element │
│  ☐ remove_unused_css           OFF → ON        64%     ◆breaks? 38KB unused │
│  ───────────────────────────────────────────────────────────────────────── │
│  [ APPLY SELECTED → ]   [ snapshot saved · UNDO LAST AI CHANGE ]            │
└─────────────────────────────────────────────────────────────────────────────┘
```

Design specifics that make it credible (and match the AI research exactly):

- **Server-side only, BYO OpenRouter key**, key entered in a masked field, never enqueued to JS,
  **excluded from `sp-config` drop-in + import/export** (reuse the `cloudflare_*` strip
  precedent). The key field lives in INTEGRATIONS with a clear external-service disclosure.
- The LLM may **only propose whitelisted `setting_key`s**; every proposed change is **validated
  in PHP against the real schema** before it is rendered as appliable. The UI literally shows a
  `VALIDATED CHANGE SET` — invalid suggestions never appear.
- **Confidence + risk chip + evidence metric** on every row (the table above), checkboxes per
  change, **Apply Selected** writes through the existing save path, **snapshot + one-click undo**,
  and a **re-audit** that re-sweeps the needle to prove the delta.
- **No key? The panel still works** — it falls back to the deterministic rules table and shows
  the same change set minus the prose, with a quiet "AI explanation unavailable" note. The
  feature exists for everyone; the LLM upgrades it. CSS work stays algorithmic; the LLM only
  *explains/triage*.
- **Natural-language config** is the same engine with a command input at the bottom of the
  panel ("speed it up but don't cache the members area") → returns a validated diff to confirm,
  never auto-applied.

Visually this is the most "HUD" screen — a scanline shimmer on the ACQUIRING bar, the cluster
needle live-updating, mono evidence column. It feels like running a systems check.

### 5.4 The nginx config generator — **OUR STANDOUT, treated as a hero**

The research is blunt: rivals are *weak* on nginx (NitroPack needs manual server config;
WP Rocket leans Apache). We already generate correct `try_files` rules in
`Config::nginx_rules()`. Mission Control makes this a **showpiece** in TOOLS, not a hidden
download link.

```
┌─ SERVER CONFIG · NGINX ─────────────────────────────────────────────────────┐
│  STACK:  ◉ nginx   ○ Apache (.htaccess)        DETECTED: nginx · PHP-FPM      │
│  FPM:    ◉ unix socket  ○ TCP    PATH: [ /run/php/php8.3-fpm.sock        ]    │
│  ☑ browser cache (6M)   ☑ gzip   ☑ CDN CORS   ☑ mobile-UA vary               │
├─────────────────────────────────────────────────────────────────────────────┤
│  server {                                              [ ⧉ COPY ]  [ ⭳ .conf ]│
│    set $cache_uri $request_uri;                        ┌─ VALIDATION ───────┐ │
│    ...                                                 │ ✓ paths resolved   │ │
│    location / {                                        │ ✓ socket exists    │ │
│      try_files /wp-content/cache/swiftpress/...        │ ▲ reload nginx     │ │
│    }                                                   │   after pasting    │ │
│  }                                                     └────────────────────┘ │
│  ▸ mono, syntax-highlit, line-numbered, the ACTUAL generated config           │
└─────────────────────────────────────────────────────────────────────────────┘
```

Upgrades over today (also flagged in the audit §7): **per-stack variants** (socket vs TCP path
as an input that templates into the snippet), **copy-to-clipboard** with a "copied ✓" flash,
**fixes the hardcoded `/var/run/fastcgi.sock`** by making it a field, **wires mobile-UA + gzip
`$pc_enc`** that the current nginx output drops, and a **validation sidebar** that runs the
existing `run_diagnostic()` checks plus a "did you reload nginx?" reminder. This screen alone is
a reason an nginx-hosted user picks SwiftPress — so it gets hero treatment, mono and
line-numbered, like reading a real config file.

### 5.5 The currently-orphaned settings

All surfaced cleanly, never dumped. Pattern: each is a **labeled panel with a master toggle,
inline help, an example, and live validation** — the opposite of the current "9 unlabeled
textareas" Advanced tab.

- **CDN / Cloudflare** (DELIVERY): master `enable_cdn` + CDN URL; a separate Cloudflare card
  (`enable_cloudflare`, token/email/key/zone in masked fields, a **Test Connection** button, a
  **Purge Cloudflare** action). Token strip on export preserved.
- **Image optimizer** (MEDIA & FONTS): `enable_image_optimization` + a **preferred-format**
  segmented control (Auto / WebP / AVIF) bound to `image_optimizer_preferred_format`, plus
  `add_missing_image_dimensions` (framed as a CLS fix, with a CLS readout reference).
- **Critical CSS / RUCSS** (OPTIMIZE → CSS Delivery): toggles for `critical_css`,
  `remove_unused_css`, with `ucss_safelist`/`ucss_excluded_files` as **chip inputs** (not raw
  textareas) and an AI "suggest safelist entries" affordance. **Honesty note:** the audit found
  these are currently stub features with no generator — Mission Control shows a clear
  **"engine: pending"** state rather than a dead checkbox, and the AI panel explains *when*
  they're worth enabling. (If the generators ship, the state flips to live.)
- **Self-host GA / FB** (INTEGRATIONS → Self-Hosted Tracking): `enable_google_tracking`,
  `enable_fb_tracking` as two cards with a one-line "what this does / privacy" note and a "local
  file refreshed Xh ago" status.
- **Heartbeat** (INTEGRATIONS → Heartbeat): `enable_heartbeat` master + a compact **3×(status,
  interval)** matrix for dashboard/editor/frontend (`enable / disable / modify` + seconds),
  shown as a small instrument-style table with mono interval values.
- **Prefetch / preconnect / DNS-prefetch** (DELIVERY → Resource Hints): `prefetch_dns`,
  `preconnect_resource` as **domain chip inputs** with per-domain validation and a "+add domain"
  affordance; a live count badge.
- **Link prefetch on hover** (DELIVERY): `prefetch_links` as a single labeled toggle with a
  "prefetches the next page when a visitor hovers a link" explainer.

### 5.6 Per-URL cache inventory — **URL INVENTORY (manifest table)**

A dedicated screen reachable from the rail — a dense, dark, mono data table reading the cache
directory (`get_page_cache_dir()` + per-URL `meta.php`/file stats) and the preload table.
Framed as a **flight manifest**:

```
┌─ URL INVENTORY ────────────────────────  1,204 cached · 318 MB · filter [____] ⌕ ┐
│  STATUS  URL                              AGE     SIZE    SCORE  VARIANTS  ACTIONS │
│  ● HIT   /                                 2m     41 KB    92    ssl·mob   ↻  ✕    │
│  ● HIT   /shop/                            8m     63 KB    78    ssl·mob   ↻  ✕    │
│  ▲ STALE /blog/old-post/                  29h     22 KB    —     ssl       ↻  ✕    │
│  ○ MISS  /cart/                            —       —       —     excluded  —       │
│  ● HIT   /about/                          14m     18 KB    95    ssl       ↻  ✕    │
│  …  (virtualized rows · sort by any column · bulk select → purge)                 │
└──────────────────────────────────────────────────────────────────────────────────┘
```

Per-row **purge** and **refresh** map onto the existing `delete_page_cache()` /
preload-single-URL paths; status dot uses the annunciator palette; AGE/SIZE/SCORE in mono with
threshold coloring; VARIANTS chips reflect the real cache-key dimensions (`-https`, `-mobile`,
logged-in) the drop-in already encodes. This is something **no competitor surfaces in-plugin**
and it fits the cockpit "every cached object is an instrument reading" metaphor perfectly.

---

## 6. SIGNATURE component — real CSS for the speedometer cluster

Below is production-quality CSS for **THE INSTRUMENT CLUSTER**: the radial gauge frame, the
animated needle + arc (the needle sweep is the brand moment), one ringed readout, and the
annunciator dot. The gauge geometry is SVG (markup sketched in the comment); the colored arc
uses `stroke-dasharray` driven by a single `--sp-score` custom property so PHP can set it inline
(`style="--sp-score: 86"`). This is judge-ready, scoped, token-driven, and reduced-motion aware.

```css
/* ============================================================
   THE INSTRUMENT CLUSTER — signature component
   Markup shape (simplified):
   <div class="sp-cluster" style="--sp-score:86">
     <svg class="sp-gauge" viewBox="0 0 200 130"> … arc + ticks … </svg>
     <div class="sp-gauge__needle"></div>
     <div class="sp-gauge__score"><b>86</b><span>PERF</span></div>
     <div class="sp-readout" data-state="good"> … LCP … </div> × 6
   </div>
   ============================================================ */

.sp-cluster {
  position: relative;
  display: grid;
  grid-template-columns: 1fr auto 1fr;          /* readouts | gauge | readouts */
  align-items: center;
  gap: var(--sp-space-6);
  padding: var(--sp-space-6) var(--sp-space-7);
  background:
    radial-gradient(120% 90% at 50% -10%, rgba(52,225,196,0.05), transparent 60%),
    var(--sp-surface-1);
  border: 1px solid var(--sp-line);
  border-radius: var(--sp-radius-lg);
  /* the bezel notch — top-right & bottom-left cut corners */
  clip-path: polygon(
    0 0, calc(100% - var(--sp-notch)) 0, 100% var(--sp-notch),
    100% 100%, var(--sp-notch) 100%, 0 calc(100% - var(--sp-notch))
  );
  box-shadow: var(--sp-shadow-2);
}

/* ---- Gauge frame ------------------------------------------- */
.sp-gauge {
  width: 260px;
  height: 170px;
  display: block;
  overflow: visible;
}
.sp-gauge__track {           /* the unfilled 0–100 arc */
  fill: none;
  stroke: var(--sp-surface-inset);
  stroke-width: 12;
  stroke-linecap: round;
}
.sp-gauge__fill {            /* the colored progress arc */
  fill: none;
  stroke: var(--sp-accent);
  stroke-width: 12;
  stroke-linecap: round;
  /* arc length ≈ 244 (semi-ellipse path). Reveal to score:   */
  stroke-dasharray: 244;
  stroke-dashoffset: calc(244 - (244 * var(--sp-score) / 100));
  filter: drop-shadow(0 0 6px var(--sp-accent-glow));   /* purposeful glow */
  transition: stroke-dashoffset var(--sp-dur-sweep) var(--sp-ease-inout);
}
.sp-gauge__tick { stroke: var(--sp-line-strong); stroke-width: 1.5; }
.sp-gauge__tick--major { stroke: var(--sp-text-dim); stroke-width: 2; }

/* Score banding recolors the arc by Lighthouse thresholds.    */
.sp-cluster[data-band="poor"] .sp-gauge__fill { stroke: var(--sp-ramp-0); filter: drop-shadow(0 0 6px rgba(255,92,92,.5)); }
.sp-cluster[data-band="okay"] .sp-gauge__fill { stroke: var(--sp-ramp-1); filter: drop-shadow(0 0 6px rgba(240,180,41,.5)); }
.sp-cluster[data-band="good"] .sp-gauge__fill { stroke: var(--sp-ramp-2); filter: drop-shadow(0 0 6px rgba(63,208,127,.5)); }

/* ---- The needle (THE sweep) -------------------------------- */
.sp-gauge__needle {
  position: absolute;
  left: 50%; bottom: 34%;
  width: 3px; height: 78px;
  background: linear-gradient(to top, transparent, var(--sp-accent-bright));
  border-radius: var(--sp-radius-pill);
  transform-origin: bottom center;
  /* map score 0..100 to -90deg..+90deg: rotation = score*1.8 - 90 */
  transform: rotate(calc((var(--sp-score) * 1.8deg) - 90deg));
  transition: transform var(--sp-dur-sweep) var(--sp-ease-inout);
  box-shadow: 0 0 10px -1px var(--sp-accent-glow);
}
.sp-gauge__needle::after {                   /* hub cap */
  content: ""; position: absolute; left: 50%; bottom: -7px;
  width: 14px; height: 14px; transform: translateX(-50%);
  border-radius: var(--sp-radius-pill);
  background: var(--sp-surface-3);
  border: 2px solid var(--sp-accent);
  box-shadow: var(--sp-glow-accent);
}

/* needle sweeps up from 0 on first paint */
@keyframes sp-sweep-in { from { transform: rotate(-90deg); } }
.sp-cluster[data-animate="in"] .sp-gauge__needle {
  animation: sp-sweep-in var(--sp-dur-sweep) var(--sp-ease-inout) both;
}

/* ---- Center score numeral --------------------------------- */
.sp-gauge__score {
  position: absolute; left: 50%; bottom: 30%;
  transform: translateX(-50%);
  text-align: center;
}
.sp-gauge__score b {
  font-family: var(--sp-font-display);
  font-size: var(--sp-text-3xl);
  font-weight: 600;
  line-height: 1;
  letter-spacing: var(--sp-tracking-tight);
  color: var(--sp-text-hi);
  font-variant-numeric: tabular-nums;
}
.sp-gauge__score span {
  display: block; margin-top: var(--sp-space-1);
  font-family: var(--sp-font-mono);
  font-size: var(--sp-text-2xs);
  letter-spacing: var(--sp-tracking-wide);
  text-transform: uppercase;
  color: var(--sp-text-dim);
}

/* ---- Ringed readout (LCP/CLS/INP/TTFB/HIT/SIZE) ------------ */
.sp-readout {
  position: relative;
  padding: var(--sp-space-3) var(--sp-space-4);
  background: var(--sp-surface-inset);
  border: 1px solid var(--sp-line-faint);
  border-radius: var(--sp-radius-md);
  display: grid;
  gap: 2px;
}
.sp-readout__label {
  font-family: var(--sp-font-mono);
  font-size: var(--sp-text-2xs);
  letter-spacing: var(--sp-tracking-wide);
  text-transform: uppercase;
  color: var(--sp-text-mute);
}
.sp-readout__value {
  font-family: var(--sp-font-mono);
  font-size: var(--sp-text-2xl);
  font-variant-numeric: tabular-nums;
  letter-spacing: var(--sp-tracking-data);
  color: var(--sp-text-hi);
  line-height: 1.1;
}
.sp-readout__value u { /* unit, de-emphasized, non-underlined */
  text-decoration: none;
  font-size: var(--sp-text-sm);
  color: var(--sp-text-dim);
  margin-left: 2px;
}
/* threshold-colored left edge + dot, the annunciator language */
.sp-readout::before {
  content: ""; position: absolute; left: 0; top: 10%; bottom: 10%;
  width: 3px; border-radius: var(--sp-radius-pill);
  background: var(--sp-text-dim);
}
.sp-readout[data-state="good"]::before { background: var(--sp-good); box-shadow: var(--sp-glow-good); }
.sp-readout[data-state="warn"]::before { background: var(--sp-warn); }
.sp-readout[data-state="crit"]::before { background: var(--sp-crit); box-shadow: var(--sp-glow-crit); }
.sp-readout[data-state="good"] .sp-readout__value { color: #d7f7e4; }
.sp-readout[data-state="crit"] .sp-readout__value { color: #ffd7d7; }

/* tiny sparkline under a readout (inline SVG polyline) */
.sp-readout__spark { width: 100%; height: 22px; opacity: .85; }
.sp-readout__spark polyline { fill: none; stroke: currentColor; stroke-width: 1.5; }
.sp-readout[data-state="good"] .sp-readout__spark { color: var(--sp-good); }
.sp-readout[data-state="warn"] .sp-readout__spark { color: var(--sp-warn); }
.sp-readout[data-state="crit"] .sp-readout__spark { color: var(--sp-crit); }

/* ---- Annunciator dot (used in rail + top bar) -------------- */
.sp-annun {
  display: inline-block; width: 8px; height: 8px;
  border-radius: var(--sp-radius-pill);
  background: var(--sp-text-dim);
  vertical-align: middle;
}
.sp-annun--on   { background: var(--sp-good); box-shadow: 0 0 8px -1px var(--sp-good); }
.sp-annun--warn { background: var(--sp-warn); box-shadow: 0 0 8px -1px var(--sp-warn); }
.sp-annun--crit { background: var(--sp-crit);
  /* only critical pulses — draws the eye, nothing else moves */
  animation: sp-pulse 1.6s var(--sp-ease-inout) infinite;
}
@keyframes sp-pulse { 50% { opacity: .35; } }

/* ---- Responsive: stack readouts below gauge on narrow ------ */
@media (max-width: 960px) {
  .sp-cluster { grid-template-columns: 1fr; justify-items: center; gap: var(--sp-space-5); }
}
```

This snippet alone demonstrates the aesthetic: cut-corner bezel, phosphor accent, glow **only**
on the live arc/needle/critical dots, mono tabular readouts, a choreographed needle sweep, and
strict token discipline. Drop it on a dark panel with the six readouts and it already *reads* as
Mission Control.

---

## 7. Recommended tech stack + honest justification

### 7.1 Recommendation: **Vanilla ES modules + a hand-built design-system CSS layer, with Alpine.js (~16KB) only on the few stateful screens. No React/Preact in the admin.**

The repo *can* compile `@wordpress/element` (React) via 10up-toolkit — but for **this vision**
that's the wrong tool, and choosing it would undercut the entire brand argument.

**Why lightweight, concretely:**

- **Performance-brand fit (the decisive factor).** This is a plugin whose entire promise is
  "your site is fast and lean." Shipping a 45KB+ React runtime to render an *admin* page —
  even though admin assets don't hit frontend Lighthouse — is a credibility tax. The competitor
  research shows Perfmatters explicitly wins trust by being "lightweight"; NitroPack loses trust
  by feeling like a heavy cloud app. Mission Control's admin should be **the fastest screen in
  wp-admin**, and we should be able to say so. A dark instrument panel that paints in one frame
  is on-brand in a way a hydrating SPA never is.
- **The UI is 90% display + forms, 10% interactive.** Most screens are server-rendered PHP
  partials (we already render PHP partials today) styled by the design system. The genuinely
  *stateful* surfaces are few: the **AI diagnostic flow**, the **mode selector diff**, the
  **URL inventory table** (sort/filter/virtualize), and the **live cluster polling**. Alpine.js
  (`x-data`/`x-show`/`x-on`) handles those declaratively, inline in the markup, with no build
  step required and no virtual DOM. The cluster's needle/arc are **pure CSS custom properties +
  SVG** — set `--sp-score` server-side, let CSS transition do the sweep; JS only updates the
  property on live refresh.
- **Bundle impact.** Design-system CSS (~18–24KB gz) + Alpine (~16KB gz, loaded only on the 3–4
  interactive screens, not the whole admin) + a small `mission-control.js` of helpers
  (clipboard, polling, count-up, sparkline path builder; ~8–12KB gz). **Total first-load on the
  dashboard ≈ 30–40KB gz.** A React/Preact build of the same surface is realistically 2–4× that
  before our own components, and brings a toolchain (JSX, hydration, state libs) the rest of the
  plugin doesn't use.
- **Maintainability fit with the codebase.** SwiftPress is PHP-singleton + WP-hooks + PHP
  partials. Vanilla + Alpine keeps the mental model "PHP renders, CSS styles, a sprinkle of JS
  enhances" — every contributor already understands it. A React island would create a second
  paradigm (build pipeline, component state, data-fetching layer) inside an otherwise
  server-rendered plugin, which is exactly the kind of architectural split the audit warns
  against. We can still **bundle** the CSS/JS through the existing 10up-toolkit/webpack pipeline
  (the audit notes current admin assets are enqueued raw — we'd fix that by adding `dashboard`
  and `inventory` entries to the `10up-toolkit.entry` map), getting minification/hashing without
  adopting React.
- **Richness is not sacrificed.** Everything in §4–§6 — the swept gauge, live readouts,
  diff-before-apply, virtualized inventory table, the diagnostic flow — is fully achievable in
  Alpine + CSS + SVG. The one thing React would buy (a large, complex, deeply-interactive app)
  is a thing this admin specifically should *not* be.

**Where I'd reconsider:** if the AI diagnostic grows into a multi-step wizard with heavy
client-side state and optimistic UI across many URLs, the diagnostic screen *alone* could become
a Preact island (Preact is ~4KB, swappable for `@wordpress/element` aliasing) without changing
the rest. That's a deliberate, contained escalation — not the default. Defaulting to React for
the whole admin is the mistake.

### 7.2 Rough component inventory

**CSS design-system primitives** (`design-tokens.css` + `mission-control.css`):
`sp-panel` (notched), `sp-rail` / `sp-rail-item` (+ annunciator), `sp-topbar`, `sp-button`
(primary/ghost/danger), `sp-toggle`, `sp-segment` (mode selector / format picker),
`sp-chip-input` (domains, safelist), `sp-field` + `sp-help`, `sp-table` (inventory),
`sp-code` (mono, line-numbered, syntax-highlit nginx), `sp-diff` (+green/−red rows),
`sp-readout`, `sp-cluster` + `sp-gauge`, `sp-annun`, `sp-tag`/`sp-risk-chip`,
`sp-notice` (toast), `sp-spark`, `sp-scanline` (diagnostic shimmer).

**Alpine components / behaviors** (`mission-control.js`):
`clusterLive` (poll metrics → set `--sp-score`, count-up, redraw sparklines),
`modeSelector` (compute + render setting-key diff, risk chips, apply),
`aiDiagnostic` (run → acquiring state → render validated change set → apply selected → undo →
re-audit), `nlConfig` (intent → validated diff), `urlInventory` (fetch, sort, filter,
virtualize, row purge/refresh), `serverConfig` (stack/socket inputs → template snippet, copy,
download, validate), `keyVault` (masked AI/Cloudflare key fields, test-connection),
`themeToggle` (Flight Deck / Daylight, persisted), `flushAll`.

**PHP/AJAX seams** (reuse + minimal new, all nonce + `manage_options`):
existing `swiftpress_clear_cache`, `swiftpress_clear_font_cache`, `swiftpress_refresh_sitemap`,
`swiftpress_preload_status`, `swiftpress_run_diagnostic`; **new** `swiftpress_metrics`
(cluster: cache size/count + hit-rate + last PSI), `swiftpress_ai_diagnostic`
(server-side OpenRouter call → validated change set), `swiftpress_apply_changeset`
(snapshot + write through `sanitize_options()`/`save_configuration()`), `swiftpress_undo_ai`,
`swiftpress_url_inventory` (paginated cache manifest), `swiftpress_purge_url`,
`swiftpress_server_config` (per-stack snippet). All route through the **existing** save path so
they inherit secret-stripping + opcache invalidation + advanced-cache regeneration.

---

## 8. Top strengths

1. **A genuinely non-interchangeable identity.** The swept-needle instrument cluster is unlike
   anything in the eight competitor dossiers (they show grades and metric cards). One screenshot
   is instantly recognizable as SwiftPress — which is exactly the owner's stated goal of a
   "super-modern, non-generic admin." It also can't be confused with the other two visions.
2. **The brand and the build agree.** A lean vanilla/Alpine + CSS stack makes the admin *itself*
   demonstrate the product's "fast and light" promise. The aesthetic argument and the
   engineering argument point the same direction — rare and powerful.
3. **Status clarity is the whole design, not a feature.** Annunciator coloring, mono readouts,
   the rail status dots, the inventory manifest — a user always knows what's armed, what's
   stale, what's broken. This directly answers NitroPack's documented weakness ("limited
   in-WordPress feedback").
4. **Surfaces the orphaned features without a dumping ground.** Every buried setting
   (Cloudflare, image format, Heartbeat, tracking self-host, prefetch/preconnect, LCP) gets a
   labeled, validated, explained home — a categorical upgrade over today's nine unlabeled
   textareas.
5. **Plays our two aces hard.** The **nginx generator** (a real edge rivals lack) gets hero
   treatment, and the **AI Explain-&-Fix** gets a cockpit "systems check" flow that makes the
   category-defining feature *feel* category-defining — while staying inside the safe
   validated-changeset + undo architecture the research mandates.
6. **Dense but legible at real values.** The information-instrument approach scales to power
   users (URL inventory, header values, per-setting risk) without becoming noisy, because
   typography and color do the hierarchy work.

## 9. Honest risks & weaknesses

1. **Dark-first + glow can tip into "gamer RGB" if undisciplined.** The entire concept lives or
   dies on *restraint*: glow only on live arc/needle/critical dots, never on idle chrome. If a
   future contributor adds neon everywhere, it cheapens fast. Mitigation: the tokens make glow a
   named, scarce resource (`--sp-glow-*` only three of them) and the spec says "idle UI does not
   glow."
2. **A speedometer is decorative if the data is thin.** The instrument is only honest if LCP/CLS/
   INP/TTFB/hit-rate are *real*. Field data needs the PSI/CrUX call (key-optional, on-demand);
   without it we fall back to lab + cache-derived numbers and must label them as such. If we ever
   show a needle with no real data behind it, it becomes the exact "gaming Lighthouse" optics the
   NitroPack dossier warns against. Mitigation: always badge field-vs-lab; never animate an
   empty gauge — show an "ACQUIRE TELEMETRY" empty state instead.
3. **WordPress admin is light by default — a dark island can feel disjoint.** Sitting a fully
   dark cockpit next to the grey wp-admin chrome is a deliberate statement but can read as
   "different app." Mitigation: the Daylight theme + respecting the admin color scheme as the
   default trigger, a clean hairline frame so it reads as an intentional panel, and never
   restyling wp-admin outside our scope.
4. **Accessibility burden is real.** Mono-everything, thin hairlines, and color-coded status
   must clear WCAG AA — color can't be the *only* signal (need the text label + shape), contrast
   on `--sp-text-dim` against dark surfaces is tight, and the needle animation must honor
   `prefers-reduced-motion` (handled in tokens). The instrument metaphor raises the bar for
   screen-reader semantics (the gauge needs an ARIA `meter` role + text equivalent). This is
   doable but is genuine work, not free.
5. **Density can intimidate non-technical owners** — the exact audience the AI feature targets.
   A Bloomberg-terminal feel risks scaring a small-business owner who just wants "make it fast."
   Mitigation: the **Zero-Config strip** ("what we already handled") and the **plain-language AI
   findings** are the soft on-ramp; the dense instrumentation is progressive — power lives one
   click deeper (URL inventory, header values) rather than on the first screen.
6. **Alpine is a (small) third-party dependency** and the team would standardize on it. It's
   ~16KB, well-maintained, and only loaded where needed — but it is a choice to own. If the org
   would rather have zero JS frameworks, the same screens are achievable in hand-written vanilla
   at the cost of more bespoke code; that's the honest trade.

---

### Appendix — grounding cross-references

- Settings keys & defaults: `includes/utils.php` `get_settings()` (lines 48–140) — every key in
  §3.2 / §5 is real.
- Save path / sanitizer / AJAX surface: `includes/admin/dashboard.php`
  (`sanitize_options()` 283–399, `run_diagnostic()` 742–829, AJAX actions 55–59) — reused, not
  reinvented.
- nginx generator: `Config::nginx_rules()` (Config.php 479–585) + audit §7 (socket hardcode,
  dropped mobile-UA/gzip) — §5.4 fixes exactly these.
- AI architecture (BYO key, server-side, validated changeset, undo, key-strip precedent):
  `research/ai-landscape.md` §2.1, §3 — §5.3 follows it precisely.
- Competitor gaps exploited: `research/nitropack.md` (split-dashboard, limited in-WP feedback,
  weak nginx), `research/wp-rocket.md` (flat Insights score, no AI), `research/perfmatters.md`
  (lightweight = trust, no score UX) — Mission Control counters each.
