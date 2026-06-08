# SwiftPress Admin Revamp — Vision: "Quiet Premium"

> Soft Atmospheric Minimalism × Luxury/Refined. The deliberate **anti-WP-Rocket**.
> One rich accent, dramatic negative space, tactile surfaces, and a Performance Score so large it *is* the page.
>
> Target polish bar: Linear / Stripe-dashboard / Vercel.
> Author: design pass for the SwiftPress owner (strong anti-generic taste). Date: 2026-06-08.

---

## 0. The one-paragraph thesis

Every cache plugin on the market — WP Rocket, FlyingPress, Perfmatters, LiteSpeed, NitroPack, W3TC, Autoptimize, WP Super Cache — renders the same screen: a wall of native-WordPress toggles in a `.form-table`, organized into tabs, fighting for the user's attention all at once. SwiftPress's *current* UI is no different (five `.form-table` accordions; the audit calls it "a developer placeholder, not a product"). **Quiet Premium wins by doing almost nothing on screen.** The landing view is one enormous, confident number — your Performance Score — floating in deliberate emptiness, with a single sentence of plain language beneath it and exactly one primary action. The 200 toggles still exist, but they are *folded away* behind a calm progressive-disclosure model until the user explicitly asks for them. Where rivals shout features, SwiftPress states a result. That restraint is not a lack of capability; it is the most expensive-looking thing on the page.

---

## 1. Aesthetic direction + the ONE signature move

### 1.1 Aesthetic direction — "Soft Atmospheric Minimalism, refined"

- **Editorial, not dashboard-y.** The composition reads like a luxury print spread (think a high-end watch ad or *Kinfolk* layout): generous margins, a strong typographic hierarchy, one image-like focal element, and lots of intentional air. Density is *low by default* and *summoned on demand*.
- **One rich accent, used sparingly.** A single warm-cool signature hue (a deep refined **teal-to-jade**, see tokens) carries all "alive/good" meaning. It appears on maybe 5% of the surface — the score arc, one CTA, the active nav marker — and nowhere else. No second brand color competes with it. This is the opposite of WP Rocket's orange-everywhere or the white+purple SaaS gradient cliché (explicitly banned).
- **Atmospheric depth, not flat-design.** Backgrounds are not a single flat grey. They carry an extremely subtle, large-radius radial "aurora" — a near-imperceptible gradient bloom behind the hero number — so the canvas feels like a lit room, not a spreadsheet. Surfaces sit on this atmosphere as soft, tactile cards with a faint top-edge highlight (a 1px inner light line) that reads as physical material under a soft light source.
- **Tactile premium surfaces.** Cards use a layered elevation system: a barely-there border, a soft long shadow, and an inner top highlight. The effect is closer to brushed anodized metal / frosted glass than to Material Design paper.
- **Calm motion.** Motion is slow, eased, and meaningful — load reveals stagger upward, the score *counts up* and the arc *draws* once on entry, disclosure panels expand with spring-soft height. Nothing bounces decoratively. (Detailed in §5 and §6.)

### 1.2 THE signature move

> **"The Number Is The Hero."**
> The Performance Score is rendered as an oversized editorial display numeral — roughly **clamp(7rem … 13rem)**, optically centered in dramatic negative space, with a thin **drawn-on-load progress arc** wrapped around it and a single human sentence beneath. The entire rest of the plugin's complexity (every setting, every advanced textarea, every add-on) is **progressively disclosed** — invisible until the user chooses to descend into it. The score is not a small widget in a grid of widgets (the way WP Rocket / LiteSpeed show a tiny grade chip). **It is the landing page.** You open SwiftPress and the first thing — almost the *only* immediate thing — is a number the size of a magazine cover headline telling you how fast your site is.

This is non-interchangeable: it is impossible to confuse this screen with WP Rocket's tab bar, with a Perfmatters checkbox wall, or with the two sibling visions (which lead with command-surfaces / dense data instrumentation respectively — see §11). It commits fully to "result first, controls on request."

---

## 2. Design tokens — DARK theme (the primary theme)

Authored as CSS custom properties on a `:root[data-sp-theme="dark"]` (a light theme mirrors these; see note after the block). Real, open-source fonts named. The palette is a **near-black ink base** (not pure black, not WP grey), warm-neutral surfaces stepped for elevation, a disciplined text hierarchy, and the single jade accent.

```css
/* ============================================================
   SwiftPress · "Quiet Premium" · DARK THEME TOKENS
   Scope to the plugin root only — never leak into wp-admin global.
   ============================================================ */
:root[data-sp-theme="dark"],
.swiftpress-app[data-sp-theme="dark"] {

  /* ---- BACKGROUND LAYERS (deepest → nearest the eye) ---- */
  --sp-bg-base:        #0B0D10;   /* app canvas — near-black ink, faint blue cast */
  --sp-bg-sunken:      #08090C;   /* wells, code blocks, recessed inputs           */
  --sp-bg-aurora-1:    #0E2A28;   /* atmospheric bloom stop A (jade-tinted)        */
  --sp-bg-aurora-2:    #0B0D10;   /* atmospheric bloom stop B (fades to base)      */

  /* ---- SURFACES (cards / sheets / popovers) ---- */
  --sp-surface-1:      #14171C;   /* primary card                                  */
  --sp-surface-2:      #1B1F26;   /* raised card / hover / nested panel            */
  --sp-surface-3:      #232932;   /* popover, menu, tooltip, active disclosure     */
  --sp-surface-inset:  #0F1216;   /* inputs, textareas, the nginx code well        */
  --sp-highlight-edge: rgba(255,255,255,0.06); /* 1px inner top-edge "light line"  */

  /* ---- BORDERS / STROKES (hairlines, by emphasis) ---- */
  --sp-border-subtle:  rgba(255,255,255,0.06);
  --sp-border-default: rgba(255,255,255,0.10);
  --sp-border-strong:  rgba(255,255,255,0.16);
  --sp-border-accent:  rgba(45,212,178,0.45);

  /* ---- TEXT TIERS ---- */
  --sp-text-hero:      #F4F7F6;   /* the giant score numeral, H1                   */
  --sp-text-primary:   #E4E8EC;   /* body headings, key labels                     */
  --sp-text-secondary: #A8B0BA;   /* descriptions, secondary copy                  */
  --sp-text-tertiary:  #6C7681;   /* meta, captions, units, disabled-ish hints     */
  --sp-text-on-accent: #04150F;   /* ink text sitting ON the jade accent           */

  /* ---- ACCENT (the single rich hue) + its scale ---- */
  --sp-accent:         #2DD4B2;   /* signature jade — "good / alive / primary"     */
  --sp-accent-bright:  #5DEBCC;   /* hover / focus glow / arc highlight            */
  --sp-accent-deep:    #11A488;   /* pressed / gradient far stop                   */
  --sp-accent-wash:    rgba(45,212,178,0.10); /* tint fills, selected row          */
  --sp-accent-glow:    rgba(45,212,178,0.30); /* focus ring + arc bloom            */

  /* ---- STATUS (quiet, desaturated — luxury, not traffic-light) ---- */
  --sp-ok:        #2DD4B2;   /* good == accent, reinforces "fast is the brand"     */
  --sp-ok-fg:     #7FF0DA;
  --sp-warn:      #E8B45C;   /* warm amber, muted                                  */
  --sp-warn-fg:   #F4D08C;
  --sp-risk:      #E0746B;   /* dusty terracotta, NOT fire-engine red              */
  --sp-risk-fg:   #F0A39B;
  --sp-info:      #7AA2E0;   /* slate-blue, informational                          */
  --sp-info-fg:   #A9C4EE;

  /* Score-band hues (the arc + numeral can subtly take these) */
  --sp-band-poor:    #E0746B;  /* 0–49   */
  --sp-band-fair:    #E8B45C;  /* 50–89  */
  --sp-band-good:    #2DD4B2;  /* 90–100 */

  /* ---- TYPOGRAPHY ---- */
  --sp-font-display: "Fraunces", "Hoefler Text", Georgia, serif;        /* hero numeral + H1 */
  --sp-font-body:    "Geist", "Inter Tight", -apple-system, sans-serif; /* precise neutral body (NOT Inter/Roboto) */
  --sp-font-mono:    "Geist Mono", "JetBrains Mono", ui-monospace, monospace; /* nginx code, values, metrics */

  /* Type scale — 1.250 (major third), display steps oversized on purpose */
  --sp-fs-score:  clamp(7rem, 4rem + 14vw, 13rem); /* THE hero numeral            */
  --sp-fs-d1:     clamp(2.5rem, 1.6rem + 3vw, 3.5rem);
  --sp-fs-d2:     2.25rem;
  --sp-fs-h1:     1.75rem;
  --sp-fs-h2:     1.375rem;
  --sp-fs-h3:     1.125rem;
  --sp-fs-body:   0.9375rem;  /* 15px */
  --sp-fs-sm:     0.8125rem;  /* 13px */
  --sp-fs-xs:     0.6875rem;  /* 11px — tracked caption / unit labels */

  --sp-lh-tight:   1.05;   /* display numerals */
  --sp-lh-snug:    1.25;
  --sp-lh-normal:  1.6;
  --sp-tracking-caps: 0.14em; /* the small uppercase eyebrow labels */
  --sp-weight-display: 340;   /* Fraunces optical: light-ish, high-contrast serif */
  --sp-weight-body:    400;
  --sp-weight-medium:  500;
  --sp-weight-semi:    600;

  /* ---- SPACING RHYTHM (4px base, generous large steps) ---- */
  --sp-space-0:  0;
  --sp-space-1:  0.25rem;  /*  4 */
  --sp-space-2:  0.5rem;   /*  8 */
  --sp-space-3:  0.75rem;  /* 12 */
  --sp-space-4:  1rem;     /* 16 */
  --sp-space-5:  1.5rem;   /* 24 */
  --sp-space-6:  2rem;     /* 32 */
  --sp-space-7:  3rem;     /* 48 */
  --sp-space-8:  4.5rem;   /* 72 — the "luxury air" step around the hero */
  --sp-space-9:  7rem;     /* 112 — top/bottom breathing room on the home canvas */

  /* ---- RADIUS SCALE (deliberate shape language: soft-squared, not pill) ---- */
  --sp-radius-xs:  4px;
  --sp-radius-sm:  8px;
  --sp-radius-md:  12px;   /* default card */
  --sp-radius-lg:  18px;   /* hero panel, sheets */
  --sp-radius-xl:  26px;   /* the outer hero stage */
  --sp-radius-full: 999px; /* used ONLY for the score arc + status dots, nowhere else */

  /* ---- ELEVATION (soft, long, low-opacity — atmospheric not harsh) ---- */
  --sp-elev-0: none;
  --sp-elev-1: 0 1px 0 0 var(--sp-highlight-edge) inset,
               0 1px 2px rgba(0,0,0,0.40);
  --sp-elev-2: 0 1px 0 0 var(--sp-highlight-edge) inset,
               0 4px 14px -4px rgba(0,0,0,0.55),
               0 2px 4px rgba(0,0,0,0.35);
  --sp-elev-3: 0 1px 0 0 var(--sp-highlight-edge) inset,
               0 18px 50px -12px rgba(0,0,0,0.65),
               0 6px 16px rgba(0,0,0,0.40);
  --sp-elev-hero: 0 1px 0 0 rgba(255,255,255,0.08) inset,
                  0 40px 120px -30px rgba(0,0,0,0.75);
  --sp-glow-accent: 0 0 0 4px var(--sp-accent-glow); /* focus ring */

  /* ---- MOTION ---- */
  --sp-ease-out:    cubic-bezier(0.16, 1, 0.3, 1);   /* the "calm" deceleration   */
  --sp-ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  --sp-dur-fast:   140ms;
  --sp-dur-base:   260ms;
  --sp-dur-slow:   520ms;
  --sp-dur-reveal: 900ms;   /* score count-up + arc draw */

  /* ---- LAYOUT ---- */
  --sp-content-max:  1080px; /* narrow, editorial column — NOT full-bleed admin */
  --sp-rail-w:       248px;  /* the quiet left rail */
}
```

**Light theme** (`[data-sp-theme="light"]`) mirrors the same variable names: `--sp-bg-base:#F6F7F5` (warm paper, not stark white), surfaces step *up* in brightness (`#FFFFFF`, `#FBFBFA`), text inverts (`--sp-text-hero:#0C0F12`), the aurora becomes a pale jade mist (`rgba(45,212,178,0.06)`), borders become `rgba(10,15,20,0.08/.12/.18)`, and the accent stays `#11A488` (the deeper jade reads better on light). Status hues darken one step for AA contrast on paper. **Both themes ship; dark is default.** A theme toggle lives in the footer of the left rail. Respect `prefers-color-scheme` on first run; respect `prefers-reduced-motion` to skip the count-up/arc-draw and just render final state.

### Why these fonts (rationale)

- **Display — Fraunces** (Google, OFL): a "soft-serif" with high optical contrast and an *opsz* axis that lets the giant numeral look like editorial luxury typography rather than a dashboard digit. It is characterful and warm — precisely the "refined print" register — and is emphatically **not** Inter/Roboto/system-ui (which the brief bans for display). Fraunces at the score size, with its tall ascenders and elegant terminals, is the single biggest contributor to the "expensive" feeling. Variable-weight, so the whole display tier is one file.
- **Body — Geist** (Vercel, OFL): a precise, neutral, slightly technical grotesque designed for product UIs and code-adjacent contexts. It is the "calm engineer" counterpoint to Fraunces' warmth: legible at 13–15px, excellent number alignment, and it signals *modern tooling* (Vercel/Linear register) without the over-exposure of Inter. (Fallback `Inter Tight` only if self-hosting Geist is undesired — never plain Inter.)
- **Mono — Geist Mono** (OFL): pairs metrically with Geist; used for the nginx config well, metric values (TTFB `0.21s`), the score's secondary digits, and setting keys in the AI diff. Real tabular monospace makes the generated server config look authoritative and copy-ready.

All three are self-hosted (woff2, `font-display:swap`, subset to Latin) inside the plugin — no Google Fonts CDN call from an admin page (on-brand for a *performance* plugin, and the audit's FontOptimizer already proves we self-host fonts).

---

## 3. Information architecture

### 3.1 Philosophy

A **quiet left rail** (not WP Rocket's horizontal tab strip, not NitroPack's split external dashboard) holds at most 6 destinations. The content column is **narrow and centered** (`--sp-content-max: 1080px`) — editorial, never full-bleed. Each destination opens calm and shallow; depth is reached by *descending* into a "Refine" disclosure, never by being confronted with everything at once.

### 3.2 Nav / page structure

```
SwiftPress (left rail)
│
├─ ◎  Overview        ← THE HOME. The hero Performance Score lives here. (§3.3)
│
├─ ⚡ Speed           ← all optimization, presented as ONE calm surface with
│                        progressive "Refine" disclosure. Folds in: Page Cache,
│                        File Optimization (CSS/JS/HTML), Fonts, Media/Images,
│                        Preload, LCP. (The current 5 accordions, re-homed.)
│
├─ ✦  AI Diagnostic   ← "Explain & Fix". The headline AI feature, its own room. (§4.3)
│
├─ ⌁  Delivery        ← server + edge: nginx config generator (our standout, §4.4),
│                        Apache/.htaccess status, CDN/Cloudflare, Varnish, browser cache.
│
├─ ⊘  Rules           ← the "advanced" power-user surface: per-URL cache inventory
│                        (§4.7), cache exclusions (URI/cookie/UA/referrer/vary/query),
│                        Heartbeat, tracking self-host (GA/FB), purge rules, dev mode.
│
└─ ⚙  Settings        ← license/key (OpenRouter), import/export, theme toggle,
                          integrations auto-detect (compat/), diagnostics log, about.
```

Rail item anatomy: a thin glyph, a label in body font, and — only on the active item — a 2px jade left-marker that *slides* between items (shared-element transition). The rail is `--sp-surface-1` against the `--sp-bg-base` canvas with one hairline border. At the rail's foot: a tiny "fast/slow" live cache-status dot, the theme toggle, and the version.

Every screen carries a **persistent slim top bar** inside the content column (not a WP admin notice): left = breadcrumb / screen title in small caps eyebrow style; right = one global **Clear Cache** affordance + a kebab for secondary purge actions (purge CF, purge fonts, purge URL). This replaces the current loud `.sp-header` with two buttons.

### 3.3 The Dashboard HOME ("Overview") — what lives here

The home screen is **deliberately under-populated**. Above the fold: essentially the score and one sentence. Everything else is a quiet second tier you scroll to. This is the signature move made literal.

**ASCII wireframe — Overview (dark):**

```
┌───────────────────────────────────────────────────────────────────────────┐
│  ◎ Overview                                       [ Clear Cache ]   ⋯       │  ← slim top bar
├───────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│                                                                            │
│                          PERFORMANCE  ·  MOBILE ▾                          │  ← tiny tracked eyebrow
│                                                                            │
│                                                                            │
│                                  ╭───────╮                                 │
│                              ╭───╯       ╰───╮       (thin jade arc,       │
│                             │                 │       drawn on load,       │
│                             │      9 4        │       sweeps to 94%)       │
│                             │                 │                            │
│                              ╰───╮       ╭───╯       ← Fraunces numeral,   │
│                                  ╰───────╯              clamp(7–13rem)      │
│                                                                            │
│                    Your site is fast. Largest paint                        │  ← ONE human sentence
│                    lands in 1.2s — better than 88% of sites.               │     (body, secondary)
│                                                                            │
│                 [ ✦ Run AI Diagnostic ]      Re-test ↻                     │  ← ONE primary CTA + ghost
│                                                                            │
│                                                                            │
│                                                                            │
│   ─────────────────────────────────────────────────────────────────────  │  ← hairline; scroll past hero
│                                                                            │
│   FIELD & LAB                                                              │  ← small-caps section eyebrow
│   ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐         │
│   │ LCP         │ │ INP         │ │ CLS         │ │ TTFB        │         │  ← 4 quiet metric cards
│   │ 1.2s    ▁▂▃ │ │ 142ms   ▃▃▂ │ │ 0.03    ▁▁▁ │ │ 0.21s   ▁▂▁ │         │     value (mono) + sparkline
│   │ Good        │ │ Good        │ │ Good        │ │ Good        │         │     + tiny status word
│   └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘         │
│                                                                            │
│   WHAT SWIFTPRESS IS DOING                              Manage in Speed →  │
│   ┌───────────────────────────────────────────────────────────────────┐   │
│   │  ● Page cache        ● Gzip          ● Fonts self-hosted           │   │  ← "active" chips, jade dot
│   │  ● Preload (sitemap) ● Heartbeat     ○ Critical CSS  ○ Delay JS    │   │     (○ = available, off)
│   └───────────────────────────────────────────────────────────────────┘   │
│                                                                            │
│   ACTIVITY                                                                 │
│   ┌───────────────────────────────────────────────────────────────────┐   │
│   │  12,418 pages cached · 184 MB · 96% hit rate (24h)                 │   │  ← one dense status line,
│   │  Last preload 2h ago · Last AI tune 3d ago (+9 pts)                │   │     mono numerals
│   └───────────────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────────────┘
```

Key home decisions:
- The **score owns the first screen.** A first-time install (no audit yet) shows the same stage with the numeral as a *dashed placeholder ring* and the sentence "We haven't measured your site yet — run a 20-second diagnostic." The CTA becomes the only thing with color. (This doubles as our version of WP Rocket's "zero-config win," reframed: instead of bragging about defaults, we *measure* and *show* them.)
- **Mobile ▾** toggle by the eyebrow switches the whole hero between mobile/desktop field data (FlyingPress's device split, but as a single quiet control on the hero, not a separate tab).
- Metric cards use **mono values + a 12-bar sparkline** of the last N measurements, with a one-word status. No chart chrome, no axes — just the line and the number. (Field-first, echoing FlyingPress's Vitals philosophy, but rendered with restraint.)
- The "What SwiftPress is doing" chip cloud is our answer to the audit's "no status surface" gap and to WP Rocket's "here's what we already fixed" idea — but as *quiet jade dots*, filled = on, hollow = available. Clicking any chip deep-links into the relevant Speed disclosure.
- Activity is **two lines of mono**, not a grid of stat boxes. Restraint over decoration.

---

## 4. How this vision surfaces each required surface

### 4.1 Performance Score widget — the instrument

The score is the signature component (full CSS in §5). Behavior:
- On entry, the numeral **counts up** from 0 → value (`--sp-dur-reveal`, eased), while the **arc draws** from 12 o'clock clockwise to `value%` using `stroke-dashoffset`. The arc and numeral take their hue from the score band (`poor/fair/good`), but in the "good" band the hue *is* the brand jade — so a fast site literally glows the brand color. This is the emotional payoff.
- A tiny eyebrow above (`PERFORMANCE · MOBILE`) and one human sentence below (LLM- or rule-generated) — never a legend, never axis labels.
- Secondary digits (the "/100", a delta `+9` after an AI tune) are mono, tertiary text, small — so the big numeral stays uncontested.
- `prefers-reduced-motion`: render final numeral + full arc instantly, no animation.
- It appears at hero scale only on Overview. Elsewhere (e.g. after applying an AI fix) it can re-render at a *medium* size (≈`--sp-fs-d1`) inside a result card to show before→after, reusing the same component at a smaller `--sp-score-size`.

### 4.2 Optimization presets

Presets are the **first thing** on the **Speed** screen — above any individual toggle — because Quiet Premium wants the user to make one confident choice, not fifty. Rendered as **four wide, tactile "mode" cards** (NitroPack's mode-selector insight, but explicit and risk-honest per the research consensus):

```
   CHOOSE A PROFILE
   ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
   │ Safe         │ │ Balanced  ✓  │ │ Aggressive   │ │ WooCommerce  │
   │              │ │  (active)    │ │              │ │              │
   │ Cache + gzip │ │ + minify,    │ │ + delay JS,  │ │ Balanced, but│
   │ + fonts.     │ │ defer JS,    │ │ remove unused│ │ cart/checkout│
   │ Zero risk.   │ │ preload.     │ │ CSS, LCP.    │ │ never cached.│
   │              │ │              │ │              │ │              │
   │ ○ no risk    │ │ ◐ low risk   │ │ ● test first │ │ ◐ low risk   │
   └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
            ▸ See exactly what changes  (disclosure: a diff of toggles)
```

- The active profile shows a jade check + accent border; others are calm surface cards.
- Each card states *in plain words* what it turns on and a **risk glyph** (`○ none / ◐ low / ● test first`) — addressing NitroPack's "mystery bundle" critique and WP Rocket's risk-framing strength.
- "See exactly what changes" is a disclosure that expands a per-setting diff ("Balanced → Aggressive: + Delay JS, + Remove Unused CSS, + LCP optimization") with per-row risk labels, so a preset is never opaque.
- Choosing a preset writes through the existing `sanitize_options()` / `save_configuration()` path and re-tests the score, closing the loop visually (the hero re-animates to the new number).

Below the presets sits **"Refine individually ▾"** — collapsed by default. Expanding it reveals the granular controls (everything in the current 5 accordions), grouped as calm sub-sections (Cache · CSS · JavaScript · HTML · Fonts · Media · Preload · LCP), each row a setting with an inline one-line explanation and, where relevant, a risk chip. **This is the progressive-disclosure mandate:** the 200 toggles are present and fully editable, but they never assault the user — they are *requested*.

### 4.3 AI Performance Diagnostic ("Explain & Fix") — its own room

This is the headline feature and gets a dedicated **AI Diagnostic** destination, designed as a calm, conversational *report*, not a settings screen. Flow (mirrors the `ai-landscape.md` pipeline: collect metrics → PII-free prompt → structured JSON → server validation gate → confirm-diff → apply via existing save path → snapshot/undo → re-audit):

**ASCII wireframe — AI Diagnostic, after a run:**

```
┌───────────────────────────────────────────────────────────────────────────┐
│  ✦ AI Diagnostic                                            ⋯              │
├───────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│   ┌─────────────────────────────────────────────────────────────────┐     │
│   │  "Your homepage takes 2.4s to show its main image because a       │     │  ← LLM summary,
│   │   Google Font is blocking the first paint and your hero image     │     │     Fraunces-italic
│   │   isn't preloaded. Three changes will likely save ~0.8s."         │     │     pull-quote style
│   └─────────────────────────────────────────────────────────────────┘     │
│                                                                            │
│   PROPOSED CHANGES                          confidence shown per item       │
│   ┌─────────────────────────────────────────────────────────────────┐     │
│   │ ☑  Preload your hero font                       ◐ low risk  92% │     │
│   │     font_preload:  off → on                                     │     │  ← each row maps to a
│   │     Why: the H1 font blocks render for ~300ms.                  │     │     REAL setting key,
│   │  ─────────────────────────────────────────────────────────────  │     │     from→to, why, risk,
│   │ ☑  Turn on LCP optimization                     ◐ low risk  88% │     │     confidence + checkbox
│   │     enable_lcp_optimization:  off → on                          │     │
│   │  ─────────────────────────────────────────────────────────────  │     │
│   │ ☐  Remove unused CSS               (algorithmic) ● test  71% │     │  ← unchecked-by-default
│   │     remove_unused_css:  off → on                               │     │     for higher-risk items
│   │     Why: ~140 KB of CSS is unused on this page.                 │     │
│   └─────────────────────────────────────────────────────────────────┘     │
│                                                                            │
│   [ Apply selected ✓ ]     Snapshot taken — one-click Undo after apply.     │
│                                                                            │
│   ───────────────────────────────────────────────────────────────────     │
│   AFTER APPLY ▸  re-tests automatically and shows:   84  →  93   (+9)       │  ← medium score component
└───────────────────────────────────────────────────────────────────────────┘
```

Quiet-Premium-specific treatment:
- The **LLM summary is rendered as an editorial pull-quote** in Fraunces italic — it reads like advice from a calm expert, reinforcing the luxury register and making the AI's *voice* the centerpiece (the research's whole point: the LLM is the communication layer).
- Each proposed change is a **calm row, not an alert.** `setting_key` shown in mono (`font_preload: off → on`) so it's verifiable; `why` in secondary; a **risk glyph** + **confidence %** in tertiary. Higher-risk items default **unchecked**.
- One **Apply selected** CTA (the only accent button), with the snapshot/undo promise stated inline — trust through reversibility.
- After apply, the **score component re-appears at medium size** showing `before → after (+delta)` — the loop closes on the same hero instrument, a deliberate callback to the signature move.
- **No key configured / API down:** the room degrades to the deterministic rules table producing the same rows minus the prose, with a quiet line "AI explanation unavailable — showing standard recommendations." Never a hard fail.
- **CSS stays algorithmic:** RUCSS/Critical rows say `(algorithmic)` and the LLM only *triages/explains* them — exactly the research mandate; the LLM never emits CSS.
- A small "What leaves your site" disclosure lists the PII-free payload (derived metrics + settings map + env facts) — disclosure-as-trust, matching the WP.org transparency requirement.

### 4.4 The nginx config generator — OUR standout, given real stage

The audit notes we already generate nginx rules and that rivals make users hand-install `rocket-nginx` or read docs. Quiet Premium turns this from a buried download into a **showpiece** on the **Delivery** screen — a "generated artifact" presented like a premium code export:

**ASCII wireframe — nginx generator (Delivery):**

```
┌───────────────────────────────────────────────────────────────────────────┐
│  ⌁ Delivery › Server config                                                │
├───────────────────────────────────────────────────────────────────────────┤
│   We detected: nginx · PHP-FPM 8.3 · socket /run/php/php8.3-fpm.sock        │  ← auto-detected facts
│                                                                            │
│   STACK     ( ● php-fpm socket   ○ php-fpm TCP )      FPM path: [ /run/… ]  │  ← per-stack variant toggle
│                                                                            │
│   ┌─────────────────────────────────────────────────────────────────┐     │
│   │  swiftpress.conf                                  [ Copy ]  [ ↓ ] │     │  ← code well header
│   │ ················································································ │     │
│   │  # BEGIN SwiftPress                                                │     │
│   │  set $cache_uri $request_uri;                                     │     │  ← Geist Mono, syntax-tinted,
│   │  if ($request_method = POST) { set $cache_uri 'no-cache'; }       │     │     line numbers, the WELL
│   │  location / {                                                     │     │     sits in --sp-bg-sunken
│   │    try_files /wp-content/cache/swiftpress/$http_host/$cache_uri/  │     │     with inset shadow
│   │      index$pc_ssl$pc_ua.html $uri $uri/ /index.php?$args;         │     │
│   │  }                                                                │     │
│   │  # END SwiftPress                                                 │     │
│   └─────────────────────────────────────────────────────────────────┘     │
│                                                                            │
│   ① Paste into your server block   ② sudo nginx -t   ③ sudo nginx -s reload│  ← 3 calm steps
│                                                                            │
│   ▸ Verify it's live   (checks for X-SwiftPress-Cache: HIT)  ● not yet      │  ← live verify affordance
└───────────────────────────────────────────────────────────────────────────┘
```

- **Detected stack facts up top** (server, PHP, FPM socket) — removing the audit's hardcoded `unix:/var/run/fastcgi.sock` smell by surfacing it as a *filled-in field*.
- A **per-stack variant toggle** (socket vs TCP) regenerates the snippet live (research consensus: "generate per-detected-stack variants").
- The snippet renders in a **premium code well**: `--sp-bg-sunken`, inset shadow, Geist Mono, subtle syntax tinting (comments tertiary, directives primary, our cache path in accent), line numbers, and a one-click **Copy** + **Download `swiftpress.conf`**. Copy shows a soft "Copied" jade flash.
- **Three numbered calm steps** beneath, and a **"Verify it's live"** affordance that hits the site and checks for the `X-SwiftPress-Cache: HIT` header (the audit confirms we emit it), returning a quiet green/red inline result — our take on WP Super Cache's "Test Cache" idea, but real and on-brand.
- The same screen hosts **Apache/.htaccess status** as a sibling card (auto-managed → just a green "managed" state), so nginx is foregrounded as the *premium manual path* while Apache is the *quiet automatic one*.

### 4.5–4.6 The currently-orphaned settings — given proper homes

The brief lists features the current 5-section UI doesn't surface. Quiet Premium re-homes each *cleanly* (not all on one page), each as a calm card with one-line plain copy:

| Orphaned feature (keys) | New home | Treatment |
|---|---|---|
| **CDN / Cloudflare** (`enable_cdn`, `enable_cloudflare`, `cloudflare_*`) | **Delivery** | A "Edge & CDN" card. Cloudflare is a connect-style panel (email/key/zone in `--sp-bg-sunken` secret fields, redacted, with the audit's secret-strip honored). Generic CDN = one URL-rewrite field. "Purge Cloudflare" lives in the top-bar kebab. |
| **Image optimizer** (`enable_image_optimization`, `image_optimizer_preferred_format`) | **Speed → Refine → Media** | Toggle + a segmented **format** control (`Auto · WebP · AVIF · Original`) rendered as soft pills. The AI Diagnostic can *explain* the format choice but conversion stays algorithmic (research mandate). |
| **Critical CSS** (`critical_css`, `*_appended_content`, `*_fallback`, `*_additional/excluded_files`) | **Speed → Refine → CSS** | A disclosure with status (`Not generated / Generating… / Generated`), a "Generate" action, and an advanced sub-panel for appended/fallback CSS + path overrides (Autoptimize's per-path insight). Marked `(algorithmic)`. Honest: if no generator exists yet, the control reads "Coming with the engine" rather than a dead checkbox (fixes the audit's "dead controls" trust problem). |
| **Self-host GA / FB Pixel** (`enable_google_tracking`, `enable_fb_tracking`) | **Rules → Privacy & tracking** | Two toggles framed as *privacy + speed* wins ("Serve Google Analytics from your own server — no third-party DNS, GDPR-friendly"), echoing Perfmatters' local-analytics framing. |
| **Heartbeat** (`enable_heartbeat`, `heartbeat_*_status/interval`) | **Rules → Heartbeat** | One card, three contexts (Dashboard / Editor / Frontend), each a segmented `Default · Reduce · Disable` + an interval stepper that only appears when "Reduce/Modify" is chosen (progressive disclosure within the card). |
| **DNS-prefetch / Preconnect** (`prefetch_dns`, `preconnect_resource`) | **Speed → Refine → Resource hints** | Instead of raw textareas, a **chip/token input** (type a domain → it becomes a removable pill), with examples shown as ghost pills. Far calmer than the current `large-text code` boxes the audit flags as "zero guidance." |
| **Link prefetch on hover** (`prefetch_links`) | **Speed → Refine → Preload** | A single toggle with a one-line explanation ("Start loading a page the instant a visitor hovers a link"). |
| **Varnish** (`enable_varnish`, `varnish_ip`) | **Delivery** | Small card next to nginx/Apache: toggle + IP field, validated. |
| **Async cache cleaning / Dev mode** (`async_cache_cleaning`, `dev_mode`) | **Rules** (async) / **Settings** (dev mode) | Dev mode gets a prominent but calm "Development mode" switch in Settings with a clear "caching paused" banner state when on. |

The point: **nothing is dumped into an undifferentiated "Advanced" textarea graveyard** (the audit's chief IA complaint). Every orphan gets a labeled card, a sentence, and the right input affordance (toggle / segmented / chip-input / connect-panel).

### 4.7 Per-URL cache inventory

Lives on **Rules → Cache inventory** (the research consensus item; NitroPack/agency-grade). Quiet Premium renders it as a **restrained data table** — the *one* place density is welcome, but still typeset, not WP-list-table:

```
   CACHE INVENTORY                              [ search URLs… ]   ⟳ refresh
   ┌──────────────────────────────────────────────────────────────────────┐
   │  URL                         STATUS   AGE     SIZE    SCORE   ⋯       │
   │  /                           ● HIT    2h      48 KB    94     [purge] │
   │  /pricing/                   ● HIT    11m     31 KB    91     [purge] │
   │  /shop/                      ○ BYPASS  —       —       —      [why?]  │
   │  /blog/post-name/            ● HIT    1d      62 KB    88     [purge] │
   │  /cart/                      ⊘ EXCL    —       —       —      [rule]  │
   └──────────────────────────────────────────────────────────────────────┘
        showing 5 of 12,418 · sorted by traffic ▾
```

- Columns: URL · status (HIT/MISS/BYPASS/EXCLUDED, jade/amber/tertiary dot) · age · size · per-URL score (PSI, lazy-fetched & cached) · per-row purge.
- `BYPASS`/`EXCL` rows expose a **"why?"** popover ("Excluded by rule: `rejected_uri` matches `/cart`") — turning the cache key logic legible (the audit notes the key already varies by SSL/mobile/cookie).
- The table is **virtualized**, sortable by traffic/age/size/score, searchable. Monospace numerals, hairline row separators, generous row height (luxury density, not cramped). This is the agency "prove it's working" surface.

---

## 5. Signature component — real CSS (the Performance Score instrument)

This is judgeable, production-intent CSS for the hero score: the oversized Fraunces numeral, the draw-on-load arc, the count-up, the band-colored glow, and reduced-motion fallback. Markup is a single SVG ring + a numeral; the count-up is driven by a CSS-registered `@property` so it animates with no per-frame JS.

```css
/* =========================================================================
   SwiftPress · Signature component: the Performance Score instrument
   Usage:
     <div class="sp-score" style="--sp-score-value:94; --sp-score-size:14rem"
          data-band="good" role="img"
          aria-label="Performance score 94 out of 100, good">
       <svg class="sp-score__ring" viewBox="0 0 120 120" aria-hidden="true">
         <circle class="sp-score__track" cx="60" cy="60" r="54"/>
         <circle class="sp-score__arc"   cx="60" cy="60" r="54"/>
       </svg>
       <span class="sp-score__num"   aria-hidden="true">94</span>
       <span class="sp-score__unit">/100</span>
       <span class="sp-score__eyebrow">Performance · Mobile</span>
     </div>
   ========================================================================= */

/* Registered custom prop lets us TRANSITION an integer (the count-up)
   purely in CSS. counter() then renders it as text. */
@property --sp-score-count {
  syntax: "<integer>";
  inherits: false;
  initial-value: 0;
}

.sp-score {
  --_size:  var(--sp-score-size, 14rem);
  --_value: var(--sp-score-value, 0);     /* 0–100, set inline by PHP */
  --_band:  var(--sp-accent);             /* overridden per data-band  */

  position: relative;
  inline-size: var(--_size);
  block-size:  var(--_size);
  display: grid;
  place-items: center;
  isolation: isolate;
  /* the atmospheric bloom behind the number — the "lit room" effect */
  background:
    radial-gradient(60% 60% at 50% 45%,
      color-mix(in oklab, var(--_band) 22%, transparent) 0%,
      transparent 70%);
  filter: drop-shadow(0 24px 60px rgba(0,0,0,0.55));
}

/* score band → hue (poor / fair / good) */
.sp-score[data-band="poor"] { --_band: var(--sp-band-poor); }
.sp-score[data-band="fair"] { --_band: var(--sp-band-fair); }
.sp-score[data-band="good"] { --_band: var(--sp-band-good); }

/* ---- the ring ---- */
.sp-score__ring {
  position: absolute;
  inset: 0;
  inline-size: 100%;
  block-size: 100%;
  transform: rotate(-90deg);      /* start the arc at 12 o'clock */
  overflow: visible;
}
.sp-score__track,
.sp-score__arc {
  fill: none;
  stroke-width: 3.5;
  stroke-linecap: round;
}
.sp-score__track { stroke: var(--sp-border-default); }

.sp-score__arc {
  stroke: var(--_band);
  /* r=54 → circumference = 2π·54 ≈ 339.292 */
  stroke-dasharray: 339.292;
  /* offset = C · (1 − value/100); drawn via the reveal animation */
  stroke-dashoffset: calc(339.292 * (1 - var(--_value) / 100));
  filter: drop-shadow(0 0 10px color-mix(in oklab, var(--_band) 60%, transparent));
  animation: sp-arc-draw var(--sp-dur-reveal, 900ms) var(--sp-ease-out, ease-out) both;
}
@keyframes sp-arc-draw {
  from { stroke-dashoffset: 339.292; }      /* empty ring */
  /* `to` is the computed dashoffset above (value%) */
}

/* ---- the hero numeral (Fraunces) ---- */
.sp-score__num {
  font-family: var(--sp-font-display);
  font-weight: var(--sp-weight-display, 340);
  font-size: calc(var(--_size) * 0.42);
  line-height: 1;
  letter-spacing: -0.02em;
  color: var(--sp-text-hero);
  /* the count-up: animate the registered integer, print it with counter() */
  counter-reset: sp-score var(--sp-score-count);
  animation: sp-count-up var(--sp-dur-reveal, 900ms) var(--sp-ease-out, ease-out) forwards;
  /* faint inner sheen so the numeral feels lit from above */
  background: linear-gradient(180deg,
    var(--sp-text-hero) 0%,
    color-mix(in oklab, var(--sp-text-hero) 82%, var(--_band)) 100%);
  -webkit-background-clip: text;
  background-clip: text;
}
.sp-score__num::after { content: counter(sp-score); }

@keyframes sp-count-up {
  from { --sp-score-count: 0; }
  to   { --sp-score-count: var(--_value); }
}

/* ---- secondary digits + eyebrow ---- */
.sp-score__unit {
  position: absolute;
  inset-block-end: calc(var(--_size) * 0.22);
  font-family: var(--sp-font-mono);
  font-size: var(--sp-fs-sm);
  color: var(--sp-text-tertiary);
  letter-spacing: 0.02em;
}
.sp-score__eyebrow {
  position: absolute;
  inset-block-start: calc(var(--_size) * 0.12);
  font-family: var(--sp-font-body);
  font-size: var(--sp-fs-xs);
  text-transform: uppercase;
  letter-spacing: var(--sp-tracking-caps);
  color: var(--sp-text-tertiary);
}

/* ---- accessibility: honor reduced motion → no draw, no count ---- */
@media (prefers-reduced-motion: reduce) {
  .sp-score__arc  { animation: none; }   /* dashoffset already = final value */
  .sp-score__num  { animation: none; --sp-score-count: var(--_value); }
}

/* ---- a subtle, slow idle breath on the bloom (good band only) ---- */
@media (prefers-reduced-motion: no-preference) {
  .sp-score[data-band="good"]::before {
    content: "";
    position: absolute; inset: -8%;
    z-index: -1;
    border-radius: var(--sp-radius-full);
    background: radial-gradient(closest-side,
      color-mix(in oklab, var(--_band) 16%, transparent), transparent);
    animation: sp-breath 6s var(--sp-ease-out) infinite alternate;
  }
}
@keyframes sp-breath {
  from { opacity: 0.55; transform: scale(0.98); }
  to   { opacity: 1;    transform: scale(1.03); }
}
```

Notes on the snippet: the count-up uses a CSS `@property` registered integer + `counter()` so the number animates **without a JS rAF loop** (graceful, cheap, and it stops dead under reduced-motion). The arc draw is pure `stroke-dashoffset`. The numeral gets a faint vertical gradient + the band-hued bloom so a "good" score literally radiates the brand jade. The whole instrument is data-driven by two inline custom props (`--sp-score-value`, `--sp-score-size`) and a `data-band`, so the *same component* serves the hero (14rem) and the post-apply before/after card (≈3.5rem).

---

## 6. Recommended tech stack + honest justification

### 6.1 Recommendation: **Preact + htm (no JSX build needed) via the existing 10up-toolkit/webpack entry, with a hand-authored design-system CSS layer. NOT @wordpress/element/React. NOT Alpine.**

Concretely:
- **Preact (~4 KB gzip) aliased in for `react`/`react-dom`** through the webpack config the repo already has (10up-toolkit supports the alias; `@wordpress/element` is React-compatible so any WP components we *do* touch still work). Use **`@preact/signals`** for state (cache stats, score, AI diff) — minimal, fast, no Redux ceremony.
- **`htm`** for templating so we can skip a heavy JSX transform if we want a lean build, or keep JSX (10up-toolkit compiles it) — either is fine; the point is the runtime is Preact, not full React.
- **One bundled admin entry** (`assets/js/admin/app.[hash].js`) replacing the current hand-enqueued raw scripts. The PHP side stays exactly as-is: same `wp_ajax_*`/`admin-post` endpoints, same nonces, same `sanitize_options()` save path; the app is a thin SPA over those.
- **The design system is CSS, not a component library.** All tokens (§2) + components are authored as plain CSS (BEM-ish `sp-*` classes) in a single stylesheet, scoped under `.swiftpress-app`. No Tailwind (its utility soup fights the editorial restraint and ships either a build dependency or a large CDN file), no MUI/Chakra (huge, generic, on-brand-hostile). The signature look depends on *bespoke* CSS — a library would actively work against "non-generic."
- **No charting library.** Sparklines are inline SVG paths; the score ring is the SVG above; the per-URL table is virtualized by hand (or `@tanstack/virtual` ~3 KB if needed). Avoiding Chart.js/Recharts (50–150 KB) keeps us honest.

### 6.2 Why this, weighed against the alternatives

| Concern | Full React (`@wordpress/element`) | Alpine.js + DS-CSS | **Preact + signals + DS-CSS (chosen)** |
|---|---|---|---|
| **Bundle (gzip)** | React+ReactDOM ≈ **45 KB** before app code | Alpine ≈ 15 KB, but DOM-driven state gets unwieldy for the AI-diff/score/table interactions | Preact+signals ≈ **6–8 KB**; whole app realistically **20–30 KB** gzip |
| **Maintainability** | Familiar, but verbose; heavy for an admin SPA | Great for sprinkles; **poor** for a stateful multi-screen app with a diff engine, virtualized table, live re-test | Component model = clean for screens/disclosure/AI flow; signals keep state legible |
| **Richness** | High | Medium — animations/score instrument doable but the count-up/diff/table state get awkward in `x-data` | High — full control of the score instrument, staggered reveals, before/after re-render |
| **Performance-brand fit** | A 45 KB React admin for a *performance* plugin is an awkward look, even though admin assets don't hit frontend Lighthouse | Excellent (tiny) | **Excellent** — tiny runtime *and* rich; the brand claim survives scrutiny |
| **Repo fit** | Already compiles | Would be a new, separate paradigm bolted on | **Drop-in**: reuses the existing webpack/10up-toolkit pipeline via a one-line `react`→`preact/compat` alias |

The deciding factor is the **performance-brand mandate**: it would be self-defeating to ship a heavyweight React admin in a plugin whose entire pitch is "your site should be lean." Preact gives React's authoring ergonomics at ~1/6th the runtime, slots into the build that already exists, and keeps the door open to the handful of `@wordpress/components` we might reuse (e.g. a battle-tested `Popover`) without shipping all of React. Alpine was a serious contender for the "calm/lean" angle, but the AI-diff confirm flow, the virtualized per-URL inventory, the count-up score, and the live re-test loop are *genuinely stateful*, and that's where a component+signals model earns its (tiny) keep. **The CSS design system carries the entire aesthetic**, independent of the JS choice — so the "expensive" look is framework-agnostic and durable.

### 6.3 Rough component inventory

**Primitives** (`sp-*`): `Surface`/`Card`, `Button` (primary/ghost/danger), `Toggle`, `SegmentedControl` (image format, heartbeat states), `ChipInput` (DNS/preconnect/UA tokens), `SecretField` (CF keys, OpenRouter key), `Stepper` (intervals/timeouts), `Eyebrow`, `RiskGlyph` (○◐●), `StatusDot`, `Disclosure` (the spring-height fold), `Sparkline`, `CodeWell` (copy/download), `Toast`, `Popover`, `Sheet`.

**Signature / feature**: `ScoreInstrument` (§5; hero + medium variants) · `MetricCard` (value+sparkline+status) · `PresetCard` (the 4 profiles) · `PresetDiff` · `AIDiagnosticReport` (summary pull-quote + `AIChangeRow[]` + apply/undo) · `NginxGenerator` (detect + variant toggle + CodeWell + verify) · `EdgeCard` (Cloudflare/CDN/Varnish) · `CacheInventoryTable` (virtualized) · `ActivityLine` · `ActiveFeatureChips` · `Rail` (sliding active marker) · `TopBar` (clear-cache + kebab) · `ThemeToggle`.

**State (signals)**: `settings` (mirrors `get_settings()`), `score`/`metrics`, `aiDiff`, `cacheStats`, `inventory`, `detectedStack`, `theme`. One thin `api.js` wrapping `admin-ajax`/REST with the existing nonces.

---

## 7. Top strengths

1. **Instantly non-generic, instantly recognizable.** The oversized Fraunces score in negative space is unmistakable. Side-by-side with WP Rocket's tab strip or any competitor's `.form-table`, it doesn't read as "another cache plugin" — it reads as a premium product. It nails the brief's #1 goal (super-modern, non-generic) and the owner's anti-default taste.
2. **The signature move is also good UX, not just decoration.** Leading with one number + one sentence + one action is the *least intimidating* possible entry to a 200-setting plugin — it directly answers the audit's "no status surface" gap and reframes "200 toggles I don't understand" into "here's your result." Restraint here is functional.
3. **Progressive disclosure tames real complexity honestly.** Every orphaned feature gets a proper home and the right input (chip-input over raw textareas, segmented controls, connect-panels), so depth is reachable without a graveyard "Advanced" tab. Power users lose nothing; novices aren't assaulted.
4. **Differentiated AI presentation.** Rendering the LLM summary as an editorial pull-quote and the fixes as calm, verifiable, reversible rows makes our category-defining feature *feel* trustworthy and premium — the communication layer the research says is the whole point. No rival has anything close.
5. **The nginx generator finally gets the stage it deserves.** Detected-stack facts + per-variant snippet + premium code well + live verify converts a buried download into a showpiece — leaning into our genuine standout vs. rivals who punt to `rocket-nginx`.
6. **Lean stack matches the brand.** A ~20–30 KB Preact admin honors the performance pitch; the all-CSS design system makes the look durable and framework-independent; it drops into the existing build with a one-line alias.
7. **Dark-first with a real, restrained palette.** A single jade accent + atmospheric depth avoids every named cliché (no orange-everywhere, no white+purple SaaS gradient), and "fast == the brand color glows" is a small, memorable emotional hook.

## 8. Honest risks / weaknesses of THIS vision

1. **Negative space can read as "empty / unfinished" to some WP users.** The category trains people to expect dense control panels; a near-empty hero risks "where is everything?" confusion. **Mitigation:** the scroll-down second tier (metrics, active-feature chips, activity) must be visible enough to signal "there's more here," and the empty/first-run state must explicitly say "run a diagnostic." This is the single biggest adoption risk and needs usability validation.
2. **The score must be *credible* or the whole vision collapses.** If we make the number the hero, a wrong/volatile/slow-to-compute score is catastrophic to trust (NitroPack's "gaming Lighthouse" reputational lesson looms). We need a defensible methodology (PSI field+lab, clearly labeled mobile/desktop, cached, honest about staging sites where PSI can't reach) and graceful "couldn't measure" states. **Do not invent a proprietary score** users can't reconcile with PSI.
3. **Fraunces at display size is gorgeous but polarizing and heavier than a system font.** A high-contrast serif numeral is a strong opinion; it also adds a self-hosted font file. **Mitigation:** subset aggressively (the score only needs digits + a few glyphs → a tiny subset), keep `font-display:swap`, and ensure the numeral is still legible in the "poor" (terracotta) band on dark.
4. **Heavy progressive disclosure adds clicks for power users.** A developer who wants to flip `js_delay` and `rejected_uri` now traverses Speed → Refine → expand, or Rules → expand. **Mitigation:** a global command-palette / search (`/` to jump to any setting by name) and deep-linkable disclosures; remember last-expanded state. Without this, the calm IA frustrates the exact technical buyer who pays.
5. **Scope/build cost is real.** A bespoke SPA + custom design system + virtualized table + the score instrument + AI flow is materially more work than restyling the existing PHP template. The current code is server-rendered `.form-table`; this is a from-scratch front end (over the same, unchanged endpoints). Worth it for the goal, but it is not a weekend reskin.
6. **Dark-first means double the QA surface.** Two themes, status-color AA contrast in both, the aurora/bloom and the band-hued glow legible on both canvases — more visual QA than a single light theme. Status hues are intentionally desaturated (luxury), which makes hitting AA on amber/terracotta *harder*; needs a contrast audit.
7. **Calm motion can feel slow if mis-tuned.** `--sp-dur-reveal: 900ms` count-up/arc on *every* Overview visit could annoy on the 10th load. **Mitigation:** animate fully only on first load / after a re-test or AI apply; on plain navigation, render final state (or a 200ms fade). And always respect `prefers-reduced-motion`.
8. **Per-URL score column cost.** Fetching PSI per URL for a 12k-page inventory is infeasible to do eagerly. It must be lazy, on-demand, cached, and rate-limited — otherwise the "score" column is either empty (anticlimactic) or expensive. Be explicit that scores populate on request.

---

## 9. File written

`F:/Claude/powered-cache-revamp/proposals/vision-quiet-premium.md`
