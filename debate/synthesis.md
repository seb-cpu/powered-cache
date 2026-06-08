# SwiftPress Admin Revamp — Debate Synthesis (decision input for the judge)

**Synthesizer:** debate referee feeding the human judge
**Date:** 2026-06-08
**Inputs read in full:** `proposals/vision-mission-control.md`, `proposals/vision-quiet-premium.md`, `proposals/vision-ai-first.md`, `proposals/ai-architecture.md`, `research/code-audit.md`, `research/ai-landscape.md`.

This document does not re-argue the visions. It ranks them against the brief, names the one direction to build (a specific hybrid), fixes the cross-vision must-haves, lists what to cut, settles the stack, and isolates the genuine forks only the owner can call.

The brief's scoring axes, restated so the ranking is auditable: **(1) super-modern + non-generic**, **(2) great UX for a 200-setting plugin aimed partly at non-technical owners**, **(3) performance-brand-true (the admin must not betray a "lean/fast" product)**, **(4) AI-differentiated (the Explain-&-Fix headline must feel category-defining)**, plus the owner's hard design constraints (one aesthetic + one signature move, characterful display type, no white+purple SaaS cliche, dark + light required, deliberate motion/shape).

---

## 1. Ranking of the three visions

### #1 — AI-First "Foreman" (Editorial Console) — *best fit, with one structural edit*

**One line:** The only vision where the category-defining feature *is* the architecture instead of a tab — you open the panel and it has already read your site and is telling you, in typeset prose with one sodium-amber accent, exactly what's slow and what to do.

Why it wins on the brief's own axes:
- **AI-differentiation (axis 4) is maximal and structural.** The research is unambiguous (`ai-landscape.md` §0–1, §5): no competitor translates an audit into plain language + applied fixes; that gap *is* the headline. The other two visions treat Explain-&-Fix as a strong feature inside the panel; Foreman makes it the first sentence on the home screen. For a product whose stated category-of-one bet is the AI diagnostic, leading with it is the most on-strategy choice available. The demo writes itself: open → it's already explaining → click Fix → score climbs.
- **Non-generic (axis 1) is the strongest of the three.** Fraunces-as-the-AI's-voice + warm graphite + a single speed-light amber + a *typeset* score is unmistakable and impossible to confuse with stock-WP, the SaaS cliche, or the sibling visions. It reads as *authored*.
- It correctly keeps CSS algorithmic and the LLM as the communication/triage layer (matches `ai-landscape.md` §2.3), and its safety spine (whitelist → server-side validation gate → snapshot → reversible History → re-audit) is exactly the `ai-architecture.md` design.

Why it is #1 *with an edit*, not unconditionally: its two boldest stakes are also its biggest risks (see §6) — **"no Save button on the home screen"** and **"the home screen is empty/broken until the model responds."** Both are fixable without losing the soul of the vision (keep the editorial Brief as the hero, but render a deterministic static Brief + the Pulse strip on first paint, and keep conventional Save bars on the Tune pages — both of which the proposal already concedes in §6.2/§10). With that edit it is the best embodiment of the brief; without it, it is the riskiest. The recommendation in §2 bakes the edit in.

### #2 — Mission Control (Instrument Cluster) — *most defensible, most "ships clean"*

**One line:** A dark glass-cockpit where the Performance Score is a swept-needle speedometer ringed by six live mono readouts — the brand and the build agree (lean vanilla/Alpine), and status clarity is the entire design rather than a feature.

Why it's a very strong #2 and the safe pick:
- **Performance-brand fit (axis 3) is best-in-class** — the lean vanilla/Alpine + CSS stack lets the admin itself *demonstrate* "fast and light," and the aesthetic argument and the engineering argument point the same way. That coherence is rare and valuable.
- The signature (score-as-swept-instrument) is genuinely non-interchangeable and no competitor renders speed as an instrument.
- It has the broadest, most concrete surface coverage of the three — URL Inventory as a "flight manifest," the nginx generator as a hero, per-setting risk chips, the orphaned-settings home table. It is the most *complete* product spec and the lowest delivery risk.

Why it lands behind AI-First on the brief: it treats the AI diagnostic as one system among nine (a rail item), so the category-defining feature is *present and well-executed* but not *foregrounded* the way the brief's emphasis ("HEADLINE AI FEATURE") implies. And its honest risk — dark-first + glow tipping into "gamer RGB," plus Bloomberg-density intimidating the non-technical owner the AI feature targets — is real (the vision flags both). It is the better choice if the judge weights *shipping a coherent, low-risk, status-dense cockpit* over *betting the home screen on the AI narrative.*

### #3 — Quiet Premium (The Number Is The Hero) — *most beautiful, least load-bearing as a whole-panel system*

**One line:** Soft atmospheric minimalism where an oversized Fraunces Performance Score floats in dramatic negative space and the 200 toggles are progressively disclosed — the deliberate anti-WP-Rocket, with a Linear/Stripe polish bar.

Why it's third and *not* a knock on its quality:
- Its signature move — the giant editorial score numeral — is arguably the single most striking *screenshot* of the three, and its restraint is genuine UX (least intimidating entry to a 200-setting plugin). The Preact + signals + bespoke-CSS stack reasoning is the sharpest of the three documents.
- But as a *whole-panel* organizing principle it is the weakest fit for *this* brief: the brief's headline is the AI diagnostic, and Quiet Premium's headline is *negative space around a number*. The score-as-hero is a beautiful container that the other two visions can (and should) absorb, but on its own it under-uses the AI edge and carries the highest adoption risk in the WordPress context ("where is everything?" — the vision itself names this as its #1 risk). Its restraint is a superb *ingredient*; it is the least complete *meal*.

**Net:** AI-First is the most on-brief, Mission Control is the most defensible, Quiet Premium is the most polished surface. The three are not far apart in quality — they are deliberately staked at different corners — which is exactly why the right answer is a hybrid that takes the load-bearing idea from each.

---

## 2. Recommended direction to build — a specific named hybrid

> **"Foreman shell + AI-First Brief home + Quiet-Premium restraint on the score and settings + Mission-Control instrumentation on Inspect/Server."**
>
> Working name to carry forward: **the Editorial Console.**

Concretely, what comes from where:

**From AI-First "Foreman" (the spine — adopt as the primary architecture):**
- The **AI Brief as the home screen**, led by the streaming plain-language diagnostic with inline Explain-&-Fix finding cards (headline, evidence-metric citation, confidence, Fix / Explain-more). This is the category-defining surface and it leads.
- The **persistent Ask bar** (⌘K / `/`) as the natural-language command surface and the **History timeline** as the reversible audit log. The Ask bar is the signature move.
- The **"AI narrates the consequence" pattern** for presets and risky toggles (selecting Aggressive returns a confirmable diff with per-setting risk, never a silent flip).
- The **IA inversion**: lead with diagnosis (`Brief`), make settings the destination (`Tune` chapters), give the nginx generator its own home (`Server`), per-URL inventory its own home (`Inspect`), and the BYO-key/budget panel its own home (`Copilot`).

**From Quiet Premium (the restraint — adopt to de-risk the AI-First aesthetic):**
- **"The number is the hero" applied to the score instrument specifically** — render the Performance Score as the large editorial Fraunces numeral with a draw-on-load arc and count-up, reused at hero size on a first-run/empty state and at medium size in the post-apply before→after card. (Both AI-First and Quiet Premium already pick Fraunces; this convergence is a gift — see §3.)
- **Progressive disclosure + "Refine individually" on the settings pages**, so the 200 toggles are present and fully editable but never assault the user. This is the antidote to the audit's "Advanced = 9-textarea graveyard" complaint and the correct home for the orphaned settings as labeled cards with the right input affordance (chip-input over raw textareas, segmented controls, connect-panels).
- **Calm, eased motion and atmospheric depth** as the polish bar (Linear/Stripe/Vercel), tuning AI-First's editorial console toward "expensive and quiet" rather than "busy."

**From Mission Control (the instrumentation — adopt where density is genuinely wanted):**
- **The per-URL cache inventory as a real, dense, sortable/virtualized manifest** (status/age/size/score, per-row purge, "why excluded?" popover) on the `Inspect` screen. This is the one place high density is correct, and Mission Control's manifest treatment is the best-specified.
- **The nginx generator as a hero with per-stack variants, copy-to-clipboard, line-numbered mono, and a validation/"reload nginx?" sidebar** on `Server`. All three visions converge here; Mission Control's and AI-First's treatments are nearly identical — build the union (detected-stack facts + socket/TCP toggle + the plain-language per-block explainer from AI-First + the validation sidebar from Mission Control).
- **Per-setting risk chips and the explicit preset diff** (Safe/Balanced/Aggressive/WooCommerce with what-flips + risk labels) — the research-consensus #1 ask, rendered in the calm editorial register rather than the cockpit register.

**The structural edits that make the hybrid safe (these are non-negotiable):**
1. **First paint is never empty and never blocks on the model.** The server renders the Pulse strip + a deterministic static Brief (`RulesFallback`) immediately; the LLM prose streams in as an enhancement. This converts AI-First's single biggest risk (a frozen-looking hero) into a non-issue and simultaneously delivers the no-key floor for free.
2. **Conventional Save bars survive on the Tune pages.** The conversational/confirm-diff model is the *default* path, not the *only* path. This neutralizes the "no Save button unsettles power users" risk while keeping the bold home-screen stance.
3. **One dark theme, authored first; one light theme, shipped.** Resolve the palette fork in §6 before building — all three propose a warm-or-cool dark base; the hybrid must pick one (recommendation: warm graphite + amber from AI-First, because the editorial voice reads best on warm ink and amber is the most ownable, least-cliche accent of the three).

Why a hybrid rather than one pure vision: the three were deliberately staked at non-overlapping corners, so each owns exactly one load-bearing idea the others lack — AI-First owns *the AI-as-architecture bet*, Quiet Premium owns *restraint that tames 200 settings*, Mission Control owns *instrument-grade status density and the lean-stack brand argument*. The brief demands all three (headline AI **and** great UX for non-technical owners **and** performance-brand-true). No single vision delivers all three; this hybrid does, and the seams are clean because the AI engine (`ai-architecture.md` §8) is explicitly UI-agnostic — the same payload drops into any shell.

---

## 3. Must-have elements (cross-vision + research consensus — appear regardless of final direction)

These are the points where two or three visions and/or the research independently converge. They are the floor.

**AI / headline (all three visions + `ai-landscape.md` + `ai-architecture.md`):**
- **AI Performance Diagnostic "Explain & Fix"** as the headline: PSI/Lighthouse ground truth → LLM plain-language `summary` + `findings[]` (each with an evidence metric) + `recommended_changes[]` mapped to **real setting keys**, one-click apply through the **existing `sanitize_options()` → `save_configuration()` path**, with **snapshot + one-click undo** and a **re-audit before→after**.
- **The safety spine, exactly as specified:** action whitelist (~27 keys), **server-side deterministic validation gate** (drop-anything-invalid, re-derive `from` from real settings, resolve conflicts like RUCSS-vs-combine), structured-JSON output, confirm-before-apply. The LLM proposes; SwiftPress disposes.
- **BYO OpenRouter key, server-side only, never to the browser, never in `sp-config/*.php`, never in export** — reuse the `cloudflare_*` strip precedent (`Config.php:630`, `dashboard.php:158`) and store the key in its **own** encrypted option (`swiftpress_ai_secrets`), with the constant-override tier (`SWIFTPRESS_OPENROUTER_KEY`).
- **Graceful no-key / API-down floor:** the deterministic `RulesFallback` produces the same change-set minus prose; the plugin is 100% functional with zero AI. AI is a pure upgrade layer.
- **CSS stays algorithmic** (Critical CSS / RUCSS); the LLM only explains/triages and suggests safelist entries — never emits CSS.
- **Cost controls:** monthly spend cap (default ~$2), response cache keyed on hash(metrics+settings), debounce, no per-pageview calls ever.

**Information architecture & status (all three + audit §8):**
- **A real status/overview home surface** (the audit's #1 gap: "no dashboard, no metrics, no status surface"). Replace the five stock accordions.
- **A left nav rail** with at most ~6–8 destinations and a live status indicator per item; a **persistent slim top bar** with a global Clear Cache affordance + secondary purge actions (replacing the current loud header).
- **Every orphaned setting gets a labeled home with the right input** — Cloudflare/CDN, image optimizer + preferred format, Critical CSS/RUCSS, self-host GA/FB, Heartbeat matrix, DNS-prefetch/preconnect as **chip inputs** (not raw textareas), link-prefetch-on-hover, Varnish, async-clean, dev mode. **No undifferentiated "Advanced" textarea graveyard.**
- **Honest state for stub features:** Critical CSS / RUCSS currently have *no generator* (audit §5). Show "engine: pending / coming with the engine," not a dead checkbox. (Trust problem the audit explicitly flags.)

**Signature score instrument (all three):**
- The **Performance Score as a custom, animated instrument** (count-up + draw-on-load arc/needle), **not** a flat grade chip. Field-vs-lab labeled; never animate an empty gauge (show an "acquire telemetry / run first diagnostic" empty state). Re-sweeps after an apply to prove the delta.

**Presets (all three + research consensus):**
- **Safe / Balanced / Aggressive / WooCommerce presets** as explicit, named maps of real keys, each with **a diff of exactly what flips + per-setting risk labels + a one-line tradeoff**, applied through the real save path with a snapshot. Never a mystery bundle.

**The nginx generator as a standout (all three + audit §7):**
- **Hero treatment, not a buried download:** detected-stack facts, **per-stack variants (socket vs TCP) as a real field** (fixes the hardcoded `/var/run/fastcgi.sock`), **copy-to-clipboard + download**, line-numbered mono, a plain-language per-block explainer, and a validation/"did you reload nginx?" sidebar. Fix the dropped **mobile-UA `$pc_ua` and gzip `$pc_enc`** wiring the current nginx output omits.

**Per-URL cache inventory (all three + research consensus):**
- A **dense, sortable, searchable, virtualized** table (status / age / size / per-URL score / variants) with per-row purge/refresh and a "why excluded?" affordance, reading the real cache dir + preloader stats. The agency "prove it's working" surface.

**Craft constraints (brief + all three):**
- **Characterful display type** (all three independently avoid Inter/Roboto for display; two of three pick **Fraunces** — strong signal). **Self-host the admin fonts** through the plugin's own FontOptimizer (dogfooding; no Google CDN call from an admin page).
- **Dark theme authored first + a light theme shipped**; respect `prefers-color-scheme` and `prefers-reduced-motion`; scope everything under a single root class so wp-admin is untouched.
- **Deliberate motion** (one choreographed load reveal, meaningful hover/focus) and **deliberate shape language** (editorial = squarer; no rounded-xl-on-everything).
- **WCAG AA** with color never the sole signal (label + shape), an ARIA `meter`/text-equivalent for the score gauge, and `aria-live` for any streaming text.

---

## 4. Ideas to reject (over-engineered, off-brand, low-ROI, or unsafe)

1. **Pure "no Save button anywhere" (AI-First's hardest stake).** Keep conventional Save bars on the Tune pages. The conversational model leads; it does not *replace* the form for power users. Rejecting the absolutist version removes the single most-cited adoption risk at almost no cost to the vision.
2. **A home screen that blocks on the LLM / PSI before first paint.** Reject any design where the hero is blank until a multi-second API round-trip returns. Static deterministic Brief + Pulse strip render instantly; AI prose streams in. (Also the no-key path, so it's free.)
3. **Full React (`@wordpress/element`) in the admin.** ~45KB runtime before app code, in a plugin whose pitch is "lean." All three visions independently reject it. Settled in §5.
4. **Any heavy client library that betrays the brand:** charting libs (Chart.js/Recharts, 50–150KB — use inline SVG sparklines + the SVG score ring), Tailwind (utility soup fights the bespoke editorial look and ships a build/CDN cost), MUI/Chakra (huge, generic, on-brand-hostile). The aesthetic depends on *bespoke* CSS.
5. **LLM writes/prunes CSS (Critical CSS / RUCSS generation by model).** Explicitly proven wrong by the research (`ai-landscape.md` §2.3): slower, costlier (50–200k tokens), unsafe (a dropped "used" selector breaks layout), non-deterministic/untestable. The LLM explains and suggests safelist entries; algorithms do CSS.
6. **Auto-applying free-text exclusion keys (`rejected_uri`, safelists) from the LLM.** Keep these **suggest-only / copy-to-confirm** (`ai-architecture.md` §3.1) — text exclusions are the high-blast-radius footgun (over-exclusion mis-caches a cart page). Booleans/scalars only in the auto-apply whitelist.
7. **Eager per-URL PSI scoring across a 12k-page inventory.** Infeasible and expensive. The inventory "score" column must be lazy, on-demand, cached, and rate-limited. Both Quiet Premium and AI-First flag this; honor it.
8. **Glow on idle chrome (Mission Control's self-named risk).** If the cockpit instrumentation is borrowed, borrow the *discipline*: glow only on live arc/needle/critical dots, never on resting UI. Undisciplined neon reads as "gamer RGB" and cheapens the brand fast. (Largely moot if the hybrid takes the warm-editorial register over the full cockpit register — but the Inspect/Server instrumentation must still obey it.)
9. **A proprietary, non-reconcilable Performance Score.** If "the number is the hero," the number must be defensible (PSI field+lab, clearly labeled mobile/desktop, honest "couldn't measure" states for staging sites). Reject inventing a score users can't reconcile with PSI — that is NitroPack's documented "gaming Lighthouse" reputational trap.
10. **Per-page metabox / Script Manager in v1.** Both appear in the research consensus and are good, but they are *expansion surface*, not the headline. Defer to a fast-follow; do not let them bloat the first build. (Inspect's per-URL detail drawer can host the per-page metabox later.)
11. **Alt-text / vision AI in v1.** The one content-leaking feature (`ai-landscape.md` §2.5); crowded market; tangential to "performance." Separate opt-in module later, with its own disclosure. Not the headline, not v1.
12. **A second brand accent / any white+purple-indigo SaaS gradient.** Explicitly banned by the brief; all three comply. Hold the line: one dominant accent, used on ~5% of surface.

---

## 5. Stack recommendation

**Build: Preact (aliased for `react`/`react-dom` via the existing 10up-toolkit/webpack config) + a tiny signals/Zustand-class store, with a hand-authored design-system CSS layer carrying the entire aesthetic. No full React. No Tailwind/component library. The few genuinely-static settings sub-pages may stay server-rendered PHP partials posting to the existing save path.**

Why Preact and not the other two contenders, decided on *this* product's facts:

- **The chosen home screen is genuinely stateful, which breaks the tie against Alpine/vanilla.** The hybrid's hero is the AI Brief: a **streaming token-by-token narrative**, an **append-only thread that doubles as the undo log**, **inline diff cards with optimistic apply states**, and a **live re-audit delta**. That is real application state with real-time updates. Mission Control's all-vanilla/Alpine case is sound *for Mission Control* (a cockpit of mostly-static instrument widgets) — but the moment the home screen is a conversational, streaming surface (the AI-First spine we're adopting), hand-rolling streaming DOM diffing + thread state + optimistic UI in Alpine ends up reinventing a worse Preact. Both AI-First and Quiet Premium independently reach Preact for exactly this reason; that convergence, *given we're taking the AI-First home*, is decisive.
- **It honors the performance brand (axis 3), which kills full React.** Preact + compat is ~4–6KB vs React+ReactDOM's ~45KB before app code. Target **initial (Brief) chunk ≤ ~45KB gz JS + ≤ ~12KB CSS**, route-split so only Brief is in the initial bundle, **admin-only** (zero frontend/Lighthouse impact), enforced by a **CI bundle-size gate**. Shipping a 45KB React runtime in a "lean" plugin's admin is a credibility tax all three visions refuse to pay.
- **It is a drop-in for the repo, not a new paradigm.** The repo already compiles `@wordpress/element` (React-compatible) through 10up-toolkit; a one-line `react → preact/compat` alias keeps React DX and access to any battle-tested `@wordpress/components` (e.g. a `Popover`) while shipping Preact's footprint. The PHP side is untouched: same `wp_ajax_*`/`admin-post` endpoints, same nonces, same `sanitize_options()` save path; the app is a thin SPA over the existing seams (`ai-architecture.md` §8 confirms the engine is UI-agnostic).
- **The design system is CSS, not a framework.** All tokens + components authored as bespoke `sp-*` CSS scoped under one root class. This makes the "expensive" look **framework-independent and durable**, and keeps the door open to server-rendering the simplest settings sub-pages without the bundle (the graceful no-JS floor for `Tune`).

Honest counter and the guardrail: a JS framework in a no-bloat plugin's admin is a real brand *tension* even admin-only. The mitigation is the **hard CI size gate + route-splitting + a server-rendered no-JS floor for the settings pages + the streaming/no-key static first paint**. If the owner vetoes *any* framework in the admin, the fallback is Mission Control's stack (design-system CSS + Alpine + a vanilla streaming fetch) — viable, but the conversational hero will feel less alive, and that trade should be made with eyes open. **This is also an open question for the judge (see §6).**

---

## 6. Open questions for the human judge (genuine forks — I cannot settle these)

1. **How bold on the home screen?** Full AI-First (the Brief *is* the home, conversation-led, Save bars only on Tune) vs. a more conservative "score-hero home with a prominent AI Advisor card below" (closer to Quiet Premium's Overview + Mission Control's dashboard). My recommendation leans bold-with-guardrails, but the appetite for the opinionated "panel that talks first" stance is a values call about the target user and the demo you want to give. *This is the highest-order fork; most other choices follow from it.*

2. **Aesthetic register / palette: warm-editorial-amber vs. cool-cockpit-cyan vs. jade-minimal.** All three are dark-first and cliche-free, but they are *different products visually*: AI-First's warm graphite + sodium-amber (editorial), Mission Control's blue-black + phosphor-cyan (instrument), Quiet Premium's near-black + jade (luxury-minimal). The hybrid needs **one** authored dark base + accent. I recommend warm-graphite + amber (best for the editorial AI voice, most ownable accent), but this is a taste decision the owner must own personally.

3. **Display typeface: Fraunces (two of three picked it) vs. Space Grotesk (Mission Control) vs. something else.** Fraunces is the convergence pick and gorgeous, but it is a high-contrast serif — a strong, slightly polarizing opinion, heavier to subset, and weak for non-Latin locales (CJK/Arabic need a graceful per-locale fallback). If the owner wants a more "technical-instrument" feel over "editorial-luxury," Space Grotesk flips the whole personality. Pick the display face deliberately; the rest of the system follows it.

4. **Framework in the admin at all?** Preact (my recommendation, required for a clean streaming conversational hero) vs. a hard "zero-JS-framework" rule (forces Mission Control's vanilla/Alpine stack and a less-alive AI hero). This is partly an engineering-values call (is a 4–6KB admin-only, size-gated framework acceptable in a lean-brand plugin?) that the owner may hold opinions on beyond the technical merits.

5. **Build scope / sequencing appetite.** The honest cost ladder: the **AI engine** is ~1 working week as a thin vertical (`ai-architecture.md` §9, reusing existing seams) and is shippable behind *any* UI. The **UI revamp** is a from-scratch front end over unchanged endpoints — materially more than a reskin, and AI-First is explicitly the highest-effort of the three. Fork: ship the AI engine first behind a lightly-restyled current UI (fast, proves the headline), then layer the full Editorial Console — vs. build the full hybrid shell up front. I lean "engine-first, then shell" (the payload is stable, so the UI can land later without rework), but the timeline appetite is the owner's.

6. **The "no Save button on home" stance, specifically.** Even with Tune-page Save bars preserved, the *home* screen being conversation-only is a deliberate UX gamble that a beta cohort should validate. Does the owner want to ship that stance and test it, or start conventional and earn the bold home over time? (Related to fork #1 but narrower and worth an explicit yes/no.)

---

### Appendix — convergence map (where the visions already agree, so these are low-risk)

| Element | AI-First | Mission Control | Quiet Premium | Verdict |
|---|---|---|---|---|
| AI Explain-&-Fix as headline feature | **hero** | rail system | own room | **Must-have**; lead with it (AI-First) |
| Safety spine (whitelist/gate/snapshot/undo/re-audit) | yes | yes | yes | **Must-have**, verbatim from `ai-architecture.md` |
| No-key deterministic fallback | yes | yes | yes | **Must-have** |
| Custom animated score instrument | typeset dial | swept speedometer | giant numeral | **Must-have**; use Fraunces numeral + arc |
| Presets w/ explicit diff + per-setting risk | yes | yes | yes | **Must-have** |
| nginx generator as a hero (per-stack, copy, validate) | yes | yes | yes | **Must-have**; build the union |
| Per-URL cache inventory (dense, sortable) | yes | yes | yes | **Must-have**; Mission Control's manifest treatment |
| Orphaned settings → labeled homes, chip-inputs not textareas | yes | yes | yes | **Must-have** |
| Left rail + persistent top bar, ≤8 destinations | yes | yes | yes | **Must-have** |
| Dark-first + light theme, reduced-motion, scoped | yes | yes | yes | **Must-have** |
| Characterful display type, self-hosted fonts | Fraunces | Space Grotesk | Fraunces | **Must-have**; face is a judge fork |
| Reject full React for the admin | Preact | vanilla/Alpine | Preact | **Settled**: Preact |
| Reject LLM-writes-CSS | yes | yes | yes | **Settled**: reject |

The breadth of this convergence is the reassuring part: the *floor* is agreed across all three independent visions and the research. The judge's real decisions are the four taste/values forks (boldness, palette, typeface, framework) and the two scope forks (sequencing, the no-Save-on-home stance) in §6 — everything else is consensus.
