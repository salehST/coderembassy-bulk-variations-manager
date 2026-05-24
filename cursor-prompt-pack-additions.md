# Cursor AI Prompt Pack — Additions

> Companion to `cursor-prompt-pack.md`.
> Adds: PROMPT 00 (porting reference from the old `bulk-variations-pro/` codebase) and PROMPTS 27–33 (the uniqueness + UX polish features from `bulk-variations-uniqueness-and-polish.md`).
>
> **How to use this file:**
>
> 1. Paste `MASTER CONTEXT` from `cursor-prompt-pack.md` once (still required).
> 2. Run `PROMPT 00` **before PROMPT 01** so Cursor knows where the reference code lives.
> 3. Then run prompts 01–26 from `cursor-prompt-pack.md` in order.
> 4. After PROMPT 26, run **PROMPT 27 → 33** from this file (UX polish + uniqueness features).
> 5. Same "one prompt = one PR" rule as the main pack.

---

## PROMPT 00 — Reference Codebase from `bulk-variations-pro/`

```
Goal: register the old codebase as a READ-ONLY reference so you can port specific algorithms while building the new plugin under bulk-variations-add-edit-product/. Do NOT copy classes wholesale — the old code is PHP 7.4, jQuery, no PSR-4, no DI, no namespaces, has N+1 wc_get_product()->save() loops, and is architecturally incompatible with the new plan.

Depends on: nothing.

Reference path
  C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations-pro\

What to port (and how to rewrite it)

1. Cartesian product generator
   - Source: includes/class-bvpro-generator.php (algorithm only — discard the WC_Product_Variation::new() + ->save() loop)
   - Target: src/Engine/VariationGenerator.php — generateCombinations() / previewCombinations() (see PROMPT 04 of the main pack)
   - Rewrite the persistence step to direct $wpdb batched INSERTs + Action Scheduler chunks. Never loop ->save().

2. CSV import streaming
   - Source: includes/class-bvpro-csv.php (fopen/fgetcsv streaming logic + header mapping)
   - Target: src/ImportExport/CsvImporter.php + ImportValidator.php (PROMPT 09 of main pack)
   - Discard the synchronous loop write — dispatch chunks to ImportJob via JobManager.

3. CSV export streaming
   - Source: includes/class-bvpro-csv.php (export portion)
   - Target: src/ImportExport/CsvExporter.php — keep php://output streaming + fputcsv.

4. Frontend variation grid HTML
   - Source: includes/class-bvpro-grid.php (HTML rendering only) + public/css/bvpro-public.css (the responsive table CSS — useful starting point)
   - Target: templates/frontend/variation-grid.php (PROMPT 20 of main pack)
   - Rebuild as a theme-agnostic PHP template with CSS custom properties. Replace jQuery with vanilla JS that supports Cart Blocks.

5. REST endpoint shape (NOT the handlers)
   - Source: includes/class-bvpro-rest-api.php — note the URL patterns /products/{id}/variations and /products/{id}/cart are clean.
   - Target: src/REST/RestController.php under the bv/v1 namespace.
   - DO NOT copy permission_callback => __return_true anywhere; every route in the new plugin requires current_user_can('manage_woocommerce') + nonce. The old plugin's public GET /variations endpoint is a privacy issue we are explicitly closing.

6. Analytics events table
   - Source: includes/class-bvpro-analytics.php — table schema for bvpro_analytics is reasonable.
   - Target: feeds the new Performance Heatmap (PROMPT 29). Migrate as a new table bv_events (cleaner name + new prefix).
   - On install: detect old bvpro_analytics table; if present, offer a one-click migration in Settings → Diagnostics.

7. Activation pattern
   - Source: includes/class-bvpro-install.php — dbDelta() usage is correct.
   - Target: src/Updater/MigrationRunner.php (PROMPT 02 of main pack) — same pattern, new tables.

8. Helpers
   - Source: includes/class-bvpro-helpers.php — get_variation_data() and get_product_attributes() — algorithm is fine.
   - Target: src/Engine/VariationRepository.php — re-implement with single SQL IN queries (no per-variation get_post_meta calls).

What NOT to port
  - The jQuery UI bulk editor (admin/js/bvpro-admin.js) — replaced by React + AG Grid.
  - Any wc_get_product()->save() loop — replaced by batched CASE SQL.
  - The static singleton bootstrap — replaced by lucatume/di52.
  - The class-bvpro-license.php stub — replaced by Freemius.
  - The bvpro_discounts table — qty discounts are out of scope for v1.

Migration UX for existing users
  - If activation detects the old bulk-variations-pro plugin alongside ours, show one-time admin notice:
    "Welcome from Bulk Variations Pro. We've rebuilt the plugin from the ground up. Want to migrate your settings? [Migrate now] [Skip]"
  - Migration covers: bvpro_analytics → bv_events, bvpro_templates → bv_templates, any saved settings under bvpro_* options → bv_settings.
  - Deactivate the old plugin programmatically after successful migration; do NOT delete its data (let merchant decide).

Output for this prompt
  - No code. Just produce a single file: docs/PORTING.md that catalogues every old file, what it does, whether to port (Y/N), and the target file in the new architecture. Use a markdown table.
  - This file lives in the new plugin folder bulk-variations-add-edit-product/docs/PORTING.md.

Acceptance
  - docs/PORTING.md exists with one row per file in bulk-variations-pro/ (24 files).
  - Every "port: Y" row has a target path that matches the MASTER CONTEXT file layout.
  - No prompt 01..26 references the old folder; PORTING.md is the only bridge.
```

**Verify**

```
test -f bulk-variations-add-edit-product/docs/PORTING.md \
  && grep -c '^|' bulk-variations-add-edit-product/docs/PORTING.md   # ≥ 25 lines
```

---

## PROMPT 27 — Visual Diff & Approval Flow (Feature B1)

```
Goal: every bulk job opens a side-by-side diff before it is enqueued. Reviewer can approve, request changes, or partial-apply per-row. This becomes the standard pre-flight for bulk_edit jobs. Reuses the bv_job_changes delta storage.

Depends on: PROMPT 07 (REST), PROMPT 14 (Bulk Editor), PROMPT 15 (Jobs).

Output files

1. Migration migrations/0002_diff_approval.sql
   - ALTER TABLE bv_jobs ADD COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'none'
     COMMENT 'none|pending_review|approved|rejected'
   - ALTER TABLE bv_jobs ADD COLUMN reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL
   - ALTER TABLE bv_jobs ADD COLUMN reviewed_at DATETIME DEFAULT NULL
   - ALTER TABLE bv_job_changes ADD COLUMN review_decision VARCHAR(20) DEFAULT 'pending'
     COMMENT 'pending|accepted|rejected'

2. src/Diff/DiffService.php
   - buildDiff(int $job_id): array — returns:
     {
       summary: { rows_total, rows_changed, fields: {price: N, stock: N, sku: N, ...},
                  potential_revenue_impact: float, products_affected: int },
       rows: [ { variation_id, sku, product_title, fields: { _price: {old, new}, _stock: {old, new} } } ]
     }
   - applyDecisions(int $job_id, array $decisions): void — sets bv_job_changes.review_decision per row.
   - filterPendingDeltas(int $job_id): array — returns only rows where review_decision === 'accepted'.
   - When JobManager processes a job whose review_status === 'approved', it consumes only the accepted deltas (rejected ones are deleted from bv_job_changes before processing).

3. src/Services/RevenueImpactEstimator.php
   - estimate(array $deltas): float — sums (new_price - old_price) * avg_daily_orders_per_variation over the last 30 days using the bv_events table from PROMPT 29.
   - Caches per job for 60s.

4. Extend src/REST/RestController.php
   - GET  /jobs/{id}/diff           → DiffService::buildDiff
   - POST /jobs/{id}/diff/decisions → DiffService::applyDecisions  (body: {decisions: [{change_id, accept}]})
   - POST /jobs/{id}/approve        → set review_status=approved, enqueue via JobManager
   - POST /jobs/{id}/reject         → set review_status=rejected, status=failed, record reason

5. Workflow change
   - JobManager::dispatch now stores deltas FIRST without enqueuing chunks.
   - Sets status='preview', review_status='pending_review'.
   - Only enqueues chunks after POST /jobs/{id}/approve.
   - JobRepositoryInterface gains preview(int $job_id): bool helper that sets these flags atomically.

6. React: views/Jobs/DiffDrawer.jsx
   - Two-column layout. Left: current. Right: proposed.
   - Per-row checkbox (default accepted). "Accept all" / "Reject all" toolbar.
   - Filters: by attribute (color/size), by field (price/stock/sku), by change magnitude (e.g., >20%).
   - Sticky footer: row counts, revenue impact estimate badge, Approve / Reject / Save & Close.
   - Keyboard: J/K to move between rows, X to toggle accept, A to approve, R to reject.

7. React: views/BulkEditor/ApplyBar.jsx
   - Replace "Apply Changes" CTA → "Preview & Approve" when the editor has > 5 row changes.
   - On click: POST /jobs (creates preview job) → navigate to #/jobs/{id}/diff.

8. React: shared/RevenueImpactBadge.jsx
   - Tiny pill that shows projected revenue impact ("+$420 / mo" green or "-$120 / mo" red).
   - Surfaces inside the DiffDrawer footer and on the Jobs row.

9. Permissions
   - Optional capability bv_review_jobs. If defined, only users with this cap can approve. Otherwise falls back to manage_woocommerce.
   - Settings → General: "Require dual approval on jobs > N rows" (Pro).

10. Tests
   - tests/Unit/Diff/DiffServiceTest.php — applyDecisions filters correctly.
   - tests/Integration/DiffWorkflowTest.php — create job → diff → reject 5 rows → approve → only accepted rows applied.
   - tests/E2E/diff-approval.spec.ts — happy path UI.

Acceptance
   - Bulk edit > 5 rows: clicking Apply opens the diff, not the job page.
   - Rejecting a row excludes it from the actual SQL UPDATE.
   - Rollback of an approved job restores only the rows that were actually applied.
   - Revenue impact badge shows numbers, with the time window noted ("last 30d").
```

**Verify**

```
composer test -- --filter "Diff"
npx playwright test diff-approval.spec.ts
```

---

## PROMPT 28 — Auto-pilot Rules Engine (Feature B2)

```
Goal: visual if-then rules that run on a daily cron via Action Scheduler. Examples:
  IF stock < 10 AND last_sold > 30 days THEN apply 15% discount + email me.
  IF variation has no image after 24h THEN flag for review.
  IF profit_margin < 10% THEN highlight on dashboard.

Depends on: PROMPT 07 (REST), PROMPT 12 (shared components), PROMPT 19 (AI assistant for rule suggestions).

Output files

1. Migration migrations/0003_rules.sql
   CREATE TABLE bv_rules (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     name VARCHAR(150) NOT NULL,
     description TEXT,
     enabled TINYINT(1) NOT NULL DEFAULT 1,
     trigger_type VARCHAR(60) NOT NULL,          -- 'daily' | 'on_demand' | 'on_event'
     event_type VARCHAR(60) DEFAULT NULL,        -- 'variation_created' | 'order_placed' | …
     conditions LONGTEXT NOT NULL,               -- JSON: tree of ANDed/ORed conditions
     actions LONGTEXT NOT NULL,                  -- JSON: ordered list of action objects
     last_run_at DATETIME DEFAULT NULL,
     last_run_matches INT DEFAULT 0,
     created_by BIGINT(20) UNSIGNED,
     created_at DATETIME NOT NULL,
     PRIMARY KEY (id),
     KEY trigger_idx (trigger_type, enabled),
     KEY event_idx (event_type, enabled)
   );

   CREATE TABLE bv_rule_runs (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     rule_id BIGINT(20) UNSIGNED NOT NULL,
     started_at DATETIME NOT NULL,
     completed_at DATETIME DEFAULT NULL,
     matches INT DEFAULT 0,
     job_id BIGINT(20) UNSIGNED DEFAULT NULL,    -- the job that the rule enqueued
     status VARCHAR(30) DEFAULT 'running',
     PRIMARY KEY (id),
     KEY rule_idx (rule_id)
   );

2. src/Rules/Condition.php
   - Tree-based condition evaluator:
     {
       "op": "AND",
       "children": [
         { "field": "stock_quantity", "op": "<", "value": 10 },
         { "field": "days_since_last_order", "op": ">", "value": 30 }
       ]
     }
   - Supported fields: stock_quantity, regular_price, sale_price, profit_margin, days_since_creation, days_since_last_order, image_count, attribute.<slug>.
   - Supported ops: =, !=, <, <=, >, >=, in, contains, is_empty, regex.
   - Matcher returns an array of variation_ids.
   - Implementation: builds one SQL query against postmeta + bv_events; never iterates variations in PHP.

3. src/Rules/Action.php
   - Action interface + concrete classes:
     - AdjustPriceAction (percent or absolute)
     - SetStockAction
     - SetSaleAction (set sale_price for window)
     - AddTagAction
     - FlagForReviewAction (writes to bv_review_queue table — bonus mini-table for the UI)
     - SendNotificationAction (email / Slack / Webhook via Webhooks system later)
   - Each action returns a set of deltas which become the rule's job.

4. src/Rules/RulesEngine.php
   - evaluateRule(int $rule_id): array — runs condition → collects variation_ids → translates to deltas via Actions → creates a bv_jobs entry (type='rule') and dispatches via JobManager (with review_status=pending_review if rule has require_approval flag).
   - evaluateAll(): runs every enabled rule with trigger_type='daily'.

5. src/Jobs/RulesDailyJob.php
   - Hooked to as_schedule_recurring_action('bv_rules_daily', DAY_IN_SECONDS).
   - Calls RulesEngine::evaluateAll().

6. REST routes (extend RestController):
   - GET    /rules
   - POST   /rules                  body: { name, conditions, actions, trigger_type, ... }
   - GET    /rules/{id}
   - PUT    /rules/{id}
   - DELETE /rules/{id}
   - POST   /rules/{id}/run         dry-run mode: returns matches without writing
   - POST   /rules/{id}/enable
   - POST   /rules/{id}/disable
   - GET    /rules/{id}/runs        run history with pagination

7. React: views/Rules/ — NEW sidebar item between Templates and AI (Pro-locked in Free above 2 active rules)
   - index.jsx — list of rules with toggle, last-run time, match count.
   - RuleBuilder.jsx — visual condition builder:
       Row: [field dropdown] [op dropdown] [value input]  ×  [AND/OR group]
       Add condition / Add group buttons.
   - ActionPicker.jsx — list of actions with parameter forms.
   - RulePreview.jsx — clicking "Test rule" runs dry-run, shows matched variations table.
   - RunHistoryDrawer.jsx — per-rule run timeline.

8. AI integration
   - "Suggest a rule" button in views/Rules/index.jsx → POST /ai/suggest with prompt_type='suggest_rule', context = store's variation profile (sanitized).
   - PromptLibrary new entry 'suggest_rule' returns up to 5 plausible rules with JSON schemas matching bv_rules.conditions/actions.
   - Preview the suggestion, click "Create rule" to save.

9. Free / Pro
   - Free: up to 2 active rules, daily trigger only.
   - Pro: unlimited rules, all triggers, AI suggest, webhook actions.

10. Tests
   - tests/Unit/Rules/ConditionTest.php — tree evaluation + SQL output snapshots.
   - tests/Integration/RulesEngineTest.php — seed product with stale variations, run rule, assert deltas correct.
   - tests/E2E/rules-builder.spec.ts — UI flow.

Acceptance
   - Daily action scheduled on activation.
   - Manually running `wp action-scheduler run --hooks=bv_rules_daily` triggers all enabled rules.
   - Dry-run on a rule shows matches without writing.
   - Disabling a rule stops it from firing on the next cron tick.
   - AI suggest returns valid rule JSON; clicking Create persists it.
```

**Verify**

```
composer test -- --filter "Rules"
npx wp-env run tests-wordpress wp action-scheduler run --hooks='bv_rules_daily'
```

---

## PROMPT 29 — Performance Heatmap (Feature B3)

```
Goal: a single-screen heatmap of variation sales velocity over the last 30 days. Cells coloured by velocity bucket. Click a cell to drill into the orders behind it. Reuses the analytics-event approach from the old plugin.

Depends on: PROMPT 12 (shared components). PROMPT 00 (migration from bvpro_analytics if present).

Output files

1. Migration migrations/0004_events.sql
   CREATE TABLE bv_events (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     variation_id BIGINT(20) UNSIGNED NOT NULL,
     product_id BIGINT(20) UNSIGNED NOT NULL,
     event_type VARCHAR(40) NOT NULL,   -- 'view' | 'add_to_cart' | 'order' | 'refund'
     qty INT NOT NULL DEFAULT 0,
     revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
     occurred_at DATETIME NOT NULL,
     PRIMARY KEY (id),
     KEY variation_idx (variation_id, occurred_at),
     KEY product_idx (product_id, occurred_at),
     KEY type_idx (event_type, occurred_at)
   ) ENGINE=InnoDB;

2. src/Analytics/EventRecorder.php
   - Hooks:
     - woocommerce_new_order_item → record 'order' with qty + revenue.
     - woocommerce_add_to_cart → record 'add_to_cart' qty=1.
     - woocommerce_after_single_product → record 'view' (guarded by once-per-session cookie).
     - woocommerce_order_refunded → record 'refund' negative qty/revenue.
   - All writes are debounced into a per-request buffer flushed on 'shutdown' via a single multi-row INSERT.

3. src/Analytics/HeatmapService.php
   - getHeatmap(array $filters): array — returns:
     {
       products: [ { product_id, title, variations: [ { variation_id, sku, attrs, velocity, units, revenue, last_sold_at } ] } ],
       buckets: [ { label: 'hot', min: 30, max: null, color: '--bv-ok' }, ... ],
       window_days: 30
     }
   - SQL: single GROUP BY query against bv_events + JOIN to posts + postmeta. Cached for 5 min via transient keyed on filter hash.

4. src/Analytics/MigrationFromBvpro.php
   - One-shot import: SELECT * FROM bvpro_analytics → INSERT INTO bv_events with column mapping.
   - Run on click from Settings → Diagnostics or via wp bv events migrate.

5. REST
   - GET /heatmap?window=30&product_ids[]=…&attr.color=red — returns HeatmapService output.
   - GET /heatmap/cell?variation_id=…&window=30 — drill into one cell: orders, top customers, daily timeline.

6. React: views/Heatmap/ — NEW sidebar item between Dashboard and Bulk Editor
   - index.jsx — controls bar (window selector, product filter, attribute filter) + grid.
   - HeatmapGrid.jsx — canvas-based cell renderer for performance with > 5k cells (fallback to CSS grid for < 1k cells).
   - CellDetail.jsx — drawer with timeline chart (Recharts), top orders, "create rule from this view" CTA.
   - EmptyState.jsx — "Need 7 days of sales to render. Come back tomorrow."
   - Legend.jsx — gradient ramp with bucket labels.

7. Export
   - "Download as PNG" button — uses html-to-image lib on a clean snapshot of the grid.
   - "Download CSV" — flattened table.

8. Free / Pro
   - Free: 7-day window only.
   - Pro: up to 365 days + drill-in + PNG export.

9. Tests
   - tests/Unit/Analytics/HeatmapServiceTest.php — bucketing maths.
   - tests/Integration/EventRecorderTest.php — order creates a row.
   - tests/E2E/heatmap.spec.ts — seed events, assert cell renders with correct hue.

Acceptance
   - Activate plugin → no errors when bv_events is empty (heatmap shows empty state).
   - Place a test order in wp-env → row appears in bv_events.
   - Heatmap loads under 800ms with 1k variations × 30 days seeded.
   - PNG export downloads a non-empty file.
```

**Verify**

```
composer test -- --filter "Heatmap|EventRecorder"
npx playwright test heatmap.spec.ts
```

---

## PROMPT 30 — Profit Margin Column (Feature B6)

```
Goal: show a profit_margin column in the spreadsheet, computed from a configurable cost meta key. Cell tints red below threshold, green above. AI "Suggest price for target margin" inline.

Depends on: PROMPT 14 (Bulk Editor), PROMPT 18 (Settings).

Output files

1. src/Services/MarginCalculator.php
   - getCost(int $variation_id, string $meta_key): float — reads from configured meta key, defaulting to '_cost' if WooCommerce extension provides it, then ACF 'cost_price', then Meta Box 'cost'.
   - margin(float $price, float $cost): array — { absolute: float, percent: float }.
   - batchCosts(array $variation_ids, string $meta_key): array — single SQL IN query.

2. Settings extension (PROMPT 18 General tab)
   - "Cost meta key" text input (default _cost) with auto-detect button that scans first 50 variations for likely meta keys.
   - "Low margin threshold (%)" numeric, default 10.
   - "High margin threshold (%)" numeric, default 40.

3. React: BulkEditor columns extension
   - Add column { field: 'profit_margin', headerName: 'Margin %', editable: false, sortable: true }.
   - cellRenderer/MarginCell.jsx — renders "23.4%" with background colour interpolated between red (< low) → amber (between) → green (>= high).
   - On hover: tooltip shows cost source + raw cost value.

4. AI integration
   - In the Toolbar bulk-action menu: "Suggest price for target margin…" → opens modal with target % slider → calls POST /ai/suggest with prompt_type='target_margin_pricing', context including current prices + costs.
   - Preview returns suggested prices per row; opens the diff drawer (PROMPT 27).

5. REST
   - GET /variations now includes profit_margin in response when ?include=margin is set, so the React app doesn't recompute client-side for large grids.

6. Heatmap hookup
   - Bonus: add a "Colour by: Velocity | Margin | Stock" selector to the Heatmap view that swaps the metric.

7. Free / Pro
   - Free: column visible, no AI pricing.
   - Pro: AI "Suggest price for target margin" enabled.

8. Tests
   - tests/Unit/Services/MarginCalculatorTest.php — handles missing cost, zero price, negative margin.
   - tests/E2E/margin-column.spec.ts — set cost on a variation, verify cell colour.

Acceptance
   - With cost meta set to 5.00 and price 10.00: margin column shows 50.0% (green).
   - With cost meta missing: cell shows "—" and a one-time toast suggesting auto-detect.
   - Sorting by margin asc / desc works in AG Grid.
```

**Verify**

```
composer test -- --filter "MarginCalculator"
npx playwright test margin-column.spec.ts
```

---

## PROMPT 31 — Command Palette (Feature C1)

```
Goal: Cmd/Ctrl+K opens a global command palette. Fuzzy match navigates, runs actions, searches data. Linear/Notion/Stripe-style. One source of truth for every action in the app — also feeds the shortcut sheet.

Depends on: PROMPT 11 (Router + Topbar), PROMPT 12 (shared components).

Output files

1. assets/admin/src/commands/registry.js
   - registerCommand({ id, title, category, keywords, run, hotkey, group, when }): void
   - getCommands(): Command[]
   - searchCommands(query, ctx): Command[]   // Fuzzy match via fuse.js (already small enough to bundle).

2. assets/admin/src/commands/builtins.js
   - Registers ~30 default commands across categories:
     - Navigate (Dashboard, Editor, Jobs, Rules, AI, Settings, Help)
     - Edit (Create job, Apply formula, Toggle column, Open last job)
     - Jobs (Pause running, Resume, Cancel, Rollback latest)
     - AI (Generate variations, Suggest prices, Suggest rule, Clean import)
     - Settings (Toggle dark mode, Toggle OLED, Reset UI preferences)
     - Data (Search variations, Open product…, Open job #…)
     - Help (Open shortcuts, Email support, Open docs, Show changelog)

3. assets/admin/src/components/CommandPalette/index.jsx
   - Modal with input + result list, grouped by category.
   - Recent commands persisted via localStorage (last 8).
   - Inline previews for data commands (e.g., "Open job #42" shows mini-summary).
   - Keyboard: ↑↓ to move, Enter to run, Esc to dismiss, Tab to scope to a category.

4. assets/admin/src/components/CommandPalette/triggers.js
   - Cmd/Ctrl+K toggles the palette.
   - / from any view (when no input focused) opens the palette pre-filtered to "Search".

5. assets/admin/src/components/Topbar.jsx (modify)
   - Replace the placeholder search field with a button "Cmd K · Search & commands" that opens the palette.

6. assets/admin/src/components/views/Help.jsx (modify)
   - "Keyboard shortcuts" panel is auto-generated from the command registry — every command with a hotkey becomes a row.

7. Extension API
   - Expose window.BulkVariations.registerCommand for any third-party JS to add commands (matched by typescript types when we publish them).

8. A11y
   - role="dialog", aria-modal="true", focus-trap, return focus on close.
   - Each result row has role="option", aria-selected, scrollIntoView on keyboard nav.

9. Tests
   - tests/Unit/commands/registry.test.js — register, search, run.
   - tests/E2E/command-palette.spec.ts — Cmd+K opens, "dash" matches Dashboard, Enter navigates.

Acceptance
   - Cmd/Ctrl+K on any view opens the palette in < 80ms.
   - Typing "rollback" returns the relevant command and recent jobs that match.
   - Recent commands persist across reloads.
   - Closing the palette returns focus to the trigger.
```

**Verify**

```
npm test -- registry.test.js
npx playwright test command-palette.spec.ts
```

---

## PROMPT 32 — Snackbar with Undo (Feature C2) + Aha Receipt (C11)

```
Goal: every bulk action surfaces a snackbar with an undo button that triggers a real rollback. Snackbar's secondary line shows the time-saved "aha receipt" pulled from a tiny client-side estimator.

Depends on: PROMPT 12 (Toast component already exists — extend it). PROMPT 06 (Rollback service). PROMPT 11 (store).

Output files

1. assets/admin/src/components/shared/Snackbar.jsx
   - Variant of Toast: bottom-right stack, max 3 visible, auto-dismiss after 8s with progress ring.
   - Body: primary line + optional secondary line (smaller, muted).
   - Action button (e.g., "Undo") + close X.
   - Respects prefers-reduced-motion.

2. assets/admin/src/store/index.js (extend)
   - Action pushSnackbar({ id, primary, secondary, actionLabel, action: () => Promise }).
   - Action dismissSnackbar(id).

3. assets/admin/src/api/snackbarMiddleware.js
   - Wraps every mutation via apiFetch:
     - On successful POST /jobs (bulk_edit, import, generate): pushSnackbar({
         primary: "Updated 47 variations.",
         secondary: "You'd have spent ~80 minutes manually.",
         actionLabel: "Undo",
         action: () => apiFetch({ path: `/bv/v1/jobs/${job.id}/rollback`, method: 'POST' })
       }).
   - "Time saved" estimator: const MIN_PER_VARIATION_MANUAL = 1.5; secondary = formatTime(rowCount * MIN_PER_VARIATION_MANUAL).
   - Threshold: skip the aha-line if rowCount < 5.

4. UndoOrchestrator
   - When the user clicks Undo within 8s, the snackbar morphs into a progress state ("Rolling back…") and disappears on success.
   - On failure: shows error inline; user keeps the snackbar to retry.

5. Settings toggle (PROMPT 18 General)
   - "Show time-saved estimates" (default on).
   - "Disable undo button on jobs > N rows" (default off, optional safety for risk-averse admins).

6. Tests
   - tests/Unit/snackbarMiddleware.test.js — intercepts mutations correctly.
   - tests/E2E/snackbar-undo.spec.ts — bulk edit → snackbar shows → click undo → variation price reverts.

Acceptance
   - Every bulk_edit job triggers a snackbar with Undo.
   - Clicking Undo within 8s actually rolls back via /jobs/{id}/rollback.
   - After 8s, the snackbar fades; the rollback is still available from the Jobs page.
   - Aha receipt hides for jobs < 5 rows.
```

**Verify**

```
npm test -- snackbarMiddleware.test.js
npx playwright test snackbar-undo.spec.ts
```

---

## PROMPT 33 — First-Run Experience (Feature C3) + Onboarding Cards (C10)

```
Goal: a 90-second interactive tour after activation that delivers the "aha moment" before the merchant gets bored. Plus a dashboard that grows smarter as the merchant uses the plugin.

Depends on: PROMPT 13 (Dashboard), PROMPT 14 (Bulk Editor), PROMPT 17 (AI Assistant), PROMPT 06 (Rollback).

Output files

1. src/Onboarding/OnboardingService.php
   - hasCompletedTour(int $user_id): bool — option bv_user_onboarding_{user_id}.
   - markStepComplete(int $user_id, string $step): void.
   - createDemoProduct(): int — only on demand from the tour; tags it 'bv-demo' so we can clean up.
   - cleanupDemoProduct(): void — deletes the demo + its variations.

2. REST
   - GET /onboarding         — returns { steps_completed[], demo_product_id|null }
   - POST /onboarding/complete  body: { step }
   - POST /onboarding/demo/create
   - POST /onboarding/demo/cleanup

3. React: views/Onboarding/Tour.jsx
   - 4-step modal that overlays the admin shell (not full-screen — leaves the shell visible).
   - Each step is real, not a mockup:
     Step 1 — "Pick a product" — opens the Bulk Editor with a product picker. User picks one OR clicks "Use demo product" (creates a real variable product with 8 variations under the hood).
     Step 2 — "Generate 5 variations with AI" — opens AI panel pre-prompted. If user has no AI key configured, uses a stubbed adapter that returns canned results so the demo works offline.
     Step 3 — "Bulk-edit price with preview" — toolbar opens with +15% formula prefilled; user clicks Apply → diff opens.
     Step 4 — "Rollback to safety" — surfaces the rollback button; clicking it reverts and shows the snackbar with the aha receipt.
   - Progress dots top-right. "Skip tour" link bottom-left. Skipping marks all steps complete.

4. React: views/Dashboard.jsx (extend)
   - First load after the tour: shows a "Next steps" card with three suggested actions tailored to the merchant's catalogue.
   - After 1 completed real job: surfaces "Try this: schedule a recurring CSV import →"
   - After 5 completed real jobs: surfaces a "Time saved this month" card with the aggregated aha receipt.
   - After 20 completed real jobs: surfaces "Ready to invite a teammate?" with link to Pro.
   - After a failed job: surfaces "That one didn't go through. Here's what happened + how to fix it" with a link to the Jobs page.

5. Cleanup
   - When the user uninstalls OR finishes the tour and clicks "Remove demo data", the demo product + its variations + analytics rows are purged.
   - If the user skips cleanup, the demo product remains a draft (not public).

6. A11y
   - Tour modal is fully keyboard-navigable.
   - Step transitions announce via aria-live.

7. Tests
   - tests/Integration/OnboardingTest.php — completing all 4 steps marks tour done.
   - tests/E2E/first-run.spec.ts — fresh activation → tour appears → walking through completes.

Acceptance
   - Activating the plugin on a fresh wp-env shows the tour on next admin page load.
   - Skipping the tour does not show it again.
   - Demo product is cleanly removable.
   - Dashboard onboarding cards appear at the documented thresholds.
```

**Verify**

```
composer test -- --filter "Onboarding"
npx playwright test first-run.spec.ts
```

---

## PROMPT 34 — Wire & Smoke Test Update (extends main pack PROMPT 26)

```
Goal: make sure the new features (PROMPTS 27–33) are wired into the same smoke test as the original 26-prompt pack.

Depends on: PROMPTS 27–33.

Tasks

1. Extend `wp bv smoke` to additionally:
   - Create a rule, dry-run it, assert matches > 0.
   - Trigger bulk_edit > 5 rows, verify it goes into preview state, programmatically approve, verify execution.
   - Hit /heatmap, assert non-empty response after seeding bv_events.
   - Verify command-palette command registry has > 25 commands.
   - Open the tour, advance through 4 steps via REST onboarding/complete, assert flag flips.

2. CI matrix update
   - Add Playwright specs from PROMPTS 27/29/31/32/33 to the e2e job.

3. Update RELEASE.md and readme.txt
   - Add the four uniqueness features to the changelog and the marketing description.
   - Refresh screenshots:
     - screenshot-7 Heatmap
     - screenshot-8 Rules builder
     - screenshot-9 Diff & approval drawer
     - screenshot-10 Command palette
   - Update upgrade notice for v1.0.

Acceptance
   - `wp bv smoke` prints ✔ on every step, including the 5 new ones.
   - CI green across the full matrix.
```

---

## Appendix — Running order

If you're running the full sequence end to end:

```
PROMPT 00  (porting reference)
PROMPT 01 → 26  (main pack — foundations through wire/smoke)
PROMPT 27  (visual diff & approval)
PROMPT 28  (auto-pilot rules engine)
PROMPT 29  (performance heatmap)
PROMPT 30  (profit margin column)
PROMPT 31  (command palette)
PROMPT 32  (snackbar with undo + aha receipt)
PROMPT 33  (first-run experience + dashboard cards)
PROMPT 34  (smoke test + CI + readme update)
```

Phase mapping (if you want to ship in stages):

- **Phase F.1 — Foundations of v1.0:** 27 + 30 (diff + margin column add the most credibility for the least surface area).
- **Phase F.2 — Hero features:** 28 + 29 (rules + heatmap — the "wow" pair).
- **Phase F.3 — UX premium:** 31 + 32 + 33 (palette + snackbar + first-run).
- **Phase F.4 — Ship:** 34.

## Appendix — Free / Pro split for the new features

| Feature | Free | Pro |
|---------|------|-----|
| Visual diff & approval (B1) | Available on jobs > 5 rows | Same + per-row reject + dual-approval gate |
| Auto-pilot rules (B2) | Up to 2 active, daily trigger only | Unlimited rules + event triggers + AI suggest |
| Performance heatmap (B3) | 7-day window | Up to 365-day window + drill-in + PNG export |
| Margin column (B6) | View only | "Suggest price for target margin" via AI |
| Command palette (C1) | Full | Full |
| Snackbar + Undo (C2) | Full | Full |
| First-run tour (C3) | Full | Full |
| Aha receipt (C11) | Toggleable | Toggleable |
