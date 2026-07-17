# CoderEmbassy Bulk Variations Manager for WooCommerce — Progress Tracker

Last updated: 2026-07-17

## Status: FREE v0.1.8 — RELEASE CANDIDATE

Free core scope is feature-complete and validated (PHPUnit 143 tests / 451 assertions, PHPStan clean, all PHP files lint clean, npm lint + build OK, release zip builds). v0.1.8 adds a generic, inert Extension API for developer hooks (`bv_booted`, `bv_admin_views`, `bv_admin_script_bundles`, `bv_grid_columns`, `bv_bulk_actions`) without adding Pro UI or Pro-specific hooks. The WordPress.org technical review findings were addressed on 2026-07-17: removed the Free licensing/feature-flag placeholder, removed `load_plugin_textdomain()`, added REST nonce verification, corrected the contributor, documented Google Fonts, removed unused `di52`, upgraded Action Scheduler to 4.0.0, and expanded release exclusions. The audited v0.1.8 ZIP contains production runtime files only. Remaining before resubmission: settle the display-name/slug concern with the review team and complete listing assets or clean-install QA if still required.

Known non-blocking caveats: no storefront variation grid in this release (admin-only scope); CSV "create" rollback reverts meta but leaves the variation post; `wp_insert_post` create failures are skipped silently; repo root has dev artifacts (`.phpunit.result.cache`, `.tmp-npd/`, build-staging folder) that should be gitignored.

## WordPress.org identity (2026-05-22)

- **Display name:** CoderEmbassy Bulk Variations Manager for WooCommerce
- **Slug / text domain:** `coderembassy-bulk-variations-manager`
- **Main plugin file:** `coderembassy-bulk-variations-manager.php`
- **Admin menu slug:** `coderembassy-bulk-variations-manager` (re-activate plugin after folder/file rename on existing sites)

## Current Build Targets

Free plugin repo:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations`

Pro addon source:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\coderembassy-bulk-variations-manager-pro`

Laragon localhost plugin folders:

- Free: `C:\laragon\www\plugins\wp-content\plugins\coderembassy-bulk-variations-manager`
- Pro: `C:\laragon\www\plugins\wp-content\plugins\coderembassy-bulk-variations-manager-pro`

Current Pro zip artifact:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\coderembassy-bulk-variations-manager-pro\artifacts\coderembassy-bulk-variations-manager-pro-0.1.0.zip`

## Product Strategy

Free plugin must show zero Pro UI:

- No locked Pro screens.
- No Pro badges.
- No blurred feature walls.
- No disabled Pro-only buttons inside the Free plugin.

Pro features should be advertised on the plugin website/sales page and injected only by the future Pro addon.

## Free Features Confirmed In Scope

> Canonical Free/Pro feature matrix: `cursor-prompt-pack-v2.md` → Appendix B "Full Free / Pro split (LOCKED)" (in the plan folder). The lists below are a working summary; if they disagree with Appendix B, Appendix B wins.

- Single-product variation spreadsheet editor.
- Product search for variable products.
- Visible column controls.
- Row selection.
- Inline editing for supported Free fields.
- Preview & approve workflow.
- Jobs screen.
- Job details.
- Rollback.
- CSV import with preview/approve.
- Background processing for larger jobs.
- Fast synchronous processing for small approved editor/import jobs.
- Displaying variation images.
- Displaying SKU values and editing SKU manually.

## Pro Features Reserved For Later Addon

- Cross-product editor.
- Batch image changing.
- Sale period bulk action from toolbar.
- SKU generation from pattern.
- Advanced formulas.
- Saved views.
- Templates.
- AI assistant.
- Automation/rules.
- Heatmaps/analytics.
- Quantity-tier pricing.
- Multi-site/team approval workflows.

## Pro Addon Progress (2026-06-09)

Pro addon source:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\coderembassy-bulk-variations-manager-pro`

Important architecture status:

- Pro is a separate addon plugin.
- Pro requires and reuses the Free plugin engine/services.
- Free remains the base plugin and must not contain Pro UI, Pro marketing, Pro feature gates, or addon detection.
- Free only exposes generic extension points and neutral field support.

Pro v0.1.0 feature checklist:

- [x] Pro addon bootstrap, activation dependency check, and admin integration through the generic extension API.
- [x] Dashboard Pro/version badges in the plugin banner when Pro is active.
- [x] Cross-product Editor view injected by Pro.
- [x] Product selection/search and multi-product variation loading.
- [x] Dynamic attribute filters based on the loaded variations; no hardcoded size/color-only logic.
- [x] Grouped variation tables by product, with per-product select all and collapse controls.
- [x] Row selection model with "select all in current view" behavior.
- [x] Pending change summary grouped by product before preview.
- [x] Pending summary now distinguishes real changes from rows that already match the target value.
- [x] Image column with per-row image preview and image change/remove actions.
- [x] Bulk image change for selected variations through Preview & Approve.
- [x] SKU generator for selected rows with prefix, first number, increment, and padding.
- [x] Price tools: set exact, increase/decrease by amount, increase/decrease by percent, round to nearest `.99`, clear sale price, set sale dates.
- [x] Stock tools: set quantity, increase/decrease quantity, set stock status, enable/disable stock management.
- [x] Physical tools: set/increase/decrease weight, length, width, height.
- [x] Tax/shipping tools: dropdown-only tax class and shipping class controls using real WooCommerce options.
- [x] Direct cell edit fan-out for safe set-same fields when multiple rows are selected.
- [x] Virtual/downloadable variation toggles.
- [x] Variation description editing.
- [x] Downloadable file fields: downloadable file name/URL list, download limit, and download expiry.
- [x] Advanced SKU token patterns, e.g. `{product}`, `{attribute_size}`, `{attribute_color}`, `{number}`.
- [x] Saved views / table presets.
- [x] Reusable bulk edit templates.
- [x] Loaded product group removal in the Cross-product Editor.
- [x] Advanced formulas for selected numeric fields.
- [x] Focused bulk tools UI: one selected tool panel is shown at a time instead of a long stacked control list.
- [x] AI assistant for bulk-edit help.
- [x] AI-assisted formula/action generation.
- [x] AI-assisted CSV mapping or cleanup suggestions.
- [x] Automation/rules groundwork: saved rule drafts and dry-run matching.
- [x] Rules dry-run matches can be sent to the shared Preview & Approve job workflow.
- [x] Rules drafts are persisted server-side through Pro REST endpoints instead of browser-only local storage.
- [x] Saved Rules can be manually run into Preview & Approve from a saved draft.
- [x] Saved Rules manual runs show a confirmation summary before creating a Preview & Approve job.
- [x] Saved Rules store preview-only schedule settings: manual, daily preview, or weekly preview.
- [x] Automation / rules engine foundation: scheduled rules create preview-only jobs through WP-Cron/admin checks.
- [x] Saved automation presets.
- [x] Scheduled bulk changes.
- [x] Conditional bulk rules, e.g. "increase only large blue variations".
- [ ] Heatmaps / analytics.
- [ ] Quantity-tier pricing.
- [ ] Multi-site workflows.
- [ ] Team approval workflows.
- [ ] Advanced reporting/history exports.
- [x] Pro license SDK, REST routes, updater wiring, and SPA License screen integration from `LICENSE_INTEGRATION_GUIDE.md`.
- [ ] License server product registration + production HMAC secret injection for release builds.
- [x] Pro package/build cleanup: `npm install`, `npm run lint:js`, and `npm run build` work in the Pro addon.
- [x] Full Pro QA pass: preview, apply, WooCommerce verify, rollback for every Pro action.
- [x] Pro zip install/package QA.

Implemented neutral Free support used by Pro:

- [x] Generic field/meta handling for image ID, stock management, virtual/downloadable, downloadable files/limits, weight, dimensions, tax class.
- [x] VariationRepository now returns image, sale dates, stock management, virtual/downloadable, downloadable files/limits, description, weight/dimensions, tax class, and shipping class ID data.
- [x] BulkEditor can apply neutral meta fields, downloadable files/limits, variation description, and shipping class taxonomy changes through the existing job workflow.
- [x] Job diff labels were added for image, stock management, virtual/downloadable, downloadable files/limits, description, weight/dimensions, tax class, and shipping class.

Latest Pro validation:

- `npm run build` passed in the Free repo after neutral Free changes.
- PHP lint passed for touched Free PHP files.
- PHP lint passed for Pro `src\REST\CrossProductController.php`.
- Pro compiled admin JS passed `node --check`.
- Files were synced to the Laragon localhost plugin folders after each batch.
- Pro zip rebuilt at `artifacts\coderembassy-bulk-variations-manager-pro-0.1.0.zip`.
- Fixed downloadable file meta storage so WooCommerce receives file arrays instead of double-serialized strings, and added Woo approved-directory handling so customer downloads are not hidden as disabled/untrusted files; local order `#194` download permissions were regenerated after repairing variation `#188`.
- Pro storefront now adds a product-page downloadable badge for selected downloadable variations.
- Cross-product Editor loaded product groups can now be removed before preview/apply, clearing that product's rows, selected variations, pending drafts, image previews, collapsed state, and cached combination warnings.
- Advanced formula panel added for selected rows across regular price, sale price, stock quantity, weight, length, width, and height. Formula drafts use the existing Preview & Approve workflow and can be saved in bulk edit templates.
- Runtime QA pass created a temporary variable product/order, verified Pro cross-product loading, dynamic attributes, SKU token preview, preview/apply, formulas, stock, virtual/downloadable files, customer download permission, description, physical fields, tax/shipping class, SKU, and rollback. Temporary product/order/shipping class/custom QA job rows were cleaned up.
- Fixed rollback mapping for shipping class history (`product_shipping_class` -> `shipping_class_id`) so Pro tax/shipping changes can be restored correctly through the shared Free rollback engine.
- Cross-product Editor empty state now hides disabled bulk-edit panels until variations are loaded.
- Cross-product Editor loaded-state UI now uses a compact Bulk tools switcher so users choose one focused action panel at a time while the product tables stay easy to reach.
- Pro build tooling is installed and validated. `npm run lint:js` and `npm run build` now pass in the Pro addon, so compiled admin JS can be generated from source instead of patched manually.
- Pro release packaging now runs through `npm run package:release`, builds admin JS first, and creates a clean install zip with runtime files only. The generated zip was extracted to a temp install folder, packaged PHP passed lint, packaged admin JS passed `node --check`, and the zip excludes `node_modules`, package files, admin source JSX, scripts, and planning docs.
- Follow-up Pro runtime QA found one rollback gap: variation status changes were applied but had no history delta. Free now records `post_status` history, rollback maps it back to the neutral `status` field, and job diff labels render it as `Status`. PHP lint, Free `npm run lint:js`, Free `npm run build`, and Free PHPUnit all pass.
- Full Laragon Pro runtime QA rerun passed: 71 checks, 0 failures. It verified Pro cross-product loading, dynamic attribute metadata, SKU preview, WooCommerce class endpoints, preview/apply workflow, downloadable customer permissions, all currently implemented Pro field writes, and rollback restoration including variation status.
- Pro Formula tool now includes a local assistant prompt that converts plain-language requests such as "increase regular price by 10%" or "round sale price to .99" into a target field and formula expression. It does not call an external provider yet; generated formulas still require Add formula changes and Preview & Approve.
- Pro `npm run lint:js`, `npm run build`, compiled admin JS `node --check`, and `npm run package:release` passed after the formula assistant batch. The Pro zip was rebuilt and verified clean, then admin CSS/dist assets were synced to Laragon.
- Pro CSV Assistant view added as a Pro-only admin view. It loads product-specific CSV template headers, maps messy source columns to importer-safe headers, normalizes common date/status values, flags duplicate SKUs and obvious numeric/date issues, and downloads/copies a cleaned CSV for the existing Free CSV Import workflow.
- Pro `npm run lint:js`, `npm run build`, compiled admin JS `node --check`, and `npm run package:release` passed after the CSV Assistant batch. The Pro zip was rebuilt and verified clean, then admin CSS/dist assets were synced to Laragon.
- CSV Assistant follow-up: strengthened messy header mapping so `Product` reliably maps to `product_id`, `Variant SKU` maps to `sku`, and common attribute names such as frame/size/color can become `attribute_*` columns when exact product headers are not loaded. Pro lint/build/package checks passed and assets were resynced to Laragon.
- CSV Assistant follow-up: fixed cleaned CSV output so mapped generic attribute columns such as `attribute_frame`, `attribute_size`, and `attribute_color` are included in the generated CSV even when those columns were inferred instead of loaded from the product template.
- Free CSV Import follow-up: tightened preview validation so non-numeric prices, non-whole-number stock quantities, and unsupported stock/status values are rejected before Preview & Approve. Import preview now returns warnings and sample rows, and the admin UI joins row `issues`/`warnings` messages instead of showing blank invalid rows. PHP lint, targeted importer tests, full Free PHPUnit, JS lint, Free build, and Free release packaging passed; patched Free files were synced to Laragon.
- Free CSV Import UI polish: preview results now show a clear continue/fix status message, separate "Rows to fix", "Warnings to review", and "Rows ready for preview" sections, and sample rows include extra CSV columns such as product attributes. Free JS lint and build passed after the polish.
- Free rollback follow-up: fixed rollback of CSV-created variations when `_regular_price` and `_price` history deltas target the same variation. Rollback now groups deltas by variation before calling the shared BulkEditor and reports processed history items so rollback jobs can complete at 100%. PHP lint, focused rollback tests, full Free PHPUnit, and JS lint passed.
- Pro Cross-product Editor now has a local Bulk edit Help tool. It accepts plain-language prompts, recommends the right bulk tool, explains next steps, warns when row selection is needed, and can open the suggested tool without creating pending edits. Pro `npm run lint:js`, `npm run build`, compiled admin JS `node --check`, and `npm run package:release` passed; runtime CSS/dist assets were synced to Laragon.
- Pro Bulk edit Help follow-up: price prompts such as "increase regular price by 10%" now prefill the Prices tool before opening it, while formula prompts can prefill the Formula tool. Users still add drafts manually and continue through Preview & Approve. Pro `npm run lint:js`, `npm run build`, compiled admin JS `node --check`, and `npm run package:release` passed; runtime assets were resynced to Laragon.
- Pro Rules view added as automation groundwork. It creates browser-saved rule drafts, searches/selects variable products, dry-runs matching variations through the existing Pro cross-product variation endpoint, and previews what numeric price/stock actions would become without applying or scheduling anything. Pro `npm run lint:js`, `npm run build`, compiled admin JS `node --check`, and `npm run package:release` passed; runtime CSS/dist assets were synced to Laragon.
- Pro Rules dry-run UI polish: result columns now explicitly show the matched condition field, the rule threshold, the current action field, and the projected value after the action so merchants can verify why each row matched. Pro lint/build/package checks passed and runtime assets were resynced to Laragon.
- Pro Rules dry-run table polish: results now use a cleaner full-width table with compact headers, product/variation/SKU grouped into one identity column, and pill-style matched/current/result values for faster scanning. Pro lint/build/package checks passed and runtime assets were resynced to Laragon.
- Pro Rules Preview & Approve bridge: dry-run matches now have a "Send to Preview & Approve" action that converts changed matches into normal Free `bulk_edit` preview jobs with source `pro_rules`, skips no-op/invalid projected values, and opens the shared job diff screen. Pro lint/build/package checks passed and runtime assets were resynced to Laragon.
- Pro Rules server-side drafts: added Pro REST draft routes under `bv-pro/v1/rules` backed by per-admin WordPress user meta, and updated the Rules UI to load/save/delete drafts from WordPress instead of browser-only `localStorage`. PHP lint, Pro JS lint/build/package checks, compiled JS syntax checks, and Laragon synced-file syntax checks passed.
- Pro Rules manual run: selected saved drafts can now load their saved product selection, run the rule immediately, create a normal Preview & Approve job, and open the shared job diff screen without scheduling or auto-applying. Pro JS lint/build/package checks, PHP lint for the Rules REST controller, compiled JS syntax checks, and Laragon synced-file syntax checks passed.
- Pro Rules confirmation step: saved rule runs now stop at a review card showing selected products, loaded rows, matched rows, rows with real changes, a sample of changed variations, and an explicit "Create Preview Job" action before opening the shared diff screen. Pro JS lint/build/package checks, PHP lint for the Rules REST controller, compiled JS syntax checks, and Laragon synced-file syntax checks passed.
- Pro Rules preview-only scheduling: saved drafts now persist sanitized schedule metadata (`manual`, `daily`, `weekly`, `HH:MM` time, weekday), the rule builder exposes schedule controls, and the saved drafts panel shows each draft's schedule status. No background automation or auto-apply behavior has been enabled yet. Pro JS lint/build/package checks, PHP lint for the Rules REST controller, compiled JS syntax checks, and Laragon synced-file syntax checks passed.
- Pro Rules scheduled preview engine: preview schedules now run through a Pro `RuleScheduler` registered with WP-Cron and admin-page checks, and the Rules screen can also poll/check due schedules while open. Due rules create normal Free Preview & Approve jobs with source `pro_rules_schedule`; rules without saved products are skipped with a visible status; rules with no real changes are marked as no-change. No auto-apply behavior is enabled. PHP lint, Pro JS lint/build/package checks, compiled JS syntax checks, package rebuild, and Laragon synced-file syntax checks passed.
- Pro Rules scheduled preview follow-up: schedule checks now refresh the loaded draft status and show an "Open Preview Job" button for the latest scheduled Preview & Approve job, making created jobs easy to find from the Rules screen. Pro JS lint/build/package checks passed, the Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules conditional fields: selected products now load their variation attribute columns into the Rules condition field dropdown, and drafts support extra AND conditions so rules can target combinations such as Size = Large and Color = Blue. Saved drafts and scheduled preview runs safely preserve/evaluate `attribute_*` condition fields while still creating Preview & Approve jobs only. Pro JS lint/build/package checks passed, the Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules condition value polish: known fields now use clearer value controls. Status, stock status, virtual/downloadable, and loaded product attributes show value dropdowns when possible; free-text fields show a "Value to match" placeholder; empty/not-empty operators show that no value is needed. Pro JS lint/build/package checks passed, the Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules condition builder simplification: the rule builder now uses a sentence-style "Match variations" filter card with "Where" and "And" rows, context-aware match options, and an "Add filter" action so combination rules are easier to build without decoding field/condition/value columns. Pro JS lint/build/package checks passed, the Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules saved automation presets: the Rules screen now includes preset buttons for common rule drafts such as high-price markdown, large blue discount, low-stock price bump, digital product markdown, and daily high-price preview. Presets populate the simplified rule builder without saving or applying until the merchant dry-runs or saves them. Pro JS lint/build/package checks passed, the Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules run-history/status visibility: rule draft save now preserves existing schedule runtime fields instead of wiping them, each scheduled run appends a capped sanitized `runHistory` entry (with status, message, job ID, matched/changed counts), and manual "Create Preview Job" actions on saved rules also append history before opening the shared Preview & Approve diff. Rules UI now shows recent run history entries with quick "Open #job" links. Pro JS lint/build/package checks passed, Pro zip was rebuilt, PHP lint passed for touched Pro backend files, and runtime files were synced to Laragon.
- Pro Rules scheduled control-center (small safe slice): the Saved drafts panel now includes a dedicated "Scheduled runs" list that aggregates recent scheduled history across drafts, sorted newest first, with matched/changed counts, status message, and quick actions to "Open #job" and "Load rule". Pro JS lint/build/package checks passed, Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules scheduled control-center deepening: added triage filters (`All`, `Created preview`, `No changes`, `Failed`, `Skipped`), search (`rule/message/#job`), and health counters (total runs, previews created, failures) above the Scheduled runs list. Run rows now also show normalized status badges for faster at-a-glance diagnosis before opening jobs. Pro JS lint/build/package checks passed, Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules scheduled controls deepening: added per-rule `schedulePaused` support (REST sanitization + scheduler guard) and a Schedule card Pause/Resume toggle in the Rules UI. Paused rules keep their draft configuration/history but are skipped by due-run checks until resumed. Schedule labels now show `(Paused)` for daily/weekly rules when paused. Pro JS lint/build/package checks passed, Pro zip was rebuilt, PHP lint passed for touched Pro backend files, and runtime files were synced to Laragon.
- Pro Rules scheduled controls deepening: added a `Run now` control path for saved drafts. Backend now exposes `POST /bv-pro/v1/rules/{id}/run-now`, runs the selected rule immediately for the current admin user, appends normalized run-history/status metadata, and returns updated rules + created job ID. Rules UI now includes `Run now` actions in Saved drafts and Scheduled runs entries, with inline running states and direct navigation when a preview job is created. Pro JS lint/build/package checks passed, Pro zip was rebuilt, PHP lint passed for touched Pro backend files, and runtime files were synced to Laragon.
- Pro Rules reliability guardrails: `Run now` now has backend cooldown locking (20s transient per user/rule) to reduce accidental duplicate preview creation from rapid clicks. A throttled manual run records status `manual_throttled` with a clear wait message. Scheduled control-center now surfaces throttled counts and supports filtering by throttled status; failed rows expose a contextual `Retry failed` action that re-runs the rule immediately. Pro JS lint/build/package checks passed, Pro zip was rebuilt, PHP lint passed for touched Pro backend files, and runtime files were synced to Laragon.
- Pro Rules reporting follow-up: Scheduled runs control-center now supports CSV export for the currently filtered/searched run set. Export includes rule name, timestamp, source, status, message, job ID, matched count, and changed count, enabling quick sharing/auditing outside wp-admin. Pro JS lint/build/package checks passed, Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules saved-drafts triage polish: the Saved drafts list now shows each draft's latest run timestamp and normalized outcome badge (derived from run history with fallback to last schedule metadata), so operators can spot stale/failed rules without opening each draft first. Pro JS lint/build/package checks passed, Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules controls follow-up: Saved drafts now include inline `Pause` / `Resume` actions per rule row, so schedule state can be changed quickly without opening the draft editor. The action persists immediately through existing Pro rule save routes and shows inline busy state while saving. Pro JS lint/build/package checks passed, Pro zip was rebuilt, and runtime assets were synced to Laragon.
- Pro Rules QA fix batch: weekly schedules now compare locale-independent weekday keys (`monday`…`sunday`) instead of localized day names; `Run now` cooldown lock is applied only after the rule draft is found; Saved drafts rows no longer nest buttons inside buttons (load row is a focusable div); `manual_preview` now has a proper status label and is included in preview stats/filters. Pro PHP lint, JS lint/build/package checks passed, Pro zip was rebuilt, and runtime files were synced to Laragon.

- Pro Scheduled bulk changes: pending Preview & Approve jobs can now be scheduled to apply later from the shared job diff screen. Free only exposes neutral job-diff action/visibility filters; Pro injects the "Apply later" control, stores schedule metadata on the existing job, hides immediate Apply while a schedule is active, supports cancel, and runs due schedules through the existing Free `JobManager::resumeAfterApproval()` path. This does not add recurring auto-apply rules. Free JS lint/build passed; Pro PHP lint, JS lint/build/package checks passed; compiled admin JS passed `node --check`; runtime files were synced to Laragon and the Pro zip was rebuilt.
- Pro Scheduled bulk changes localhost fallback: scheduled apply now has a Pro REST "check scheduled apply" path and the job diff UI sets a due-time timer/check button, so overdue schedules can apply while the admin screen is open or after refresh even if local WP-Cron/Action Scheduler does not wake exactly on time. Pro PHP lint, JS lint/package checks, compiled JS syntax check, zip rebuild, and Laragon sync passed.
- Scheduled apply progress QA fix: completed scheduled jobs now normalize final processed/progress counters when the stored job row reports `0 / total`, and the Jobs UI displays completed jobs as 100% with rollback available when change history exists. Free JS lint/build passed; Pro PHP lint/package checks passed; compiled Free/Pro JS syntax checks passed after Laragon sync.
- Pro License integration: copied the shared CoderEmbassy SDK into the Pro addon, added `LicenseSdkBootstrap`, Pro REST license routes (`GET /license`, activate/deactivate/check/modal-shown), updater wiring, localized license data, and replaced the placeholder Pro License view with an activation/status/deactivation screen. The release package now includes `includes/` so the SDK is present in the zip. For source safety, the HMAC constant remains empty with the `<CE_SECRET_INJECT />` marker; local testing can use `BV_PRO_HMAC_SECRET`, and production still needs license-server product registration plus release-time secret injection.
- Pro License UI follow-up: `Check status` no longer checks an empty saved-license state while a new key is typed into the input. If a key is entered and no active license is saved, the button now runs the activation/check path for that key; failed activation responses render as errors instead of misleading success notices. Pro JS lint/package checks passed, the Pro zip was rebuilt, and runtime admin JS was synced to Laragon.
- User documentation batch: added a Free-only user guide at `docs/USER_GUIDE_FREE.md` plus `docs/CoderEmbassy-Bulk-Variations-Manager-Free-User-Guide.docx`, and a Pro addon user guide at `docs/USER_GUIDE_PRO.md` plus `docs/CoderEmbassy-Bulk-Variations-Manager-Pro-User-Guide.docx` in the Pro source. The Pro package script now includes `docs/` so the Pro user guide files ship in the addon zip.

Known Pro notes / next likely work:

- Continue one feature batch at a time and let the user test in Laragon after each.
- Next likely Pro batch: heatmaps/analytics, quantity-tier pricing, team/multisite workflows, advanced reporting, or production license-server registration.
- License SDK/UI integration is in place. Production activation requires registering `coderembassy-bulk-variations-manager-pro` on the license server and injecting the real HMAC secret during release packaging.
- Keep Pro `package-lock.json` with the source addon for reproducible admin builds. Do not include `node_modules` in install zips.

## Completed And Verified

### Product Search

- Product search endpoint works.
- ProductPicker can find variable products by name.
- Bulk Editor loads variations after selecting a product.

### Bulk Editor

Verified working in Laragon:

- Change SKU -> apply -> verify in WooCommerce -> rollback.
- Change regular price -> apply -> verify in WooCommerce -> rollback.
- Change sale price -> apply -> verify in WooCommerce -> rollback.
- Change sale from / sale to -> apply -> verify -> rollback.
- Change stock quantity -> apply -> verify -> rollback.
- Disable one variation -> verify status -> rollback.
- Select 2-3 rows -> set price -> verify all changed.

Important fixes already made:

- `_price` sync was added when regular/sale price changes.
- Sale price and scheduled sale dates now apply correctly.
- Sale date rollback works.
- Small editor jobs complete synchronously when safe.
- Status pills improved: stock green, publish blue.
- Grid spacing and first column cut-off were fixed.
- Row action column is always visible.
- Empty SKU cells show a muted placeholder because the tested product has no SKU values.

### Jobs

- Jobs list implemented.
- Job detail implemented.
- Preview/diff page implemented.
- Pending review orphan jobs can be discarded.
- Old orphan jobs were discarded/cancelled.
- Completed jobs show applied changes and rollback.
- Rollback creates a separate rollback job. This is correct because rollback should be audited.
- Progress percent displays.
- Small approved editor jobs now finish quickly instead of waiting on Action Scheduler.

### CSV Import — update existing variations (smoke test passed)

Manual smoke test passed in Laragon (existing variations only):

- Validate → Preview & Approve → Apply → WooCommerce verification → Rollback.
- Fields verified: SKU, regular price, sale price, sale dates, stock quantity, status.
- Sync apply toast and fast completion for small imports.
- Rollback restored pre-import values.

Important CSV fixes already made:

- Critical error from CSV preview was fixed.
- Endpoint now returns valid JSON instead of raw WordPress fatal HTML.
- CSV preview UI no longer shows raw JSON by default.
- Preview UI uses summary cards, invalid/warning sections, sample rows table, and optional technical details.
- Import diff omits fake Product ID changes.
- Import diff omits unchanged fields.
- Import apply toast now says "Import applied." for sync imports.

## Recent Validation

Last reported passing checks:

- PHPUnit: 136 tests / 432 assertions.
- PHPStan: OK.
- `npm run lint:js`: OK.
- `npm run build`: OK.
- `composer package:release`: OK.

## Known Design Notes

The UI has been improved but should still get a dedicated polish pass later:

- Keep the grid readable and compact.
- Avoid huge unused whitespace.
- Make buttons obvious but not visually noisy.
- Keep helper text short and user-friendly.
- Dark mode should not make controls disappear.
- Top-right theme/user control should look like one compact pill/card with background and shadow.

## CSV Import Smoke Test (3–5 existing variations)

Use real `product_id` + `variation_id` values from your variable product (e.g. product **18** in Laragon).

**Before import:** In WooCommerce, note each variation’s current SKU, regular/sale price, sale schedule, stock qty, and status.

**CSV columns (header row):**

`product_id,variation_id,sku,regular_price,sale_price,sale_from,sale_to,stock_quantity,stock_status,status`

**Sample rows (replace variation IDs; use unique SKUs if you change them):**

```csv
product_id,variation_id,sku,regular_price,sale_price,sale_from,sale_to,stock_quantity,stock_status,status
18,19,CSV-SMOKE-19,599,499,2026-06-01,2026-06-30,10,instock,publish
18,20,CSV-SMOKE-20,649,,,,15,instock,publish
18,21,CSV-SMOKE-21,699,599,2026-07-01,2026-07-31,8,instock,publish
18,22,CSV-SMOKE-22,749,649,2026-08-01,2026-08-15,5,outofstock,publish
18,23,CSV-SMOKE-23,799,,,,20,instock,publish
```

**Flow:** CSV Import → Validate → Preview & Approve → diff (only changed fields) → Apply → verify in WC → Rollback on job detail.

**Acceptance:** 5 valid rows; sync apply toast “Import applied.”; WC values match CSV; rollback restores pre-import values for SKU, prices, sale dates, stock, status.

**Known limits:** Empty CSV cells do not clear existing values. Job history may include `_price` alongside price fields (rollback still restores it).

## Bulk Editor — read-only attribute columns (2026-05-22)

**Decision:** Show parent-product variation attributes as read-only columns in the Bulk Editor grid (after Image, before prices) so users can identify each row.

**Implemented:**

- `GET /bv/v1/variations` now returns `{ variations, attribute_columns }` (labels from `ImportAttributeReadiness::getEditorAttributeColumns()`).
- Variation rows already include `attribute_*` meta; repository test added.
- Dynamic AG Grid columns: read-only, slug → label formatting, visible in **Customize table** (default on after Image).
- JS unit tests: `columnUtils.test.js` (column order, parsing, catalog merge).

**Manual test checklist:**

1. Open Bulk Editor → select variable product **18**.
2. Confirm **Color / Size / Frame** (or your product’s attributes) appear after Image.
3. Values show human-readable labels where term labels exist.
4. Toggle attribute columns in **Customize table**; grid updates.
5. Edit SKU/price/stock still works; attribute cells are not editable.

## CSV Import — attribute readiness helper (2026-05-22)

**Decision:** Show parent-product variation attribute columns on the CSV Import screen when a default product ID is set, so users do not need to open WooCommerce product edit to discover CSV headers and allowed slugs.

**Implemented (Free):**

- `GET /bv/v1/products/{id}/import-attributes` — `ImportAttributeReadiness` service (WC `get_variation_attributes()` when available, `_product_attributes` meta fallback).
- CSV Import UI: debounced auto-load + **Load attributes** button; compact table (CSV column, label, allowed values/slugs).
- Warning when all attribute combinations already exist (add options in WooCommerce first).
- Create-row **warnings** (not errors) when CSV uses an unknown attribute column or slug vs parent product (`ImportValidator` + `CsvImporter` when default `product_id` is set).

**Manual test checklist:**

1. Open CSV Import, enter variable product ID (e.g. 18), confirm attribute table loads (auto or **Load attributes**).
2. Confirm CSV column names match `attribute_pa_*` (or custom `attribute_*`) shown in the table.
3. Upload a create-row CSV with a wrong slug → Validate → warning on that row (row still valid).
4. Upload a create-row CSV with correct slugs → Validate → approve → apply → new variation in WC.
5. On a product where every combo exists, confirm “all combinations already exist” warning appears.

## CSV Import — download template (2026-05-22)

**Decision:** When a default product ID is set and attribute readiness loads, offer a one-click CSV template with correct headers and example rows for that product.

**Implemented (Free):**

- `ImportCsvTemplate` service — base headers (`product_id`, `variation_id`, `sku`), product `attribute_*` columns from readiness, editable Free fields (`regular_price`, `sale_price`, `sale_from`, `sale_to`, `stock_quantity`, `stock_status`, `status`).
- Two sample rows: update-style (blank `variation_id`, placeholder SKU) and create-style (valid attribute slugs, preferring a combination not already on the product).
- `GET /bv/v1/products/{id}/import-csv-template` — returns `filename`, `headers`, `csv`.
- CSV Import UI: **Download CSV template** button in the attribute helper header (enabled when readiness payload is loaded).

**Manual test checklist:**

1. CSV Import → enter variable product ID (e.g. 18) → wait for attribute table (or **Load attributes**).
2. Click **Download CSV template** → file `import-template-product-18.csv` downloads.
3. Open CSV: header row includes `product_id`, `variation_id`, `sku`, your `attribute_pa_*` columns, then price/stock/status columns.
4. Row 1: update example (`your-existing-sku`, blank `variation_id`); row 2 (if product has attributes): create example with valid slugs (e.g. unused color slug).
5. Edit placeholders → **Validate CSV** → preview/apply as usual.

## CSV Import — create new variations (verified)

Code path exists and is unit-tested: `CsvImporter` → `ImportValidator` → pending job → `ImportJob` → `VariationWriter::createBatch` (+ `HistoryLogger`). **Manually smoke-tested in Laragon (create + rollback).**

### 1. Is create supported?

**Yes**, for CSV rows **without** `variation_id` (omit the column or leave cells empty). Rows are validated, previewed, applied via `VariationWriter::createBatch`, and logged for rollback.

**Caveats:**

- A **new SKU** is required. If the SKU already exists in the shop, the validator maps the row to an **update** path instead of create.
- `wp_insert_post` failures are skipped silently (processed count may be lower than row count with no per-row error).
- Rollback **does not** trash/delete the new variation post; it removes/reverts meta written on create.
- `status` on create is set on the post directly and is **not** in job history (rollback cannot revert post status).

### 2. Required CSV columns (create)

| Column | Required | Notes |
|--------|----------|--------|
| `product_id` | Yes | Parent variable product ID. Can come from CSV and/or **Default product ID** on the Import screen. |
| `sku` | Yes | Unique in file and not already used by another variation (unless you intend an update). |
| `attribute_*` | Yes (≥1) | At least one non-empty attribute column, e.g. `attribute_pa_color`. Header `pa_color` is normalized to `attribute_pa_color`. |
| `variation_id` | No | Omit for create rows. |
| `regular_price`, `sale_price`, `sale_from`, `sale_to`, `stock_quantity`, `stock_status`, `status` | No | Same rules as update import; empty cells do not clear values. |

### 3. Parent product prerequisites (product 18 example)

Before running the test:

1. Product **18** must be a **Variable product** with attributes configured under **Products → Attributes** and assigned on the **Attributes** tab (e.g. Color, Size as global `pa_color`, `pa_size`).
2. Each `attribute_*` value in the CSV must be a valid **term slug** for that attribute (e.g. `blue`, `xl` — not display labels unless they match the slug).
3. The attribute **combination must not already exist** on product 18 (check existing variations 19–23 in admin).
4. Note exact meta keys from an existing variation (e.g. `attribute_pa_color`, `attribute_pa_size`) and use the same column names in the CSV.

### 4. Safe sample CSV (1 new variation)

Replace `attribute_pa_*` names/values with ones that match product 18 and a **new** combination. Set **Default product ID** to `18` on the Import screen (or keep `product_id` in the file).

```csv
product_id,sku,attribute_pa_color,attribute_pa_size,regular_price,stock_quantity,stock_status,status
18,CSV-CREATE-SMOKE-01,blue,xl,499,7,instock,publish
```

Optional second row (only if the combo + SKU are unique):

```csv
18,CSV-CREATE-SMOKE-02,green,medium,549,5,instock,publish
```

### 5. Expected preview / apply / rollback

| Step | Expected |
|------|----------|
| **Validate** | 1 valid row; 0 invalid (or invalid if combo/SKU/product_id wrong). |
| **Preview UI** | Summary cards; sample row shows attributes + SKU + prices; no raw JSON by default. |
| **Preview & Approve job** | Diff shows create fields with **empty old values**; placeholder `variation_id` / `object_id` **-1** (first row). Fields include `attribute_*`, `sku`, prices, stock; **not** `product_id`. |
| **Apply** | Toast **Import applied.** for small jobs; new variation under product 18 in WooCommerce; SKU/prices/stock match CSV. |
| **WC check** | New row in **Variations** tab; attribute pills correct; optional: variable product price range updates after save/cache. |
| **Rollback** | Separate rollback job; meta reverted (SKU/prices/stock/attributes cleared or removed). **Variation post may still exist** (possibly empty/disabled-looking) — manually trash in WC if you want it removed. |

### 6. Manual test steps

1. Note current variation count for product 18.
2. CSV Import → upload sample → **Default product ID: 18** → **Validate**.
3. **Preview & Approve** → review diff → **Apply**.
4. WooCommerce → product 18 → Variations: confirm new variation + attribute combo + SKU `CSV-CREATE-SMOKE-01`.
5. Jobs → completed import → **Rollback** → confirm meta reverted; accept that the post may remain.
6. Cleanup: trash orphan variation in WC if needed.

### 7. Tests coverage

- `ImportValidatorTest` — create requires `product_id` + `attribute_*`
- `ImportJobTest` — routes create rows to `VariationWriter::createBatch`
- `VariationWriterTest` — insert + history logging
- `ImportPreviewChangesTest` — create preview diff rows
- `RollbackJobTest` — attribute meta rollback mapping
- `CsvImporterTest` — `test_preview_import_accepts_create_row_with_attributes`

**Gaps (no integration test yet):** full apply + WC post creation, duplicate attribute combo handling, wrong term slug behavior.

## Settings screen + release packaging restore (2026-05-24)

**Implemented (Free):**

- Real **Settings** screen replaced the placeholder route.
- `GET /bv/v1/settings` and `POST /bv/v1/settings` persist Free-safe preferences.
- Settings available:
  - Theme: `auto`, `light`, `dark`.
  - Default product ID for CSV Import.
  - Jobs per page: `20`, `50`, or `100`.
  - Remove plugin data on uninstall (backs existing `bv_uninstall_remove_data` behavior).
- CSV Import now reads the saved default product ID.
- Jobs screen now reads the saved jobs-per-page value.
- Release packaging files restored:
  - `.distignore`
  - `readme.txt`
  - `scripts/package-release.php`
  - `scripts/package-release.ps1`
  - Composer script `package:release`.

**Manual test checklist:**

1. Open **Settings** from the plugin sidebar.
2. Change theme and save; confirm the admin app theme changes and survives refresh.
3. Set Default product ID (e.g. 18), save, then open **CSV Import** and confirm it pre-fills.
4. Change Jobs per page, save, then open **Jobs** and confirm the list loads normally.
5. Toggle Remove plugin data on uninstall only when you intentionally want cleanup during uninstall.
6. Run `npm run build` before packaging.
7. Run `composer package:release`; confirm `artifacts/coderembassy-bulk-variations-manager-0.1.7.zip` is created.

## Bulk Editor apply bar visibility (2026-05-24)

**Fixed:** The sticky apply bar no longer appears just because a product has rows loaded. It now appears only when there are pending grid changes or an apply-preview request is submitting.

**Manual test checklist:**

1. Open Bulk Editor and select a product.
2. Confirm the bottom **Discard / Preview & Approve** bar is hidden before any edits.
3. Change one editable cell and commit the edit.
4. Confirm the bar appears and shows pending changes.
5. Click **Discard** and confirm the bar disappears after rows reload.

## Free Release Completion Checklist

Core Free scope is feature-complete. Remaining work before publishing is release QA and packaging verification.

1. Run a final manual QA pass in Laragon:
   - Bulk Editor: edit SKU, prices, sale dates, stock, status -> preview -> apply -> rollback.
   - Confirm the sticky apply bar is hidden until a real grid change exists.
   - CSV update existing variations -> preview -> apply -> rollback.
   - CSV create new variation -> preview -> apply -> rollback; remember the known caveat that the created variation post may remain.
   - Settings: save theme, default product ID, jobs per page, and uninstall cleanup preference.
2. Build and install the release zip on a clean WordPress + WooCommerce site.
3. Confirm the zip has the correct root folder, includes production `vendor/`, and excludes tests, `node_modules`, source admin JS, and planning docs.
4. Confirm no Pro UI, Pro locks, Pro badges, or disabled Pro-only buttons appear in the Free plugin.
5. Finalize WordPress.org assets:
   - `readme.txt` wording.
   - Screenshots.
   - Optional plugin banner/icon assets.
6. Optional Free polish after release candidate:
   - Default visible columns setting.
   - Editor density / thumbnail size.
   - Confirmation toggles for rollback/delete/disable.
   - Job retention setting.
   - CSV default status / stock status.

## Superseded New Chat Starting Prompt (Do Not Use)

This project is continuing in Claude Code. Open Claude Code in the repo folder
(`C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations`) and paste the
prompt below to start.

```text
I'm building a WooCommerce plugin: "CoderEmbassy Bulk Variations Manager for
WooCommerce". You are continuing the project in Claude Code.

Repo (Free plugin): the current working directory.
Main file: coderembassy-bulk-variations-manager.php

STEP 1 — Read these before doing anything, as the source of truth:
  1. PROJECT_PROGRESS_TRACKER.md  — what's done, Free vs Pro scope, caveats, next steps.
  2. cursor-prompt-pack-v2.md     — the full build plan + the "FREE / PRO PLUGIN
     BOUNDARY" section. It says "Cursor" but the prompts are tool-agnostic —
     execute them directly.

HARD PROJECT RULE:
This folder is the FREE plugin. It must contain ZERO Pro UI — no locked screens,
no Pro badges, no disabled Pro-only buttons, no <ProLock>. The Pro addon is a
SEPARATE plugin (bulk-variations-pro/) to be built later.

CURRENT STATUS:
Free v0.1.7 is feature-complete and validated — single-product Bulk Editor,
CSV import (update + create), Jobs + diff + rollback, Settings, release packaging.
PHPUnit 136 tests pass, PHPStan clean, npm lint + build OK, release zip builds.

WORKING RULES (important — these caught real regressions during the build):
- Before claiming any task complete, run ALL of: composer test:unit,
  composer phpstan, npm run lint:js, npm run build. Every one must pass.
- Trust but verify: after editing, re-read the file on disk. Do not trust a
  summary that says work is done — confirm the actual code.
- One change at a time. Show the diff. Let me test in Laragon before moving on.
- Never defer real implementation behind a "later" comment and call it done.
- Do not add Pro features to this folder.

WHAT I WANT NEXT — pick the one I tell you:
  Option A (recommended): finish the Free release. Final QA pass on the zip
    installed to a clean WordPress + WooCommerce site, fix anything found,
    produce WordPress.org listing assets (screenshots, icon, banner), and
    prep the readme.txt for submission.
  Option B: start the Pro addon — create the separate bulk-variations-pro/
    plugin per PROMPT 21 in cursor-prompt-pack-v2.md (addon bootstrap +
    extension API), then build Pro features into it.

I will tell you A or B. Start by reading the two docs above and confirming
you understand the Free/Pro boundary.
```

## New Chat Starting Prompt (Current)

Open the new chat with the Free repo as the working directory:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations`

Paste this prompt:

```text
We are continuing a WooCommerce plugin project.

Free plugin repo:
C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations

Pro addon source:
C:\Users\User\Desktop\My Plugins\Bulk Variation\coderembassy-bulk-variations-manager-pro

Localhost plugin folders:
Free: C:\laragon\www\plugins\wp-content\plugins\coderembassy-bulk-variations-manager
Pro:  C:\laragon\www\plugins\wp-content\plugins\coderembassy-bulk-variations-manager-pro

First, read PROJECT_PROGRESS_TRACKER.md in full. Treat it as the current source
of truth. Then read claude_plan_cursor-prompt-pack-v2.md and use its corrected
PROMPT 21 / Section 7 architecture rules, not the old standalone/Freemius-era
plan.

Architecture rules:
- Free must remain WordPress.org-safe.
- Do not add Pro UI, Pro badges, Pro strings, Pro feature gates, or addon
  detection to Free.
- Free is the base plugin and only exposes generic extension points / neutral
  field support.
- Pro is a separate addon plugin that requires Free and reuses Free engine
  services.
- Pro features are injected only when the Pro addon is active.

Current status:
- Free v0.1.8 is still the release candidate.
- Pro v0.1.0 addon is in active build.
- Pro Cross-product Editor is working in Laragon.
- Current Pro zip:
  C:\Users\User\Desktop\My Plugins\Bulk Variation\coderembassy-bulk-variations-manager-pro\artifacts\coderembassy-bulk-variations-manager-pro-0.1.0.zip

Implemented Pro features so far:
- Cross-product product selection/search and variation loading.
- Dynamic attribute filters and grouped product tables.
- Row selection, select all in current view, select all in product.
- Pending change summary with "already matched" rows for no-op selected rows.
- Batch image change/remove through Preview & Approve.
- SKU generator for selected rows.
- Price tools: set exact, +/- amount, +/- percent, round .99, clear sale price,
  set sale dates.
- Stock tools: set/adjust quantity, stock status, enable/disable stock management.
- Physical tools: weight/length/width/height set/increase/decrease.
- Tax/shipping tools: dropdown-only tax class and shipping class from WooCommerce options.
- Direct cell fan-out for safe set-same fields when multiple rows are selected.

Recent validation:
- Free npm run build passed after neutral Free changes.
- PHP lint passed for touched Free PHP files and Pro CrossProductController.php.
- Pro compiled admin JS passed node --check.
- Pro `npm run lint:js` and `npm run build` now pass after installing npm dependencies.
- Files were synced to the Laragon plugin folders.

Important known note:
The Pro source addon now has `node_modules` locally for development and a
`package-lock.json` for reproducible installs. Do not include `node_modules` in
the install zip.

Next likely task:
Continue Pro one feature batch at a time. The next sensible batch is broader
Pro QA / zip install QA, then AI assistant or license integration after the core
tools stay stable. After each batch, sync to localhost, rebuild the Pro zip, and
let me test in Laragon.
```
