# Vision: "AI-First Copilot" — SwiftPress Admin Revamp

> One of three competing visions. This one bets the whole panel on the AI Performance Diagnostic. The home screen is not a dashboard of dials — it is a **briefing written by the plugin about your site**, with a command bar as the primary verb. Metrics and settings are evidence cited *inside the narrative*, not the headline.

**Codename:** `Foreman` — the panel speaks like a site reliability foreman walking the floor: it has already looked, it tells you what's wrong in order, and it can fix it on your say-so.

---

## 0. The thesis in one paragraph

The research is unambiguous (`research/ai-landscape.md` §0–1): in the entire cache/perf category, **nobody translates an audit into plain language and applies mapped fixes.** WP Rocket hides the work behind tabs and "it just works" (`research/wp-rocket.md` §1); NitroPack is a black-box SaaS proxy; Perfmatters exposes 200 toggles. Every one of them makes the *human* do the interpretation: read a Lighthouse score, guess which of forty toggles maps to it, hope. The AI-First Copilot vision deletes that interpretation step. You open the panel and it has **already read your site** and is mid-sentence: *"Your homepage takes 4.1s to show its main image because a Google Font is render-blocking and your hero image isn't preloaded. Want me to fix both?"* The settings still exist — all ~70 keys from `includes/utils.php` are reachable — but they are the *appendix*, not the table of contents.

This is the only vision where the AI is the **information architecture**, not a feature inside it.

---

## 1. Aesthetic direction + the ONE signature move

### Aesthetic direction: **"Editorial Console"**

A serious, typeset, ink-on-paper editorial feel fused with the restraint of a financial terminal. Think *The Economist*'s confidence in a typeface meeting Linear's calm density meeting the quiet authority of a Bloomberg panel — but warmer, because it's *talking to you*. Large-format display serif for the AI's voice (this is prose; treat it as prose). A precise grotesque for UI chrome and data. A mono for the things that are literally code (nginx config, setting keys, file paths). The surface is a deep warm graphite, not the cold blue-black of every SaaS dark mode, with a single decisive accent — a **sodium-amber** (the color of a long-exposure speed light) — used sparingly enough that when it appears, it means *act here*.

It must read as **authored**, not generated. A magazine has a designed column, a pull-quote, a margin note, a byline. We borrow that grammar: the AI narrative lives in a real text column with a measure (~68ch), findings are pull-quotes, metrics are margin notes, and every claim is "cited" to the metric that produced it. This is the antithesis of a checkbox grid.

This deliberately does **not** look like Vision B (a dense control-room dashboard) or Vision C (a wizard-led, friendly design-system app). Those lead with *controls* and *flows*. This leads with *a voice and a page*.

### THE signature move: **"Ask SwiftPress" — the command/conversation surface as the hero**

The single memorable, non-interchangeable decision: **a persistent, full-width conversational command bar pinned to the top of every screen, which is also the home screen's beating heart.** It is simultaneously:

1. **The hero of the Dashboard** — on load, it is already populated with the AI's opening briefing (the diagnostic narrative), streamed in token-by-token like it's thinking, with Explain&Fix cards rendered inline as the model "reaches" each finding.
2. **A natural-language command line** — `⌘K` / `/` from anywhere focuses it. You type *"speed it up but don't cache the members area"* or *"why is my product page slow?"* and it answers in the same editorial voice, returning a **confirmable settings diff** (never silent application — `research/ai-landscape.md` §2.4, §3.6).
3. **The undo/history spine** — every applied change is a message in the thread. The conversation *is* the audit log. "Undo last AI change" is just scrolling up and clicking revert on that turn.

No competitor has a conversational surface at all. Making it the *hero* — the first thing you see, the primary verb, and the system of record — is the bet. Where Vision B's signature is an instrument cluster and Vision C's is a guided setup, **ours is a typeset machine that talks.**

A concrete consequence: there is **no traditional "Save Settings" button on the home screen.** You converse and confirm diffs, or you go into the (de-emphasized) settings pages. That is a deliberate, opinionated, slightly risky stake in the ground — and it's what makes this vision *not interchangeable* with the others.

---

## 2. Design tokens — DARK theme (primary)

The dark theme is the hero (the editorial console reads best on ink). A light theme inverts the layers and is sketched at the end of this section. These are real, copy-pasteable CSS custom properties.

```css
/* ============================================================
   SwiftPress — "Editorial Console" design tokens · DARK (hero)
   Namespace every token to avoid colliding with wp-admin.
   ============================================================ */
:root[data-sp-theme="dark"], .sp-root[data-sp-theme="dark"] {

  /* ---- BACKGROUND LAYERS (warm graphite, not cold blue-black) ---- */
  --sp-bg-base:        #14110f;   /* app canvas — warm near-black ink   */
  --sp-bg-sunken:      #100d0c;   /* wells, code blocks, the deepest    */
  --sp-bg-raised:      #1c1815;   /* primary surfaces / cards           */
  --sp-bg-raised-2:    #241f1b;   /* nested surface / hovered card      */
  --sp-bg-overlay:     #2b251f;   /* popovers, command palette, menus   */
  --sp-bg-scrim:       rgba(10, 8, 7, 0.72); /* modal backdrop          */

  /* ---- SURFACES / HAIRLINES ---- */
  --sp-line:           #322b25;   /* default hairline border            */
  --sp-line-strong:    #443a31;   /* emphasized divider / input border  */
  --sp-line-faint:     #241f1b;   /* barely-there internal rule         */

  /* ---- TEXT TIERS ---- */
  --sp-ink:            #f4ede3;   /* primary — warm paper-white         */
  --sp-ink-2:          #c9bdaf;   /* secondary — body prose             */
  --sp-ink-3:          #968a7c;   /* tertiary — captions, metadata      */
  --sp-ink-4:          #6a6055;   /* quaternary — disabled, watermarks  */
  --sp-ink-onaccent:   #1a1206;   /* text on amber fills                */

  /* ---- THE ACCENT: sodium-amber (use sparingly = "act here") ---- */
  --sp-accent:         #f4a527;   /* primary accent — speed-light amber */
  --sp-accent-hi:      #ffba47;   /* hover / focus brighten             */
  --sp-accent-lo:      #c47e10;   /* pressed / deep                     */
  --sp-accent-wash:    rgba(244, 165, 39, 0.12);  /* tint backgrounds   */
  --sp-accent-line:    rgba(244, 165, 39, 0.34);  /* tinted borders     */
  --sp-accent-glow:    rgba(244, 165, 39, 0.45);  /* focus ring / aura   */

  /* ---- STATUS (semantic, tuned to sit on warm graphite) ---- */
  --sp-good:           #5fb88a;   /* pass / healthy / improved          */
  --sp-good-wash:      rgba(95, 184, 138, 0.13);
  --sp-warn:           #e0a93a;   /* needs attention (distinct from accent) */
  --sp-warn-wash:      rgba(224, 169, 58, 0.13);
  --sp-bad:            #e0685f;   /* failing / error / regressed        */
  --sp-bad-wash:       rgba(224, 104, 95, 0.13);
  --sp-info:           #6fa8d6;   /* neutral informational              */
  --sp-info-wash:      rgba(111, 168, 214, 0.13);

  /* Core Web Vitals rail (deliberately NOT the same as status colors,
     so a "warn" badge never gets confused with a CWV "needs improvement") */
  --sp-cwv-good:       #59c08a;
  --sp-cwv-ni:         #e6b24a;
  --sp-cwv-poor:       #df6a61;

  /* ---- TYPOGRAPHY ---- */
  --sp-font-display: "Fraunces", "Iowan Old Style", Georgia, serif;     /* AI voice, headlines */
  --sp-font-body:    "Public Sans", ui-sans-serif, system-ui, sans-serif; /* UI chrome, labels  */
  --sp-font-mono:    "JetBrains Mono", ui-monospace, "SFMono-Regular", monospace; /* code, keys, nginx */

  /* Type scale — 1.250 (major third), 16px base, fluid at the top end */
  --sp-fs-900: clamp(2.40rem, 1.9rem + 1.6vw, 3.05rem); /* hero AI headline    */
  --sp-fs-800: 2.027rem;   /* section title (display)    */
  --sp-fs-700: 1.602rem;   /* card / finding title       */
  --sp-fs-600: 1.266rem;   /* sub-head                   */
  --sp-fs-500: 1.125rem;   /* lead prose                 */
  --sp-fs-400: 1.000rem;   /* body                       */
  --sp-fs-300: 0.875rem;   /* secondary / UI label       */
  --sp-fs-200: 0.787rem;   /* caption / metadata         */
  --sp-fs-100: 0.706rem;   /* micro / eyebrow / tag      */

  --sp-lh-tight:   1.12;   /* display headlines          */
  --sp-lh-snug:    1.32;   /* sub-heads                  */
  --sp-lh-prose:   1.62;   /* the AI narrative column    */
  --sp-tracking-eyebrow: 0.14em; /* uppercase eyebrows   */
  --sp-measure:    68ch;   /* the narrative column width */

  /* ---- SPACING — 4px base, musical (not linear) rhythm ---- */
  --sp-s-0:  0;
  --sp-s-1:  0.25rem;   /*  4 */
  --sp-s-2:  0.5rem;    /*  8 */
  --sp-s-3:  0.75rem;   /* 12 */
  --sp-s-4:  1rem;      /* 16 */
  --sp-s-5:  1.5rem;    /* 24 */
  --sp-s-6:  2rem;      /* 32 */
  --sp-s-7:  3rem;      /* 48 */
  --sp-s-8:  4rem;      /* 64 */
  --sp-s-9:  6rem;      /* 96 */

  /* ---- RADIUS — deliberate, restrained. Editorial = squarer.
     Cards are nearly square; only pills/controls round fully. ---- */
  --sp-r-0:    0;
  --sp-r-xs:   3px;     /* tags, inline chips          */
  --sp-r-sm:   5px;     /* inputs, buttons             */
  --sp-r-md:   8px;     /* cards, panels               */
  --sp-r-lg:   12px;    /* the command bar, modals     */
  --sp-r-pill: 999px;   /* status dots, segmented ctrl */

  /* ---- ELEVATION — low, warm, mostly hairline + soft ambient.
     Editorial surfaces sit *flat*; light comes from borders, not drop shadow. ---- */
  --sp-elev-0: none;
  --sp-elev-1: 0 1px 0 0 rgba(255,255,255,0.02) inset,
               0 1px 2px rgba(0,0,0,0.40);
  --sp-elev-2: 0 1px 0 0 rgba(255,255,255,0.03) inset,
               0 4px 16px -4px rgba(0,0,0,0.55);
  --sp-elev-3: 0 1px 0 0 rgba(255,255,255,0.04) inset,
               0 18px 48px -12px rgba(0,0,0,0.66);
  --sp-elev-accent: 0 0 0 1px var(--sp-accent-line),
               0 8px 30px -8px var(--sp-accent-glow);  /* live AI surface */

  /* ---- MOTION ---- */
  --sp-ease-out:   cubic-bezier(0.16, 1, 0.30, 1);   /* reveals          */
  --sp-ease-inout: cubic-bezier(0.65, 0, 0.35, 1);   /* state changes    */
  --sp-dur-fast:   140ms;
  --sp-dur-med:    260ms;
  --sp-dur-slow:   520ms;
  --sp-stagger:    60ms;   /* per-item entrance delay   */

  /* ---- FOCUS ---- */
  --sp-focus-ring: 0 0 0 2px var(--sp-bg-base), 0 0 0 4px var(--sp-accent-glow);
}

/* LIGHT theme — inverts layers to warm paper, keeps the amber accent.
   (Editorial-on-paper; serif headlines look native here.) */
:root[data-sp-theme="light"], .sp-root[data-sp-theme="light"] {
  --sp-bg-base:     #f7f3ec;  /* warm newsprint           */
  --sp-bg-sunken:   #efe9df;
  --sp-bg-raised:   #fffdf8;  /* card = brightest paper    */
  --sp-bg-raised-2: #f4eee3;
  --sp-bg-overlay:  #fffdf8;
  --sp-bg-scrim:    rgba(40, 33, 26, 0.40);
  --sp-line:        #e0d7c7;
  --sp-line-strong: #cbbfa9;
  --sp-line-faint:  #efe9df;
  --sp-ink:         #211b14;
  --sp-ink-2:       #4a4036;
  --sp-ink-3:       #7a6d5c;
  --sp-ink-4:       #a89a86;
  --sp-ink-onaccent:#1a1206;
  --sp-accent:      #d4820a;  /* amber goes a shade deeper for AA on paper */
  --sp-accent-hi:   #b86d05;
  --sp-accent-lo:   #9a5a04;
  --sp-accent-wash: rgba(212, 130, 10, 0.10);
  --sp-accent-line: rgba(212, 130, 10, 0.30);
  --sp-accent-glow: rgba(212, 130, 10, 0.35);
  --sp-good:#2f8f5f; --sp-warn:#b9791a; --sp-bad:#c24b42; --sp-info:#356c97;
  --sp-elev-1: 0 1px 2px rgba(90,70,40,0.10);
  --sp-elev-2: 0 6px 18px -6px rgba(90,70,40,0.18);
  --sp-elev-3: 0 20px 48px -16px rgba(90,70,40,0.22);
}
```

### Why these fonts (real, open-source / Google Fonts)

- **Display — Fraunces** (Google Fonts, SIL OFL). A "soft-serif" with optical-size and `wght`/`SOFT`/`WONK` variable axes. It has *character* — high-contrast, slightly old-style, warm — exactly the "this was written by something with a point of view" feeling the AI narrative needs. It is emphatically not Inter/Roboto/system-ui. At display sizes it carries the editorial voice; we run it at a higher optical size for headlines and dial `WONK` down for legibility.
- **Body / chrome — Public Sans** (US government / open-source, SIL OFL). A precise, neutral, slightly engineered grotesque designed for dense UI and excellent at small sizes — the "financial terminal" half. Neutral by design so it never competes with Fraunces, but with cleaner spacing and a taller x-height than Helvetica. (A strong alternative: **Archivo** — also OFL — if a touch more personality is wanted in the chrome.)
- **Mono — JetBrains Mono** (Apache 2.0). For the nginx config block, setting keys (`enable_page_cache`), file paths, and the command-bar input echo. High legibility, generous spacing, real ligatures off by default for config clarity.

The pairing is the aesthetic: **a serif that talks, a grotesque that labels, a mono that codes.** Three voices, three jobs, no overlap.

---

## 3. Information architecture

The IA is **inverted from every competitor.** Competitors lead with feature tabs (Cache / File Optimization / Media / Preload / CDN / Advanced). We lead with the *conversation and the diagnosis*; features are where you go when the AI sends you there or when you want manual control.

### Top-level nav (left rail, collapsible to icons)

```
SwiftPress
│
├─ ◆  Brief            ← HOME. The AI diagnostic narrative + Ask bar. (default)
├─ ◇  Inspect          ← Per-URL cache inventory + per-page detail + Lighthouse pull
├─ ⚙  Tune             ← All settings, AI-organized into "chapters" (not raw tabs)
│     ├ Caching
│     ├ Assets (CSS/JS/HTML)
│     ├ Media & Fonts
│     ├ Preload & Hints
│     ├ Integrations (CDN/Cloudflare, GA/FB, Varnish, Heartbeat)
│     └ Server & nginx
├─ ⌁  Server           ← nginx/Apache config generator (the standout, given its own home)
├─ ⧗  History          ← every AI change + manual save, as a reversible timeline
└─ ◈  Copilot          ← BYO OpenRouter key, model, spend cap, privacy disclosure
```

Two persistent global elements above the page content:

- **The Ask bar** (the signature move) — pinned full-width at the very top, present on *every* screen. On Brief it's the hero; elsewhere it's a slim docked strip that expands on focus (`⌘K`).
- **The Pulse strip** — a single thin horizontal line under the Ask bar showing the live **Performance Score** number, the three CWV dots (LCP/CLS/INP), cache state (warm/cold/%), and a "last audited 6m ago · re-audit" affordance. It's the persistent vital sign so the narrative-led home never feels like it's hiding the numbers.

### What this buys us

- A non-technical owner never has to know what "RUCSS" means to act — they read the Brief and click Fix.
- A power user is two clicks from any raw setting (`Tune → chapter`) and gets a real nginx generator (`Server`) that rivals don't ship at all.
- The **History** tab makes the "no Save button on home" decision safe: everything is reversible and visible.

---

## 4. The Dashboard HOME screen ("Brief")

The home screen is a **single editorial column** (max ~880px content, ~68ch prose measure) with a margin rail for metrics — like a magazine feature with a sidebar. It is NOT a grid of widget cards. The narrative leads; numbers are cited in the margin.

### ASCII wireframe — Brief (dark, desktop)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  ⌘  Ask SwiftPress anything…  "why is my homepage slow?"          [↵ Ask]  ◐   │ ← Ask bar (HERO)
├──────────────────────────────────────────────────────────────────────────────┤
│  PERFORMANCE 73 ▲6   ● LCP 2.4s   ● CLS 0.04   ● INP 180ms   Cache 94% warm    │ ← Pulse strip
│                                              audited 6m ago · ↻ re-audit        │
╞════════════════════════════════════════════════════════════╤═══════════════════╡
│                                                              │                   │
│  THE BRIEF                                  Tue · 14:06      │   ┌─ SCORE ─────┐ │
│  ───────────────────────────────────────────────────────    │   │             │ │
│                                                              │   │     ╭───╮   │ │
│  Your site is in decent shape — a 73 — but it's leaving      │   │    ( 73 )  │ │ ← Signature
│  about 1.6 seconds on the table, almost all of it on the     │   │     ╰───╯   │ │   instrument
│  homepage. Three things are worth fixing, in order:          │   │  ▲ +6 wk    │ │   (§5)
│                                                              │   │  Balanced   │ │
│  ┌────────────────────────────────────────────────────┐    │   └─────────────┘ │
│  │ ◆ 1 · A Google Font is blocking your first paint    │    │                   │
│  │      ─────────────────────────────────────────────  │    │   ┌─ CITED ─────┐ │
│  │   Your hero text waits on Fonts.googleapis.com       │    │   │ LCP element  │ │
│  │   before it can render. Self-hosting it would cut    │    │   │ <img.hero>   │ │
│  │   ~0.6s off LCP.                                     │    │   │ 2.4s → ~1.8s │ │ ← margin notes
│  │                          evidence: render-block 640ms│    │   │              │ │   "cite" the
│  │   ┌──────────────┐ ┌─────────────────┐  conf ●●●●○  │    │   │ Unused CSS   │ │   metric behind
│  │   │  ✦ Fix this  │ │ Explain more ▾  │             │    │   │ 142 KB / 38% │ │   each finding
│  │   └──────────────┘ └─────────────────┘             │    │   │              │ │
│  │      Self-host Google Fonts · Preload hero font      │    │   │ Blocking JS  │ │
│  └────────────────────────────────────────────────────┘    │   │ 9 scripts    │ │
│                                                              │   └─────────────┘ │
│  ┌────────────────────────────────────────────────────┐    │                   │
│  │ ◆ 2 · 142 KB of CSS goes unused on the homepage     │    │   ┌─ PRESET ────┐ │
│  │   …                                  conf ●●●○○      │    │   │ ○ Safe       │ │
│  │   ┌──────────────┐ ┌─────────────────┐              │    │   │ ◉ Balanced   │ │
│  │   │  ✦ Fix this  │ │ Explain more ▾  │              │    │   │ ○ Aggressive │ │
│  │   └──────────────┘ └─────────────────┘              │    │   │ ○ WooComm.   │ │
│  └────────────────────────────────────────────────────┘    │   │  what's this?│ │
│                                                              │   └─────────────┘ │
│  ┌────────────────────────────────────────────────────┐    │                   │
│  │ ◆ 3 · 9 scripts run before the page is interactive  │    │   You're on       │
│  │   …  [ ✦ Fix this ]  [ Explain more ▾ ]             │    │   nginx — Apache  │
│  └────────────────────────────────────────────────────┘    │   tips are hidden.│
│                                                              │   View nginx ⌁    │
│  ─────────────────────────────────────────────────────      │                   │
│  ✦ Fix all three  ·  est. +14 score, ~1.5s LCP   [ Apply ]  │                   │
│       This is reversible. I'll snapshot first.               │                   │
│                                                              │                   │
╘════════════════════════════════════════════════════════════╧═══════════════════╛
```

### Reading the wireframe

1. **Ask bar (hero).** Full width, top. On first load it does NOT sit empty — it shows the streaming briefing below. The input is always live for follow-ups (`"actually, skip the font one"`, `"what about mobile?"`).
2. **Pulse strip.** The one place raw numbers are always visible, so a narrative-led home is never accused of hiding data.
3. **THE BRIEF** is real prose set in **Fraunces**, with a dateline ("Tue · 14:06") like a column. It streams in on load (token-by-token reveal, §"motion"). It synthesizes — it doesn't just list audits — exactly the LLM value the research argues for (`ai-landscape.md` §2.1).
4. **Explain&Fix cards** are numbered findings rendered *inline in the narrative flow* (pull-quote style), each with:
   - a plain headline + a one-paragraph explanation,
   - the **cited evidence metric** ("render-block 640ms") in the margin — every claim is traceable,
   - a **confidence meter** (`●●●●○`) from the model's structured output,
   - **✦ Fix this** → shows the exact settings diff (which real keys flip) and applies through the existing save path with a snapshot,
   - **Explain more ▾** → expands without an API call if cached.
5. **Margin rail** (the "sidebar" of the feature): the **Performance Score instrument** (the signature component, §5), the **Cited** metrics block (LCP element, unused-CSS bytes, blocking-JS count — the ground truth the narrative draws from), the **Preset** selector, and an environment note ("You're on nginx").
6. **Fix-all footer.** One action to apply the whole prioritized set, with an honest estimate and the reassurance "This is reversible. I'll snapshot first."

### Empty / no-key state

If no OpenRouter key is set, the Brief degrades gracefully (`ai-landscape.md` §3.5): the **same findings** render from the deterministic static-rules engine, but the prose is replaced by a terse "Standard recommendations" list, and a quiet inline card says *"Connect a model in Copilot to get the plain-language briefing and the Ask bar."* The plugin is 100% functional with zero AI — AI is a pure upgrade layer.

### First-run state

On activation, before any audit, the Brief shows a single warm panel: *"I haven't looked at your site yet. Give me ~20 seconds?"* → **[ Run my first diagnostic ]**. Behind it, a one-time "here's what I already turned on for you" summary (zero-config activation, the WP Rocket-style move — `wp-rocket.md` §3.1 — but *narrated*).

---

## 5. The signature instrument — Performance Score (with real CSS)

This vision's hero metric is the **Performance Score dial**, but treated editorially: not a generic gauge, but a **typeset score** — a large Fraunces numeral inside a thin progress ring, with a delta, the active preset name, and a hairline tick scale. The ring animates on load; the number counts up; the whole thing has the amber accent only on the *filled* arc so it reads as "this is the live measure."

It must be judgeable, so here is the real implementation.

```css
/* ============================================================
   Signature component — Performance Score instrument
   Editorial gauge: typeset numeral + conic progress ring.
   Pure CSS (conic-gradient + @property for animatable angle).
   ============================================================ */

@property --sp-score-deg {
  syntax: "<angle>";
  inherits: false;
  initial-value: 0deg;
}

.sp-score {
  --score: 73;                 /* set inline: style="--score:73" (0–100)  */
  --score-deg: calc(var(--score) * 3.6deg);
  position: relative;
  display: grid;
  place-items: center;
  inline-size: 168px;
  block-size: 168px;
  font-family: var(--sp-font-body);
  isolation: isolate;
}

/* the ring: a conic fill (amber) over a track, masked to an annulus */
.sp-score__ring {
  position: absolute;
  inset: 0;
  border-radius: var(--sp-r-pill);
  background:
    conic-gradient(from -90deg,
      var(--sp-accent) var(--sp-score-deg),
      transparent 0);
  /* lay it over a static track */
  box-shadow: 0 0 0 1px var(--sp-line) inset;
  /* punch out the center to make a 10px-thick ring */
  -webkit-mask: radial-gradient(farthest-side,
                  transparent calc(100% - 11px), #000 calc(100% - 10px));
          mask: radial-gradient(farthest-side,
                  transparent calc(100% - 11px), #000 calc(100% - 10px));
  /* animate the angle in on load */
  animation: sp-score-fill var(--sp-dur-slow) var(--sp-ease-out) both;
  filter: drop-shadow(0 0 10px var(--sp-accent-glow));
}

/* the static track sits behind the fill so the unfilled arc is visible */
.sp-score__track {
  position: absolute;
  inset: 0;
  border-radius: var(--sp-r-pill);
  background: var(--sp-bg-sunken);
  box-shadow: 0 0 0 1px var(--sp-line-faint) inset;
  -webkit-mask: radial-gradient(farthest-side,
                  transparent calc(100% - 11px), #000 calc(100% - 10px));
          mask: radial-gradient(farthest-side,
                  transparent calc(100% - 11px), #000 calc(100% - 10px));
  z-index: -1;
}

@keyframes sp-score-fill {
  from { --sp-score-deg: 0deg; }
  to   { --sp-score-deg: calc(var(--score) * 3.6deg); }
}

/* the typeset numeral — Fraunces, the AI's voice, large */
.sp-score__num {
  font-family: var(--sp-font-display);
  font-weight: 560;
  font-size: var(--sp-fs-900);
  line-height: 1;
  letter-spacing: -0.02em;
  color: var(--sp-ink);
  font-variant-numeric: tabular-nums;
  /* count-up handled in JS by stepping a CSS var / textContent */
}

.sp-score__num::after {                 /* the /100, quiet */
  content: "/100";
  font-family: var(--sp-font-body);
  font-size: var(--sp-fs-200);
  font-weight: 500;
  color: var(--sp-ink-4);
  letter-spacing: 0;
  vertical-align: super;
  margin-inline-start: 2px;
}

.sp-score__delta {
  margin-block-start: 2px;
  font-size: var(--sp-fs-200);
  font-weight: 600;
  letter-spacing: var(--sp-tracking-eyebrow);
  text-transform: uppercase;
  color: var(--sp-good);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.sp-score__delta[data-dir="down"] { color: var(--sp-bad); }

/* tier label under the dial (Safe / Balanced / Aggressive) */
.sp-score__preset {
  position: absolute;
  inset-block-end: -26px;
  inset-inline: 0;
  text-align: center;
  font-size: var(--sp-fs-100);
  letter-spacing: var(--sp-tracking-eyebrow);
  text-transform: uppercase;
  color: var(--sp-ink-3);
}

/* color the ring by band without changing the accent identity:
   poor scores tint toward red, mid toward amber, good toward green */
.sp-score[data-band="poor"] { --sp-accent: var(--sp-cwv-poor); }
.sp-score[data-band="ni"]   { --sp-accent: var(--sp-cwv-ni); }
.sp-score[data-band="good"] { --sp-accent: var(--sp-cwv-good); }

/* respect reduced-motion: no fill sweep, no count-up */
@media (prefers-reduced-motion: reduce) {
  .sp-score__ring { animation: none; --sp-score-deg: calc(var(--score)*3.6deg); }
}
```

```html
<!-- usage -->
<figure class="sp-score" data-band="ni" style="--score:73">
  <div class="sp-score__track"  aria-hidden="true"></div>
  <div class="sp-score__ring"   aria-hidden="true"></div>
  <figcaption class="sp-score__num" aria-label="Performance score 73 of 100">73</figcaption>
  <span class="sp-score__delta" data-dir="up">▲ 6 this week</span>
  <span class="sp-score__preset">Balanced</span>
</figure>
```

Why this is the signature instrument and not a stock gauge: the **numeral is set in the display serif** (the same voice as the narrative), the fill is the brand amber with a soft glow (so the score reads as *live*, the way a terminal's active value glows), the band-tinting reuses the CWV palette so the color always means the same thing, and the whole thing animates as a single confident sweep on load rather than bouncing. It's a *typeset* score. Paired in the margin with the before/after ("2.4s → ~1.8s") it closes the loop the research demands (`ai-landscape.md` §2.1 step 6): re-audit after a fix and the ring re-sweeps to the new number.

---

## 6. How this vision surfaces every required surface

The rule across all of these: **the AI narrative is the entry point; the setting is the destination.** Every feature is reachable both ways — narrated ("Fix this") or manual (`Tune` chapter).

### 6.1 Performance Score widget
Covered in §5. Lives in the Brief margin rail and is mirrored, slimmed, in the persistent Pulse strip. Clicking it opens a "Score breakdown" drawer (Lighthouse category weights + CWV field vs lab) — for the user who wants the receipts behind the headline number.

### 6.2 Optimization presets (Safe / Balanced / Aggressive / WooCommerce)
A **segmented control in the Brief margin**, and — critically — **the AI narrates the consequence of switching.** Selecting "Aggressive" doesn't silently flip toggles; the Ask thread gets a message: *"Aggressive turns on Delay JavaScript and Remove Unused CSS. These are the two settings most likely to need an exclusion later. I'll snapshot first — apply?"* Each preset maps to a concrete set of real keys (e.g. Balanced = `minify_css`, `minify_js`, `js_defer`, `enable_font_optimization`, `self_host_google_fonts`, `font_display_swap`, `enable_cache_preload`; Aggressive adds `combine_css`, `combine_js`, `js_delay`, `remove_unused_css`, `critical_css`; WooCommerce force-excludes cart/checkout/account from `rejected_uri` and disables page cache for those). Per-setting **risk labels** ("higher breakage risk") ride along on the diff. This is the research's #1 recurring ask (`ai-landscape.md` + all dossiers) delivered *in our voice*.

### 6.3 The AI Performance Diagnostic ("Explain & Fix") — the headline
This **IS** the home screen — it's not surfaced, it's the substrate. Pipeline (matching `ai-landscape.md` §2.1, attaching at `dashboard.php:run_diagnostic()` which already has nonce+cap+JSON plumbing):
1. **Collect ground truth** — PageSpeed Insights v5 for homepage + 2–3 key URLs + SwiftPress's own settings map + environment facts (nginx, PHP version, WooCommerce?, object cache?). No page HTML, no PII leaves the site.
2. **Stream the narrative** — the LLM returns structured JSON (`summary`, `findings[]`, `recommended_changes[]`), and the UI renders `summary` as the streaming prose and each finding as an Explain&Fix card with its `evidence_metric`, `severity`, `confidence`.
3. **Action whitelist + server-side validation gate** — every proposed change is validated against the real setting schema (key exists? value in range? not mutually exclusive?) *before* it's shown as appliable. The LLM proposes; SwiftPress disposes. Invalid changes are dropped, never rendered.
4. **One-click apply** — writes through the existing `save_configuration()` path (inherits secret-stripping, opcache invalidation, advanced-cache regen), after a settings **snapshot**.
5. **Reversible + re-audit** — History tab restores any change; re-audit re-sweeps the score ring to prove the delta.

The conversational framing is the differentiator: this is the only vision where the diagnostic is *the page you land on*, mid-sentence, not a tab you click into.

### 6.4 The nginx config generator (our standout)
Given its own top-level **`Server`** screen — because we're the only product that ships this (`wp-rocket.md`/NitroPack don't; `code-audit.md` §7), it deserves prominence, and the AI ties it in. Layout: server-block detection up top (the AI reads `$is_apache` and the diagnostic's environment facts), a **side-by-side**: left = the generated config in a **JetBrains Mono** block with syntax tinting and a fat **Copy** button; right = a plain-language explainer of *what each block does* ("this `try_files` line serves the static cache file directly, skipping PHP"). Improvements the research flagged, surfaced as UI:
- **Per-stack variants** — a segmented toggle for `php-fpm socket` vs `tcp`, fixing the hardcoded `fastcgi_pass unix:/var/run/fastcgi.sock` (`code-audit.md` §7).
- **Mobile/gzip parity** — generate the `$pc_ua`/`$pc_enc` wiring the current nginx output omits.
- The Ask bar reaches here too: *"give me the nginx config"* deep-links to `Server` with the block pre-rendered and copied. The AI's diagnostic can also say *"You're on nginx and your cache rules aren't installed — here's the exact block to paste"* with a one-click jump.

### 6.5 The currently-orphaned settings (the big win)
These exist in `utils.php` defaults but are invisible in today's 5-section UI (`code-audit.md` §8). This vision surfaces them **two ways**: the AI recommends them in context, *and* they get a real home under `Tune → chapters`. Concretely:

| Orphaned capability | Real keys | Surfaced via narrative as… | Manual home |
|---|---|---|---|
| **CDN / Cloudflare** | `enable_cdn`, `enable_cloudflare`, `cloudflare_api_token/email/api_key/zone` | *"Your assets are served from your origin in Dublin; a CDN would cut latency for far-away visitors."* → connect card | `Tune → Integrations` |
| **Image optimizer + format** | `enable_image_optimization`, `image_optimizer_preferred_format`, `add_missing_image_dimensions` | *"3 images ship as JPEG; AVIF would save 240 KB."* (conversion stays **algorithmic** — `ai-landscape.md` §2.5) | `Tune → Media & Fonts` |
| **Critical CSS** | `critical_css`, `critical_css_*` | finding #2's Fix; **algorithmic generation**, LLM only explains/triages | `Tune → Assets` |
| **Remove Unused CSS** | `remove_unused_css`, `ucss_safelist`, `ucss_excluded_files` | *"142 KB unused"* finding; if a later regression, AI suggests safelist entries (never writes CSS) | `Tune → Assets` |
| **Self-host GA / FB Pixel** | `enable_google_tracking`, `enable_fb_tracking` | *"Google Analytics adds a render-blocking third-party request; serve it locally?"* | `Tune → Integrations` |
| **Heartbeat control** | `enable_heartbeat`, `heartbeat_*_status/interval` | *"Your admin pings the server every 15s; I can throttle that to cut CPU."* | `Tune → Integrations` |
| **DNS-prefetch / preconnect** | `prefetch_dns`, `preconnect_resource` | auto-suggested from detected third-party origins in the audit | `Tune → Preload & Hints` |
| **Link prefetch on hover** | `prefetch_links` | *"I can prefetch links as visitors hover — snappier navigation."* | `Tune → Preload & Hints` |
| **LCP optimization** | `enable_lcp_optimization` | rides finding #1 (hero image) | `Tune → Preload & Hints` |
| **Varnish / async clean / dev mode** | `enable_varnish`, `varnish_ip`, `async_cache_cleaning`, `dev_mode` | environment-detected; dev mode gets a persistent amber banner when on | `Tune → Server`, `Tune → Caching` |

The point: **the orphaned settings stop being orphaned because the AI has a reason to mention each one.** A capability the model can't motivate from the audit still has a manual home, but most surface naturally as findings.

### 6.6 Per-URL cache inventory ("Inspect")
A dedicated screen, but styled as an **editorial index/ledger**, not a database grid: a typeset table (Public Sans, tabular-nums) of cached URLs with columns **URL · status (warm/cold/stale dot) · age · size · score**. The twist that fits this vision: each row is **selectable into the Ask bar** — pick a URL and ask *"why is this page slow?"* and the diagnostic runs scoped to that URL, returning a per-page Brief. A per-page detail drawer exposes the per-page metabox controls (LCP preload image, disable-cache-for-this-URL) the research wants. Bulk actions (purge selected, re-warm) sit in a sticky footer. Data comes from walking the cache dir + the preloader stats (`SitemapPreloader::get_preload_stats()`).

---

## 7. Recommended tech stack + honest justification

### Recommendation: **Preact + HTM + Zustand**, one webpack entry, lazy-loaded routes — NOT full React, NOT Alpine.

| Concern | Decision |
|---|---|
| **Framework** | **Preact 10** (3KB) via `@wordpress/element`'s aliasing *or* a direct Preact alias in the existing 10up-toolkit/webpack config. The repo already compiles `@wordpress/element` (React) — so a component tree is a solved build problem (`code-audit.md` STACK REALITY). We alias `react`/`react-dom` → `preact/compat` to keep the React DX and the WP ecosystem while shipping Preact's footprint. |
| **Templating** | JSX (via the existing Babel/10up pipeline). |
| **State** | **Zustand** (~1KB) for the conversation thread, diagnostic results, and settings draft. The thread *is* the app state; a tiny store beats prop-drilling and beats Redux bloat. |
| **Streaming** | The diagnostic streams via a server-side OpenRouter call relayed over a `ReadableStream`/SSE from a WP REST route; the client appends tokens to the thread store. No client AI SDK — the key never reaches the browser (`ai-landscape.md` §3.2). |
| **Styling** | The design-token CSS above + hand-authored component CSS (CSS Modules through 10up-toolkit). **No Tailwind, no CSS-in-JS runtime** — tokens + modules keep the payload lean and the aesthetic controlled. |
| **Routes** | `Brief / Inspect / Tune / Server / History / Copilot` are lazy `import()` chunks. Only **Brief** (the home) is in the initial bundle. |
| **Markdown** | The AI narrative needs minimal rich text (bold, lists, inline `code`, links). Use a ~3KB micro-markdown renderer (e.g. `snarkdown`) with strict sanitization — *not* a full markdown stack. |

### Why a richer (Preact) stack here — and the honest counter-argument

This is the one vision where I *argue for* a component framework, because the **signature interaction demands it**: a streaming conversational surface with token-by-token reveal, an append-only thread that doubles as the undo log, inline diff cards with optimistic apply states, and live re-audit deltas. That is genuine application state with real-time updates — exactly what a reactive view layer is for. Hand-rolling streaming DOM diffing + thread state + optimistic UI in Alpine/vanilla (the path the other visions can take) would end up *reinventing a worse Preact*. Vision B (a control-room dashboard of mostly static widgets) and Vision C (a linear wizard) can credibly ship on Alpine + design-system CSS; **the conversational hero cannot, cleanly.**

**The counter-argument, stated honestly (it's a *performance* plugin):** shipping a JS framework into the admin of a plugin whose entire brand is "lean, no-bloat" (`ai-landscape.md` §5) is a real tension. The mitigations and the honest accounting:
- **It's admin-only.** None of this touches the frontend or a visitor's Lighthouse score (the thing the plugin is judged on). The frontend cache path stays zero-JS, zero-LLM. The brand risk is *perception in the wp-admin*, not measured site performance.
- **Bundle budget (realistic):** Preact+compat ~4–5KB + Zustand ~1KB + snarkdown ~3KB + app code. Target **initial (Brief) chunk ≤ 45KB gzipped JS + ≤ 12KB CSS**, other routes lazy. For comparison, WP Rocket and NitroPack ship far heavier admin bundles. This is defensible *if we hold the budget* — and a CI bundle-size gate must enforce it.
- **Graceful no-JS / no-key floor:** the server renders the Pulse strip and a static "Standard recommendations" Brief without the bundle; the React layer is an enhancement. The plugin's settings remain editable even if the bundle fails to load (the `Tune` pages can server-render forms posting to the existing `save_configuration()` path).

**If the owner vetoes any framework in the admin:** this vision degrades to "editorial CSS + Alpine.js + a vanilla streaming fetch," losing the optimistic diff cards' polish but keeping the narrative and Ask bar. It is *viable* on Alpine — it just won't feel as alive. I'm recommending Preact because the signature move is the product, and the signature move wants reactivity. That trade should be made with eyes open.

### Bundle-impact summary
~45KB gz initial / ~12KB CSS, route-split, admin-only, zero frontend impact, CI size-gated. Heavier than Vision C's Alpine approach by ~25–30KB on the home screen; lighter than any SaaS competitor's admin; justified solely by the conversational hero.

### Rough component inventory

```
App shell
├─ AskBar                 (hero input + streaming controller, ⌘K focus)
├─ PulseStrip             (score mini + CWV dots + cache state + re-audit)
├─ NavRail                (collapsible icon nav)
│
Brief (home)
├─ BriefColumn            (editorial narrative container, streaming text)
│  ├─ NarrativeStream     (token-by-token Fraunces prose + markdown)
│  └─ FindingCard ×N      (Explain&Fix: headline, evidence, confidence, Fix/Explain)
│     ├─ ConfidenceMeter
│     ├─ DiffPreview      (real setting keys: from → to, risk labels)
│     └─ ApplyButton      (optimistic, snapshot-aware)
├─ MarginRail
│  ├─ ScoreInstrument     (THE signature component, §5)
│  ├─ CitedMetrics        (LCP element, unused CSS, blocking JS)
│  ├─ PresetSelector      (segmented; narrates consequence)
│  └─ EnvNote             (nginx/Apache, dev-mode banner)
└─ FixAllFooter           (apply-all w/ estimate + reversible note)

Inspect
├─ UrlLedger              (typeset table, sortable, row→Ask)
├─ UrlDetailDrawer        (per-page metabox: LCP preload, disable-cache)
└─ BulkActionBar

Tune
├─ ChapterNav             (Caching / Assets / Media&Fonts / Preload / Integrations / Server)
├─ SettingRow            (label + control + inline help + "ask about this")
├─ ConnectCard           (Cloudflare/CDN/Varnish credential capture)
└─ SaveBar               (manual save → existing save_configuration())

Server
├─ StackDetector
├─ ConfigBlock           (mono, syntax-tinted, copy)
├─ ConfigExplainer       (plain-language per-block)
└─ StackVariantToggle    (fpm socket / tcp)

History
├─ ChangeTimeline        (AI + manual changes)
└─ RevertControl         (snapshot restore)

Copilot
├─ KeyField              (BYO OpenRouter, write-only, masked)
├─ ModelSelect           (default cheapest viable)
├─ SpendCapMeter         (monthly cap + usage)
└─ PrivacyDisclosure     (what leaves the site, DPA note)

Primitives
└─ Button · Segmented · Toggle · Tag · Tooltip · Drawer · Toast · StatusDot · Sparkline
```

---

## 8. Motion & shape (the deliberate bits)

- **Load reveal (the signature moment):** on Brief open, the score ring sweeps once (§5), the numeral counts up, and the narrative **streams token-by-token** as if being written now — finding cards fade+rise (`translateY(8px)→0`, `--sp-ease-out`) staggered `--sp-stagger` apart *as the prose "reaches" them.* This is the load reveal that sells "the plugin looked at your site and is telling you about it." Under `prefers-reduced-motion`, everything renders instantly, fully formed.
- **Apply feedback:** clicking **Fix this** optimistically collapses the card into a one-line "✓ Applied · undo" row (amber→green tick), and the score ring nudges toward its projected value with a soft pulse, settling on the real number after re-audit.
- **Hover/focus:** finding cards lift one elevation step on hover; the Ask bar gets the `--sp-elev-accent` aura on focus (amber glow) — the only place the glow appears at rest, marking it as *the* input.
- **Shape language:** editorial = **squarer**. Cards/panels use `--sp-r-md` (8px); only truly pill-shaped things (status dots, the segmented preset control, tags) round fully. No `rounded-xl`-on-everything. The narrative column has a left hairline rule (`--sp-line`) like a manuscript margin — a small, consistent editorial tell.

---

## 9. Top strengths

1. **Category-of-one, made visceral.** The research says no competitor translates an audit into plain language + applied fixes (`ai-landscape.md` §0). This vision doesn't just *include* that feature — it **is** that feature, from the first second. The demo writes itself: open panel → it's already explaining your site → click Fix → score climbs.
2. **Solves the real user problem (interpretation), not the surface one (toggles).** WP Rocket hides the work; Perfmatters exposes 200 switches. We delete the "which toggle maps to this Lighthouse line?" cognitive load entirely. That's the thing a non-technical owner actually pays for.
3. **A genuinely distinctive aesthetic.** The editorial-console direction (Fraunces serif voice + warm-graphite + single sodium-amber accent + typeset score) is nothing like stock-WP, nothing like the white+purple SaaS cliché the brief forbids, and nothing like the other two visions. It's *authored*.
4. **The orphaned settings stop being orphaned by design.** Because the AI has a contextual reason to mention CDN, image format, GA self-hosting, Heartbeat, prefetch, etc., features that are invisible today (`code-audit.md` §8) surface *naturally* — not as one more tab the user must discover.
5. **The standout (nginx generator) gets star treatment** with per-stack variants and a plain-language explainer, fixing the real bugs (`code-audit.md` §7) while being something rivals literally don't ship.
6. **Safe by construction.** Whitelist + server-side validation gate + snapshot + reversible History + re-audit loop means the "AI breaks my site" fear is structurally defused (`ai-landscape.md` §3.6, §4). The conversation *is* the audit trail.
7. **Graceful AI-optional floor.** Zero-key users get the same findings (deterministic) and a fully functional plugin — AI is a pure upgrade, preserving the lean identity.

---

## 10. Honest risks / weaknesses

1. **It lives or dies on AI quality and latency.** The home screen *is* the diagnostic. If PSI is slow (multi-second), the model stalls, or the prose is bland, the hero feels broken in a way a static dashboard never would. Mitigation: aggressive response caching keyed on (metrics+settings) hash (`ai-landscape.md` §3.4), optimistic skeleton + streaming so it never looks frozen, a hard timeout that falls back to the deterministic Brief, and a strong static first-paint so the page is useful *before* the model responds. **This is the vision's biggest bet.**
2. **"No Save button on home" is opinionated and will unsettle some users.** Power users expect a settings form. Mitigation: the `Tune` pages *are* conventional forms with a Save bar; the conversational model is the default, not the only, path. Still, this is a deliberate UX gamble that QA and a beta cohort must validate.
3. **Heaviest admin bundle of the three visions.** A JS framework in a "no-bloat" plugin's admin is a brand tension, even admin-only. Mitigation: route-splitting, a CI bundle-size gate, server-rendered no-JS floor, and the honest framing that frontend Lighthouse is untouched. But it *is* the vision asking for the most JS, and that must be owned (§7).
4. **Narrative trust / hallucination perception.** Even with the validation gate guaranteeing every *applied* change is safe, plain-language prose can still be *plausibly wrong* in its explanation ("it said the font was the problem but it was actually the image"). Mitigation: every claim cites its evidence metric (traceable), confidence is shown, changes are reversible, and re-audit exposes non-improvements — but a confidently-worded wrong explanation is a credibility risk a terse dashboard avoids.
5. **More expensive to build than the other two.** Streaming infra, the conversation store, optimistic diff apply, the SSE relay, and the editorial component set are real engineering beyond "render settings nicely." Mitigation: the pipeline reuses existing seams (`run_diagnostic()`, `save_configuration()`, the OpenRouter integration the owner already has), and the deterministic fallback is shippable first with the prose layered on. But honestly: this is the highest-effort vision.
6. **Editorial type at small sizes / i18n.** Fraunces is gorgeous at display sizes but must never be used for dense UI (handled — Public Sans does chrome), and a serif-led design needs care for non-Latin locales (CJK, Arabic) where Fraunces has no coverage; the system must fall back gracefully per-locale. A solvable but real localization caveat.
7. **Accessibility of a streaming, motion-heavy hero.** Token streaming + staggered reveals + a count-up gauge need correct `aria-live` regions, reduced-motion paths (provided in the CSS), and screen-reader handling so the "writing now" effect doesn't become an accessibility liability. Built into the plan, but it's extra surface to get right.

---

## 11. One-line summary

**SwiftPress "Foreman": you open the panel and it has already read your site and is telling you — in real, typeset prose with an amber speed-light accent — exactly what's slow and what to do, with a command bar as the primary verb and every fix one reversible click away. The only vision where the AI is the architecture, not a feature.**

---

*File: `F:/Claude/powered-cache-revamp/proposals/vision-ai-first.md`. Grounded in `research/code-audit.md` (architecture, AI hooks, nginx, UI assessment), `research/ai-landscape.md` (AI strategy, BYO-key architecture, safety spine), the real setting keys in `includes/utils.php`, and competitor dossiers (`wp-rocket.md`, `nitropack.md`, `perfmatters.md`).*
