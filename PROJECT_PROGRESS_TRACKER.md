# CoderEmbassy Bulk Variations Manager for WooCommerce — Progress Tracker

Last updated: 2026-05-24

## WordPress.org identity (2026-05-22)

- **Display name:** CoderEmbassy Bulk Variations Manager for WooCommerce
- **Slug / text domain:** `coderembassy-bulk-variations-manager`
- **Main plugin file:** `coderembassy-bulk-variations-manager.php`
- **Admin menu slug:** `coderembassy-bulk-variations-manager` (re-activate plugin after folder/file rename on existing sites)

## Current Build Target

We are building the Free plugin in this folder:

`C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations`

No Pro plugin folder has been created yet. The Pro plugin will be a separate addon later.

## Product Strategy

Free plugin must show zero Pro UI:

- No locked Pro screens.
- No Pro badges.
- No blurred feature walls.
- No disabled Pro-only buttons inside the Free plugin.

Pro features should be advertised on the plugin website/sales page and injected only by the future Pro addon.

## Free Features Confirmed In Scope

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

## New Chat Starting Prompt

Use the prompt below to start a new chat.

```text
You are helping me build a WooCommerce WordPress plugin called "CoderEmbassy Bulk Variations Manager for WooCommerce".

Current repo/folder:
C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations

Important project rule:
We are building the Free plugin only in this folder. Do not add Pro UI, Pro locks, Pro badges, or disabled Pro-only features. The Pro addon will be a separate plugin later.

Please read PROJECT_PROGRESS_TRACKER.md first and use it as the source of truth for what is already done, what is Free vs Pro, and what should be done next.

Current status:
- Free core scope is feature-complete.
- Bulk Editor supports single-product editing, preview/apply, jobs, and rollback.
- CSV import supports update existing variations, create new variations, attribute readiness, template download, preview/apply, and rollback.
- Settings screen is implemented for Free-safe preferences.
- Release packaging is set up and creates the WordPress.org-style zip.
- Latest checks: PHPUnit OK, PHPStan OK, npm lint/build OK.

What I want next:
Continue from the tracker. Start by reviewing the current code and the tracker, then focus on final release QA, clean zip install testing, and WordPress.org packaging polish.

When you find an issue, give me:
1. What is wrong.
2. Why it matters.
3. A precise Cursor prompt to fix it.
4. Acceptance checks and functional test steps.
```
