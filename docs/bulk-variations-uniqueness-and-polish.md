# Bulk Variations — Uniqueness, UX & Feature Polish (Plan v2)

> Companion read to `bulk-variations-plan.md` and `bulk-variations-plan-improvements.md`.
> Purpose: take the plan from "well-architected utility" to "the plugin merchants tell each other about".
> Scope: feature differentiation + UI/UX modernisation. Architecture is already solid — this doc does not re-litigate it.

---

## Part A — My read on the plan as it stands

### A1. What the plan does brilliantly (keep)

- **Performance correctness.** Direct `$wpdb` + Action Scheduler + batched CASE SQL is the right call. Most competitors fail here.
- **Provider-agnostic AI with preview-then-apply.** This is the right safety posture and the right architectural seam.
- **Rollback via delta storage.** Tabular, auditable, idempotent. Excellent.
- **Improvements doc nailed the foundation.** Unified admin shell, design tokens, dark mode, a11y, i18n, privacy/uninstall, retention, concurrency lock, REST schemas, pagination, Cart Blocks compatibility — those gaps are now closed on paper.
- **Cursor prompt pack is sequenced sensibly.** 26 prompts with verify steps is a real plan; most plugin builds at this size don't have one.

### A2. The "vibe gap" still left after the improvements doc

The plan and the improvements doc together make a **technically excellent, feature-complete enterprise utility**. They do not yet make a product merchants get *excited* about. The gap is:

1. **No feature competitors don't already have.** Beating Barn2/VBULKiT on cross-product editing + CSV + rollback + AI is good, but each of those exists somewhere. Nothing in the plan makes a merchant say "wait, *what?*"
2. **Spreadsheet hero is generic.** AG Grid out of the box is functional but feels like an internal admin tool. No character. No moments that delight.
3. **No emotional onboarding.** Activation drops the merchant in front of a menu. The first 90 seconds decide free→pro conversion; the plan doesn't engineer them.
4. **No retention loop.** Once set up, there's nothing pulling the merchant back to the dashboard daily. It's a "configure and forget" tool.
5. **No social-proof / share-worthy moment.** Nothing in the UI is screenshot-able for Twitter/Reddit.
6. **All the AI features serve the same mental model** (generate / suggest / clean) — none of them help the merchant *understand* their catalogue.

### A3. Why this matters commercially

The plan targets ~$20–40k Y2 revenue. To hit that with WooCommerce's saturated plugin shelf you need ONE of:

- **Distribution moat** (WooCommerce.com Marketplace listing, or a partnership)
- **Feature moat** (something nobody else has)
- **Brand/UX moat** (it just feels nicer to use than anything else)

The current plan competes on parity-plus-AI, which is a weak moat. The recommendations below add a feature moat and a UX moat without expanding scope catastrophically.

---

## Part B — Ten differentiator features (pick 3–5 to lead with)

I've ranked these by *impact-per-engineering-week*. The top three are the ones I'd ship in v1.0. The rest are v1.5+ levers.

### B1. ⭐ Visual diff & approval flow ("Git for product data")

**The pitch:** every bulk change opens a side-by-side diff — old value, new value, row-by-row, with per-row accept/reject checkboxes. Reviewer can approve, request changes, or partial-apply. Pre-flight before the job is queued.

**Why it wins:**
- Solves the #1 anxiety of bulk-editing: "what if I get it wrong on 5,000 rows"
- Teams with editor + owner roles have nowhere good to do this today
- Beats Barn2 (no preview), VBULKiT (apply-then-pray), PW Bulk Edit (no team workflow)
- Pairs naturally with the rollback system you've already specced — diff is the *prevention*, rollback is the *cure*

**Cost:** 1 week. The data structure is already there (`bv_job_changes` deltas). Add a "preview job" status before "queued" and a React diff component.

**Tagline:** *"Pull request for your products."*

### B2. ⭐ Auto-pilot rules engine

**The pitch:** visual if-then rules that run on a daily cron via Action Scheduler.

Examples:
- IF `stock < 10` AND `last_sold_at > 30 days` → apply `15% discount` + email `me@store.com`
- IF `variation has no image after 24h of creation` → flag for review + Slack notification
- IF `variation.profit_margin < 10%` → highlight red on the dashboard heatmap

**Why it wins:**
- This is the "retention loop" the plan is missing — once a merchant builds 5 rules, they log in to see what fired
- Killer feature for B2B catalogues with thousands of SKUs
- Easy upsell for Pro (Free gets 2 rules, Pro gets unlimited)
- Pairs with the AI assistant (rules suggest themselves: "your variations matching X pattern often go on sale — want a rule?")

**Cost:** 2 weeks. You already have the job system. Rules are just scheduled jobs with conditions.

**Tagline:** *"Set the rules. Let the store run itself."*

### B3. ⭐ Variation performance heatmap

**The pitch:** one screen, full-width. Rows = variations grouped by product, cell colour = sales velocity (last 30 days). Bright green = hot, grey = cold, red = aged inventory. Click any cell → orders behind it.

**Why it wins:**
- It's the **screenshot moment**. Merchants will post this on Twitter. Free marketing.
- Solves a real problem (which variations to discontinue, which to restock) that today requires exporting CSVs from WC Analytics
- Bridges "bulk editing tool" to "merchandising tool" — much bigger TAM
- Pairs with the rules engine and AI ("hide the 12 dead variations" becomes one click)

**Cost:** 1.5 weeks. Reads from `wp_wc_order_product_lookup` — no new tables.

**Tagline:** *"See what's selling. Fix what isn't."*

### B4. AI image bulk operations

**The pitch:** four AI-powered image tools:
- **Generate by colour** — pick a base image, AI recolours per variation
- **Auto-match uploads** — drop a folder of images, AI matches each to the right variation by filename + visual similarity
- **Background removal in bulk** — one click strips backgrounds across N variations
- **Alt-text generation** — accessibility-friendly alt text per variation, generated and stored

**Why it wins:**
- Image management is the #2 pain point in variable products (after pricing)
- No WooCommerce plugin combines this with bulk editing today
- Uses your existing `AiAdapterInterface` — add `OpenAiImageAdapter` + Anthropic vision

**Cost:** 2.5 weeks. Most expensive in this list — but it's a Pro-tier hero feature.

### B5. Schedule & stage workflows ("Black Friday mode")

**The pitch:** prepare a bulk update *now*, stage it, then auto-execute at a specific time. Optionally auto-revert on a second timer.

Examples:
- "Black Friday pre-load" — stage discount prices, execute 23:59 Thursday, auto-revert 00:00 Tuesday
- "Off-peak ops" — bulk-edit prepared at 5pm, runs 3am when traffic is dead
- "Linear-style Cycle view" — a calendar of upcoming scheduled jobs

**Why it wins:**
- Solves a problem every merchant feels in November
- Same engine as "scheduled imports" the plan already has — just generalised
- Trivial PR campaign in October: "Schedule your Black Friday in advance"

**Cost:** 1 week (mostly UI on top of existing scheduled-job code).

### B6. Profit margin live column

**The pitch:** in the spreadsheet, surface a `profit_margin` virtual column. Reads cost from a meta key (`_cost`, ACF `cost_price`, Meta Box, etc — configurable). Computed client-side as `(price - cost) / price`. Cell tints red below threshold, green above. AI "Suggest price" option that targets a margin %.

**Why it wins:**
- Most stores never see margin in the WC admin
- One column. Huge perceived value.
- Pairs naturally with rules ("if margin < 10% flag")

**Cost:** 3 days.

### B7. Webhooks + headless story (Pro)

**The pitch:**
- Outbound webhooks on job.complete / job.failed / job.rolled_back / rule.fired
- Public TypeScript types published to npm as `@your-vendor/bulk-variations-types`
- Optional GraphQL endpoint (alternative to REST)
- Documented event names + payload schemas

**Why it wins:**
- Sells the Pro tier to agencies and headless-Woo shops
- Differentiates from every Woo bulk-editor on the market
- Becomes a developer-mode talking point for blog posts and conference demos

**Cost:** 1.5 weeks.

### B8. Multi-warehouse stock matrix (Pro+)

**The pitch:** stock-per-location for each variation. Bulk transfer UI. Per-location low-stock alerts. Cooperates with WC Multi-Warehouse plugin if installed, otherwise standalone.

**Why it wins:**
- B2B merchants will pay for this alone
- Unlocks the Agency tier ($399/yr) — gives it a reason to exist
- Future-proofs against WooCommerce's own (slow) multi-warehouse work

**Cost:** 3 weeks. Defer to v1.5 unless an early customer asks.

### B9. Mobile PWA for stock crew

**The pitch:** `/bv-stock` URL → mobile-first PWA. Barcode scanner via `getUserMedia` + ZXing. Quick stock adjust, "scan to find variation", check-in / check-out flow. For warehouse staff who shouldn't touch wp-admin.

**Why it wins:**
- Nobody else does this in the bulk-edit space
- Real workflow value for stores with physical inventory
- Adds a viable Agency-tier feature

**Cost:** 2 weeks. Defer to v1.5 — high differentiation, but later in funnel.

### B10. Activity feed + live presence

**The pitch:**
- Right rail or topbar: chronological feed ("Sarah edited 12 prices · 2 min ago", "Auto-pilot rule 'Low stock alert' fired on 3 variations")
- Avatars next to currently-edited cells (heartbeat-based presence, no need for websockets)
- Optimistic UI with last-writer-wins conflict resolution

**Why it wins:**
- Makes the plugin feel "alive" — that retention loop again
- Trust signal for teams ("I can see who changed what")
- Pairs with the audit log you already need to build for the rollback system

**Cost:** 1.5 weeks. WP Heartbeat API is good enough — no websocket infra needed.

### My recommended v1.0 lineup

Pick **B1 (Diff & Approval)** + **B2 (Auto-pilot rules)** + **B3 (Heatmap)** + **B6 (Margin column)**. Add **B5 (Scheduled workflows)** in v1.1 timed for Black Friday. Defer B4, B7, B8, B9, B10 to v1.5+.

That gives you **four differentiating features in v1.0** that no competitor combines.

---

## Part C — UI/UX modernisation pass

The improvements doc gives you tokens, themes, and a unified shell. This section adds the *feel*. Each item is intentionally small — they compound.

### C1. Command palette (Cmd / Ctrl + K)

Not "quick search" — a **command surface**. Linear / Notion / Stripe pattern. Implement on top of `cmdk` (the npm package) or roll lightweight.

Categories:
- **Navigate** (Dashboard, Editor, Jobs, …)
- **Edit** (Create job, Apply formula to selection, …)
- **AI** (Generate variations, Suggest prices, Clean import, …)
- **Job** (Pause running, Rollback latest, …)
- **Settings** (Toggle dark mode, Set retention, …)
- **Help** (Open shortcuts, Email support, …)

Features:
- Fuzzy match with score-based ranking
- Recent commands persisted to localStorage
- Inline previews for "Open job #42" (mini-summary)
- Keyboard navigation, Enter to execute
- One source of truth for every action in the app (also feeds the keyboard shortcut sheet)

**Why it matters:** every power-user habit forms around the palette. Once a merchant learns ⌘K, they stop clicking menus. They never leave.

### C2. Snackbar with undo (Gmail style)

Every bulk action: `Updated 47 variations. [Undo]` — auto-dismisses after 8 seconds. Click → triggers actual rollback. Stacks bottom-right.

This is also the **single best safety feature you can ship**. Pairs perfectly with the rollback system: rollback becomes one-click, not a buried menu.

### C3. First-run experience (the 90-second aha)

Activation → modal: *"Hi. Got 90 seconds?"* → 4-step interactive walkthrough using a real test product (auto-created in the demo step, cleaned up after):

1. **Pick a product** (or use our demo product)
2. **Generate 5 variations with AI** — happens live, shows real preview
3. **Bulk price change with preview** — diff opens, user clicks apply
4. **Rollback** — instant, prove the safety net works

End on: *"You just saved yourself 20 minutes of manual editing. Here's what to try next."* with three suggested actions.

Why it matters: this is your free→pro conversion engine. Currently the plan has *nothing* engineered for the first 5 minutes.

### C4. Floating action menu (selection-aware)

When N rows selected in the editor: a bottom-centre pill morphs in.

```
●  47 selected   ·   Set price   ·   Set stock   ·   Apply formula   ·   ⋯ More
```

Replaces the always-visible toolbar. Better than top-mounted bulk actions when the user has scrolled past them on a 1,000-row grid.

### C5. Micro-interactions (subtle, respects `prefers-reduced-motion`)

| Action | Animation |
|--------|-----------|
| Cell value changes | 400ms pulse from `--bv-primary-soft` → transparent |
| Row select | 2px primary left-border slides in from -8px |
| Save | Button morphs into checkmark for 600ms, then back |
| Job completes | Sidebar Jobs badge ticks down with a 200ms scale-bounce |
| Toast appears | Slide-up from bottom with spring (250ms cubic-bezier) |
| Modal opens | Backdrop fade 150ms, content scale from 0.96 → 1 |
| Locked Pro feature hover | Lock icon shake (single 80ms wiggle) |

None of these should be longer than 500ms. All disabled when `prefers-reduced-motion: reduce`.

### C6. Inline cell history

Hover any spreadsheet cell with a history badge (small dot in the corner) → tooltip shows last 5 changes (who · when · from→to). Click → drawer opens with full timeline for that variation.

Source: `bv_job_changes`. Free feature — makes the database table earn its keep.

### C7. Smart empty states

Not "no data found." Each empty state is a teaching surface with an illustrated SVG, headline, body, and one CTA.

| View | Empty state |
|------|-------------|
| Editor (no product picked) | "Pick a product to start editing. Or [try the demo product]." |
| Jobs (no jobs yet) | "No bulk operations yet. Here's a [30-second tour]." |
| AI Assistant | "Tell me what you want. Try: *Create sizes S–XXL in red and blue.*" |
| Templates | "Save a recipe once, apply it to any product. [Create your first template]." |
| Heatmap | "Need 7 days of sales data to render. Come back tomorrow." |

### C8. Dark mode polish

The improvements doc has tokens. Go further:
- Subtle frosted-glass effect on the sticky topbar (`backdrop-filter: blur(8px)` + 85% alpha background)
- Glow on focus rings using `--bv-primary-soft` at higher alpha
- Status badges keep their hue but desaturate 15% in dark mode (otherwise they vibrate against the deep-blue bg)
- True OLED option (`#000` background) toggleable in Settings → Appearance
- Surface gradient: surface-1 has a 1px top highlight (`linear-gradient(180deg, rgba(255,255,255,.04), transparent 12px)`) so cards look lifted

### C9. The "live" feel

Small touches that make the app feel alive:
- Pulsing dot in the topbar when any background job is running
- Sidebar Jobs item shows a pulsing badge count
- Job rows have smoothly-transitioning progress bars (not jerky step updates)
- Heartbeat-style polling shows "Last updated 2s ago" subtle ticker in the topbar
- When a job completes, a soft chime sounds (off by default, opt-in in Settings)

### C10. Dashboard onboarding cards (the retention loop)

The dashboard gets smarter over time:
- After 1 job: *"Try this: schedule a recurring CSV import →"*
- After 5 jobs: *"You've saved an estimated 4 hours this month. Here's your time-saved chart."*
- After 20 jobs: *"Ready to invite a teammate? Pro adds role-based access."* (upgrade nudge)
- After a failed job: *"That one didn't go through. Here's what happened + how to fix it."*

This is a behavioural-loop trick that takes ~2 days to build and pays off forever.

### C11. The "Aha receipt"

After every applied bulk job, the snackbar's secondary line shows a tiny stat:
*"Updated 247 variations across 12 products in 3.4s. **You'd have spent ~80 minutes doing this manually.**"*

Sharable, addictive, slightly smug — in a good way. Sets up the upgrade conversation.

---

## Part D — Things to cut or defer (focus is a feature)

The plan tries to ship everything. Don't.

| Cut from v1.0 | Why | When to revisit |
|---------------|-----|-----------------|
| **Templates UI** (§16 in prompt pack) | Adds a whole view + table for a feature 5% of users need at launch | After 100 paying users specifically ask |
| **CleanImport + Summarize AI tabs** | Two of the four AI tabs serve niches | v1.2 after watching `bv_ai_log` usage |
| **WP-CLI** (full surface) | Power-user feature; CLI parity slows you down at launch | v1.1 |
| **GraphQL endpoint** | REST is enough for v1 | v2.0 |
| **Multi-warehouse** | Heavy lift, narrow audience | v1.5 only if an Agency customer asks |
| **Mobile PWA** | Big surface, separate codepath | v1.5 |
| **Visual regression testing** (Chromatic/Loki) | Nice-to-have, not blocking | v1.2 |

Use the freed time on **B1 diff** + **B3 heatmap** + **C1 command palette** + **C3 first-run**. Those four ship the moat.

---

## Part E — Suggested roadmap revision

Replace plan §13 with this. Same total weeks, different priorities:

### MVP — Weeks 1–6 (Free + paid Early Access)

| Week | Deliverables |
|------|-------------|
| 1 | Plugin bootstrap, migrations, HPOS, Cart Blocks, i18n |
| 2 | VariationGenerator, BulkEditor (batched SQL), JobRepository, SKUGenerator |
| 3 | **Admin shell** (Topbar + Sidebar + Router + tokens + dark mode), Dashboard skeleton |
| 4 | JobManager + Action Scheduler + REST API core + CSV import |
| 5 | **AG Grid editor** with floating action menu + selection-aware toolbar |
| 6 | **B1: Visual diff & approval flow** ⭐ + first-run experience (C3), WP.org submission |

### v1.0 Pro — Weeks 7–14

| Week | Deliverables |
|------|-------------|
| 7 | **B3: Performance heatmap** ⭐ + AI assistant (Generate + Suggest Prices only) |
| 8 | Rollback system + snackbar-with-undo (C2) + inline cell history (C6) |
| 9 | **B2: Auto-pilot rules engine** ⭐ |
| 10 | **B6: Profit margin column** + **C1: Command palette** ⭐ |
| 11 | Freemius integration, Pro feature gates, upgrade flow |
| 12 | E2E + a11y + i18n + RTL pass |
| 13 | Marketing assets, readme.txt, screenshots, banner |
| 14 | Pro launch |

### v1.1 — November (Black Friday play)

- **B5: Schedule & stage workflows** ("Black Friday Mode")
- "How to use Bulk Variations for Black Friday" 4-part blog series
- AI tabs: Clean Import + Summarize

### v1.5 — Q1 next year

- **B4: AI image bulk ops**
- **B7: Webhooks + TypeScript types**
- **B10: Activity feed + presence**
- Variation templates (B2-led demand by now)

### v2.0 — H2 next year

- **B8: Multi-warehouse**
- **B9: Mobile PWA**
- GraphQL
- WooCommerce.com Marketplace listing

---

## Part F — Quick-hit polish backlog (week 12)

Save these for the polish sprint. Each is < 2 hours but the collective impact is huge.

- [ ] Keyboard shortcut cheat sheet behind `?`
- [ ] "Was this helpful?" feedback widget on every Settings tab
- [ ] Bulk-action confirmation includes the math: *"This will change 3,247 prices across 142 products"*
- [ ] CSV exports include a `# How to safely edit this file` header comment
- [ ] Every error message includes a fix-it link to docs
- [ ] Loading skeletons on every list (not spinners)
- [ ] Toast dismiss includes "Don't show this again" for non-critical notices
- [ ] Job rows on the Jobs page get a subtle progress shimmer while running
- [ ] Settings save autosaves with a 300ms debounce + status pill ("Saved 2s ago")
- [ ] Admin menu icon is a custom SVG, not a dashicon
- [ ] Plugin-row "Settings" link in the WP plugins list links to `/dashboard` not `/settings`
- [ ] When AI returns invalid JSON, the repair-loop status shows in the UI, not silently retries
- [ ] Audit log on the Diagnostics page (last 200 events, downloadable)
- [ ] WP admin colour scheme respected — if user picks "Modern" admin colour, our primary becomes accent

---

## Part G — Marketing hooks unlocked by the above

Each differentiator gives you one campaign:

| Feature | Hook | Audience |
|---------|------|----------|
| B1 Diff | *"Pull requests for your products"* | dev-savvy Woo merchants on r/woocommerce |
| B2 Rules | *"Set the rules. The store runs i{"net":{"http_server_properties":{"broken_alternative_services":[{"anonymization":[],"broken_count":5,"host":"ithemelandco.com","port":443,"protocol_str":"quic"}],"servers":[{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13426271021230006","port":443,"protocol_str":"quic"}],"anonymization":[],"server":"https://t2.gstatic.com","supports_spdy":true},{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13426278083852504","port":443,"protocol_str":"quic"}],"anonymization":[],"network_stats":{"srtt":76307},"server":"https://t0.gstatic.com","supports_spdy":true},{"anonymization":[],"server":"https://ithemelandco.com","supports_spdy":true},{"anonymization":[],"server":"https://barn2.com","supports_spdy":true},{"anonymization":[],"network_stats":{"srtt":5402},"server":"https://pluginrepublic.com","supports_spdy":true},{"anonymization":[],"network_stats":{"srtt":51793},"server":"https://www.pimwick.com"},{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13426495784912444","port":443,"protocol_str":"quic"}],"anonymization":[],"server":"https://t3.gstatic.com","supports_spdy":true},{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13426495784703572","port":443,"protocol_str":"quic"}],"anonymization":[],"network_stats":{"srtt":38036},"server":"https://www.google.com"},{"anonymization":[],"server":"https://developer.wordpress.org","supports_spdy":true},{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13424072110741587","port":443,"protocol_str":"quic"}],"anonymization":[],"server":"https://ab.chatgpt.com","supports_spdy":true},{"alternative_service":[{"advertised_alpns":["h3"],"expiration":"13426577708544375","port":443,"protocol_str":"quic"}],"anonymization":[],"network_stats":{"srtt":45434},"server":"https://o33249.ingest.us.sentry.io","supports_spdy":true},{"alter