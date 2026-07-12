# Cursor AI Prompt Pack — Bulk Variations Add & Edit Product (v2)

> **Supersedes:** `cursor-prompt-pack.md` and `cursor-prompt-pack-additions.md` (you can delete those once you're happy with this file).
> **Companion to:** `bulk-variations-plan.md`, `bulk-variations-plan-improvements.md`, `bulk-variations-uniqueness-and-polish.md`.
> **Total prompts:** 37 (PROMPT 00 + 01–36).
>
> **How to use this file:**
>
> 1. Open Cursor and paste `MASTER CONTEXT` (below) once as project rules / pinned chat.
> 2. Run `PROMPT 00` before everything to generate `docs/PORTING.md` from the old `bulk-variations-pro/` codebase.
> 3. Then run prompts **01 → 36** in order.
> 4. After each prompt: run its **Verify** block. Do not move to the next prompt until it passes.
> 5. Every prompt is standalone — if Cursor loses context, re-paste `MASTER CONTEXT` + the specific prompt.
> 6. Do not let Cursor combine prompts. One prompt = one reviewable PR.

---

## MASTER CONTEXT — paste once, keep pinned

```
You are a senior WordPress/WooCommerce plugin engineer pair-programming on a commercial plugin called "Bulk Variations Add & Edit Product" (text domain: bulk-variations).

GOAL
Beat Barn2 WooCommerce Bulk Variations and every other competitor by being the ONLY plugin that combines: a polished frontend variation grid, a cross-product spreadsheet editor with diff-and-approve safety, an auto-pilot rules engine, a sales-velocity heatmap, profit-margin awareness, AI assistance with preview-then-apply, scheduled/staged bulk operations ("Black Friday mode"), and full rollback. The UX target is Linear / Notion / Stripe, not the WordPress admin default.

STACK
- PHP 8.1+, WordPress 6.x, WooCommerce 7.x+
- Namespace: BulkVariations\
- Autoload: Composer PSR-4, src/ → BulkVariations\
- DI: lucatume/di52
- Runtime deps (prefixed via Strauss): lucatume/di52, woocommerce/action-scheduler
- Licensing: Freemius (freemium single codebase, __premium_only markers)
- Admin UI: React + @wordpress/element + @wordpress/data + AG Grid Community (MIT) + cmdk-like command palette
- Storefront: PHP template + vanilla JS + Gutenberg block (server-rendered) + Cart Blocks compatibility
- Jobs: Action Scheduler (chunked)
- Bulk writes: direct $wpdb->query() with batched CASE SQL — NEVER wc_get_product()->save() in a loop
- Tests: PHPUnit + Brain Monkey (unit), WP_UnitTestCase via wp-env (integration), Playwright (E2E), axe-core (a11y)
- Static analysis: PHPStan level 6, PHPCS (WPCS + WooSniffs), ESLint (wp-scripts preset), Stylelint

NON-NEGOTIABLE RULES
1. Every REST callback: current_user_can('manage_woocommerce') + wp_verify_nonce('wp_rest') via the check_ajax_referer pattern. The old plugin's `permission_callback => '__return_true'` is explicitly forbidden anywhere.
2. Every $wpdb->query() uses $wpdb->prepare(); user input is always escaped via intval / floatval / sanitize_text_field / wc_clean.
3. Bulk operations MUST run through JobManager + Action Scheduler. No synchronous bulk writes above 50 rows.
4. Every bulk write records a delta in {prefix}bv_job_changes BEFORE writing, for rollback.
5. AI NEVER writes to DB automatically. Every AI action produces a suggestion job; admin clicks "Apply as Job" to trigger the real write.
6. Strip PII before any AI call: only product_title, attributes, prices, SKUs, rules. Never customer/order data.
7. Declare HPOS compatibility (FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)) AND Cart/Checkout Blocks compatibility (FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true)).
8. All user-facing strings use __(), _n(), _x() with text domain 'bulk-variations'. JS strings use @wordpress/i18n.
9. All Pro-only features are gated by FeatureFlags::isEnabled(FEATURE) AND visually locked in Free via the <ProLock> React component (blurred, lock icon, upgrade tooltip).
10. The admin UI is a SINGLE React app mounted at #bv-admin-root. Sub-pages are routes inside the app, not separate mounts.
11. File naming: PHP classes use PascalCase, namespaced; JS components use PascalCase.jsx; CSS uses kebab-case with a `bv-` prefix on every public class; data attributes use `data-bv-*`.
12. Keep the codebase ready for WP.org submission: no obfuscation, no external scripts loaded from third-party CDNs on the frontend, no tracking by default.
13. Every job > 5 rows opens the Visual Diff & Approval drawer before executing (PROMPT 25). Skipping the drawer is opt-in per-user via Settings.
14. Every bulk action posts a Snackbar with Undo (PROMPT 32). Aha receipts ("you'd have spent ~80 min manually") show for jobs ≥ 5 rows.
15. Every interactive element registers a Command in the Command Palette registry (PROMPT 31). The shortcut sheet is auto-generated from the registry.

FILE LAYOUT (authoritative — do not improvise)
bulk-variations-add-edit-product/
├─ bulk-variations.php                 plugin bootstrap
├─ uninstall.php                       cleanup entry
├─ composer.json
├─ composer-strauss.json
├─ package.json
├─ webpack.config.js                   extends @wordpress/scripts if needed
├─ phpcs.xml.dist
├─ phpstan.neon.dist
├─ playwright.config.ts
├─ .github/workflows/ci.yml
├─ readme.txt                          WP.org readme
├─ docs/                               developer & porting docs
│  └─ PORTING.md
├─ src/                                BulkVariations\ namespace
│  ├─ Plugin.php
│  ├─ ServiceProvider.php
│  ├─ Contracts/
│  ├─ Admin/{AdminPage,Notices,RestBootstrapper,Pages/}
│  ├─ Engine/{BulkEditor,VariationGenerator,AttributeMatrix,VariationRepository}
│  ├─ Services/{SKUGenerator,PriceCalculator,JobService,HistoryLogger,RetentionService,ConcurrencyLock,Diagnostics,CostEstimator,MarginCalculator,RevenueImpactEstimator,ActivityRecorder,PresenceTracker}
│  ├─ Repository/{JobRepository,RollbackRepository,TemplateRepository,RulesRepository,ScheduleRepository}
│  ├─ ImportExport/{CsvImporter,CsvExporter,ImportValidator}
│  ├─ Jobs/{JobManager,BulkUpdateJob,ImportJob,RollbackJob,RetentionJob,RulesDailyJob,StagedExecutionJob,StagedRevertJob}
│  ├─ Rollback/RollbackService
│  ├─ Diff/{DiffService,DiffDecisionApplier}
│  ├─ Rules/{Condition,Action,RulesEngine,RuleFieldRegistry,RuleActionRegistry}
│  ├─ Schedule/{SchedulerService,RevertScheduler}
│  ├─ Analytics/{EventRecorder,HeatmapService,MigrationFromBvpro}
│  ├─ Activity/{ActivityFeedService,PresenceService}
│  ├─ AI/{AiAssistantService,PromptLibrary,Adapters/{OpenAiAdapter,AnthropicAdapter,NullAdapter}}
│  ├─ REST/{RestController,Schema,ErrorResponse}
│  ├─ CLI/BulkVariationsCLI
│  ├─ Frontend/{VariationGrid,Block}
│  ├─ Updater/MigrationRunner
│  ├─ Licensing/{LicenseManager,FeatureFlags}
│  ├─ Onboarding/{OnboardingService,PersonaDetector,AchievementLog}
│  ├─ Privacy/{PrivacyExporter,PrivacyEraser,PolicyContent}
│  └─ Support/Logger
├─ assets/admin/
│  ├─ admin.css
│  ├─ admin-rtl.css                    generated
│  ├─ logo-light.png  logo-dark.png
│  ├─ dist/                            build output (gitignored)
│  └─ src/{
│       index.js,
│       store/,
│       api/,
│       commands/ (registry, builtins, triggers),
│       hooks/,
│       components/{App,Topbar,Sidebar,Router,CommandPalette,Snackbar,shared/, views/{Dashboard,Heatmap,BulkEditor,Jobs,Rules,Schedule,Templates,AiAssistant,Settings,Help,Onboarding}}
│     }
├─ assets/frontend/
│  ├─ css/frontend.css
│  ├─ js/frontend-grid.js
│  └─ block/{block.json,edit.jsx,view.js}
├─ migrations/
├─ templates/{admin/, frontend/}
├─ tests/
│  ├─ Unit/…
│  ├─ Integration/…
│  └─ E2E/…
└─ languages/

REFERENCE-ONLY (do not import, do not refactor — port specific algorithms per docs/PORTING.md):
C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations-pro\

When you finish a prompt, end your reply with:
  ✅ Completed: PROMPT <N>
  📁 Files created/changed: <list>
  ▶ Next: run PROMPT <N+1>
```

---

## Phase index

- **Phase 0 — Porting:** PROMPT 00
- **Phase A — Foundations:** PROMPT 01–06
- **Phase B — Surfaces:** PROMPT 07–09
- **Phase C — Admin UI:** PROMPT 10–18
- **Phase D — AI + Storefront + Licensing:** PROMPT 19–21
- **Phase E — Compliance & QA:** PROMPT 22–24
- **Phase F — Uniqueness Features:** PROMPT 25–30 ⭐ (the moat)
- **Phase G — UX Premium:** PROMPT 31–34 ⭐ (the feel)
- **Phase H — Release:** PROMPT 35–36

---

## PROMPT 00 — Reference Codebase from `bulk-variations-pro/`

```
Goal: register the old codebase as a READ-ONLY reference so you can port specific algorithms while building the new plugin under bulk-variations-add-edit-product/. The old code is PHP 7.4, jQuery, no PSR-4, no DI, no namespaces, has N+1 wc_get_product()->save() loops, and is architecturally incompatible. Do NOT copy classes wholesale.

Depends on: nothing.

Reference path
  C:\Users\User\Desktop\My Plugins\Bulk Variation\bulk-variations-pro\

What to port (and how to rewrite it)

1. Cartesian product generator
   - Source: includes/class-bvpro-generator.php (algorithm only — discard the WC_Product_Variation::new() + ->save() loop)
   - Target: src/Engine/VariationGenerator.php — generateCombinations(), previewCombinations() (see PROMPT 04)
   - Rewrite persistence to direct $wpdb batched INSERTs + Action Scheduler chunks.

2. CSV import streaming
   - Source: includes/class-bvpro-csv.php (fopen/fgetcsv streaming + header mapping)
   - Target: src/ImportExport/CsvImporter.php + ImportValidator.php (PROMPT 09)
   - Discard synchronous loop write — dispatch chunks to ImportJob via JobManager.

3. CSV export streaming
   - Source: includes/class-bvpro-csv.php (export portion)
   - Target: src/ImportExport/CsvExporter.php — keep php://output streaming + fputcsv.

4. Frontend variation grid HTML
   - Source: includes/class-bvpro-grid.php (HTML rendering only) + public/css/bvpro-public.css (responsive table CSS)
   - Target: templates/frontend/variation-grid.php (PROMPT 20)
   - Rebuild as a theme-agnostic PHP template with CSS custom properties. Replace jQuery with vanilla JS that supports Cart Blocks.

5. REST endpoint shape (NOT the handlers)
   - Source: includes/class-bvpro-rest-api.php — URL patterns /products/{id}/variations and /products/{id}/cart are clean.
   - Target: src/REST/RestController.php under the bv/v1 namespace.
   - DO NOT copy permission_callback => __return_true. Every route requires manage_woocommerce + nonce.

6. Analytics events table
   - Source: includes/class-bvpro-analytics.php — bvpro_analytics schema is reasonable.
   - Target: feeds the new Performance Heatmap (PROMPT 27). Migrate as bv_events.
   - On install: detect old bvpro_analytics table; if present, expose a one-click migration in Settings → Diagnostics.

7. Activation pattern
   - Source: includes/class-bvpro-install.php — dbDelta() usage is correct.
   - Target: src/Updater/MigrationRunner.php (PROMPT 02) — same pattern, new tables.

8. Helpers
   - Source: includes/class-bvpro-helpers.php — get_variation_data() and get_product_attributes() — algorithm is fine.
   - Target: src/Engine/VariationRepository.php — re-implement with single SQL IN queries (no per-variation get_post_meta).

What NOT to port
  - The jQuery UI bulk editor (admin/js/bvpro-admin.js).
  - Any wc_get_product()->save() loop.
  - The static singleton bootstrap.
  - The class-bvpro-license.php stub (replaced by Freemius).
  - The bvpro_discounts table (qty discounts are out of scope for v1).

Migration UX for existing users
  - If activation detects the old bulk-variations-pro plugin alongside ours, show one-time admin notice:
    "Welcome from Bulk Variations Pro. We've rebuilt the plugin from the ground up. Want to migrate your settings? [Migrate now] [Skip]"
  - Migration covers: bvpro_analytics → bv_events, bvpro_templates → bv_templates, any saved options under bvpro_* → bv_settings.
  - Deactivate the old plugin programmatically after successful migration; do NOT delete its data.

Output for this prompt
  - No code yet. Produce a single file: docs/PORTING.md cataloguing every old file with columns:
      | Old file | Purpose | Port? (Y/N) | Target (new path) | Notes |
  - Use a markdown table. One row per file under bulk-variations-pro/ (24 files).

Acceptance
  - docs/PORTING.md exists with ≥ 24 file rows.
  - Every "Port: Y" row has a target path matching MASTER CONTEXT file layout.
  - No prompt 01..36 references the old folder; PORTING.md is the only bridge.
```

**Verify**

```
test -f docs/PORTING.md && wc -l docs/PORTING.md   # ≥ 30 lines including header
grep -c '^|' docs/PORTING.md                       # ≥ 25 (header + 24 file rows)
```

---

# Phase A — Foundations

## PROMPT 01 — Repo & Tooling Baseline

```
Goal: scaffold the project with all tooling, configs and empty folders so every later prompt has a place to drop files.

Depends on: PROMPT 00.

Output files:

1. bulk-variations.php — plugin header only (Name, Description, Version 0.1.0, Author, License GPLv2+, Requires at least 6.3, Requires PHP 8.1, WC requires at least 7.0, Text Domain: bulk-variations, Domain Path: /languages). Safety ABSPATH guard. Real bootstrap comes in PROMPT 02.

2. composer.json
   - name: "your-vendor/bulk-variations"
   - autoload PSR-4: "BulkVariations\\": "src/"
   - autoload-dev PSR-4: "BulkVariations\\Tests\\": "tests/"
   - require: php ">=8.1", lucatume/di52 "^3.3", woocommerce/action-scheduler "^3.7"
   - require-dev: phpunit/phpunit ^9, brain/monkey ^2, 10up/wp_mock ^0.5, szepeviktor/phpstan-wordpress ^1, wp-coding-standards/wpcs ^3, automattic/vipwpcs ^3, dealerdirect/phpcodesniffer-composer-installer
   - scripts: test, test:unit, test:integration, lint:php, lint:fix, phpstan, build-free (Strauss strip placeholder)
   - config: sort-packages true, allow-plugins for phpcs installer.

3. composer-strauss.json — minimal Strauss config prefixing lucatume\DI52 into BulkVariations\Vendor. Output target: src/Vendor/.

4. package.json
   - private: true
   - scripts: build, start, lint:js, lint:css, format, test, test:e2e
   - devDependencies: @wordpress/scripts, ag-grid-community, ag-grid-react, fuse.js (for command palette), @playwright/test, eslint-config-wordpress, stylelint, @wordpress/env, html-to-image
   - dependencies: none (React/Element from WP globals).

5. webpack.config.js — extends @wordpress/scripts default; entries: assets/admin/src/index.js → assets/admin/dist/index.js. Externalise react/react-dom to wp.element.

6. phpcs.xml.dist — WordPress + WooCommerce rules, include src/, tests/, exclude vendor/, node_modules/, assets/admin/dist/. Text-domain rule forces 'bulk-variations'.

7. phpstan.neon.dist — level 6, include szepeviktor/phpstan-wordpress, scanDirectories src/, excludePaths src/Vendor/*.

8. .github/workflows/ci.yml — matrix PHP 8.1/8.2/8.3 × WP latest/latest-1 × WC 8.x/9.x. Jobs: composer install, phpcs, phpstan, phpunit (unit), wp-env start + phpunit (integration), npm ci + npm run build + lint + playwright.

9. playwright.config.ts — baseURL http://localhost:8889, testDir tests/E2E, retries 1 on CI, trace "on-first-retry".

10. .gitignore — vendor/, node_modules/, assets/admin/dist/, .phpunit.result.cache, .wp-env/, coverage/, *.log, .idea/, .vscode/.

11. readme.md (project README) — dev quickstart: composer install, npm install, npm run start, npx wp-env start.

12. Empty placeholder dirs with .gitkeep each:
    src/Contracts/  src/Admin/  src/Engine/  src/Services/  src/Repository/
    src/ImportExport/  src/Jobs/  src/Rollback/  src/Diff/  src/Rules/
    src/Schedule/  src/Analytics/  src/Activity/  src/AI/Adapters/  src/REST/
    src/CLI/  src/Frontend/  src/Updater/  src/Licensing/  src/Onboarding/
    src/Privacy/  src/Support/  assets/admin/src/  assets/frontend/  migrations/
    templates/admin/  templates/frontend/  languages/  docs/
    tests/Unit/  tests/Integration/  tests/E2E/

Acceptance
- composer validate --strict passes.
- composer install + npm install succeed.
- composer phpstan + composer lint:php zero errors on empty codebase.

Do NOT implement any real logic yet.
```

**Verify**

```
composer validate --strict && composer install && npm install \
  && composer phpstan && composer lint:php && npm run lint:js
```

---

## PROMPT 02 — Plugin Bootstrap, DI, Migrations, HPOS, i18n, Uninstall

```
Goal: turn the skeleton into a bootable plugin that registers services, runs migrations on activation, declares HPOS + Cart Blocks compatibility, loads translations, and cleans up on uninstall.

Depends on: PROMPT 01.

Output files:

1. bulk-variations.php (replace)
   - Keep header from PROMPT 01.
   - define() BV_PLUGIN_FILE, BV_PLUGIN_PATH, BV_PLUGIN_URL, BV_VERSION.
   - Require vendor/autoload.php (graceful notice if missing).
   - register_activation_hook → \BulkVariations\Updater\MigrationRunner::run().
   - register_deactivation_hook → as_unschedule_all_actions('bv_*') and flush plugin transients.
   - add_action('before_woocommerce_init', function() {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
     });
   - add_action('plugins_loaded', fn() => \BulkVariations\Plugin::instance()->boot(), 20).
   - load_plugin_textdomain('bulk-variations', false, basename(__DIR__) . '/languages') on plugins_loaded priority 1.

2. uninstall.php
   - Guard: if (! defined('WP_UNINSTALL_PLUGIN')) exit;
   - Read option 'bv_uninstall_remove_data' (default false).
   - If true: drop all bv_* tables, delete all options prefixed bv_*, delete all user meta bv_*, unschedule 'bv_*' Action Scheduler actions.

3. src/Plugin.php
   - Final class, singleton via instance().
   - boot(): if (! class_exists('WooCommerce')) { Admin\Notices::missing_wc(); return; }
   - Build DI container (lucatume\DI52\Container), call new ServiceProvider($container)->register(), then ->boot().

4. src/ServiceProvider.php
   - register(): bind all interfaces → concrete classes. Singletons for: JobRepository, RollbackRepository, TemplateRepository, RulesRepository, ScheduleRepository, AiAssistantService, JobManager, FeatureFlags, ActivityFeedService, PresenceService.
   - boot(): call each service's ->register_hooks().
   - Register WP-CLI command if defined('WP_CLI') && WP_CLI.

5. src/Updater/MigrationRunner.php
   - run(): compare get_option('bv_db_version') vs BV_VERSION, execute new migration files in migrations/ in order. Use dbDelta(). Idempotent.

6. migrations/0001_create_bv_tables.sql — SQL from plan §5 for 4 base tables (bv_jobs, bv_job_changes, bv_templates, bv_ai_log) with correct indexes and utf8mb4. Additional tables created in later migrations: 0002_diff_approval, 0003_rules, 0004_events, 0005_schedule, 0006_activity, 0007_achievements.

7. src/Admin/Notices.php
   - static missing_wc() → admin notice red.
   - static generic(string $message, string $level='info').

8. languages/bulk-variations.pot — placeholder header only; real .pot generated by CI via `wp i18n make-pot`.

Acceptance
- Activate plugin in wp-env → 4 base tables exist.
- Deactivate → scheduled 'bv_*' actions cleared.
- Delete plugin with 'bv_uninstall_remove_data' = true → tables + options gone.
- composer phpstan still zero errors.

Unit test tests/Unit/Updater/MigrationRunnerTest.php — Brain Monkey mocks verifying version bump flow.
```

**Verify**

```
npx wp-env start
wp --path=wp plugin activate bulk-variations
wp --path=wp db query "SHOW TABLES LIKE '%_bv_%';"   # expect 4
composer test:unit
```

---

## PROMPT 03 — Contracts, JobRepository, HistoryLogger

```
Goal: lay down Contract interfaces and the repositories / loggers the Engine depends on.

Depends on: PROMPT 02.

Output files:

1. src/Contracts/VariationServiceInterface.php
   - bulkUpdate(array $updates, int $job_id, ?callable $progress = null): array
   - generateCombinations(int $product_id, array $attributes): array

2. src/Contracts/JobRepositoryInterface.php
   - create(array $meta): int
   - get(int $id): ?array
   - updateStatus(int $id, string $status, array $extra = []): bool
   - addChange(int $job_id, array $delta): bool
   - getChanges(int $job_id): array
   - listJobs(array $filters = [], int $limit = 20, int $offset = 0): array
   - count(array $filters = []): int
   - setControl(int $id, string $control): bool   // queued|running|paused|cancelled

3. src/Contracts/AiAdapterInterface.php — request(string $prompt_type, array $context): array; isConfigured(): bool.

4. src/Contracts/ImporterInterface.php — import(string $file_path, int $product_id, array $options): int (returns job_id).

5. src/Repository/JobRepository.php — implements JobRepositoryInterface. Every query uses $wpdb->prepare. listJobs supports filters: status, type, created_by, date range, review_status, source (manual|rule|schedule|ai).

6. src/Services/HistoryLogger.php
   - recordChange(int $job_id, string $object_type, int $object_id, string $field, $old_value, $new_value): void
   - flush(): void — batched multi-row INSERT.

7. Unit tests for both classes.

Acceptance
- All public methods type-hinted and documented.
- PHPStan level 6 passes.
- Unit tests green.
```

**Verify**

```
composer test:unit -- --filter "JobRepository|HistoryLogger"
composer phpstan
```

---

## PROMPT 04 — Variation Engine & Bulk Editor (with SQL + sync fix)

```
Goal: implement the Cartesian generator and the bulk editor with batched CASE SQL. Fix the placeholder from the plan (real parent-ID collection + per-parent sync).

Depends on: PROMPT 03.

Output files:

1. src/Engine/VariationGenerator.php
   - generateCombinations(int $product_id, array $attributes): array — Cartesian, dedupe existing.
   - previewCombinations(int $product_id, array $attributes, int $limit = 100): array
   - createVariations(int $product_id, array $combinations, array $defaults = []): array

2. src/Engine/AttributeMatrix.php — loadProductAttributes(int), validateAttributeSlugs(array).

3. src/Engine/VariationRepository.php
   - getParentIds(array $variation_ids): array — single SQL IN on wp_posts.post_parent.
   - getMetaSnapshot(array $variation_ids, array $meta_keys): array — single query for delta capture.
   - getCosts(array $variation_ids, string $cost_meta_key): array — used by MarginCalculator (PROMPT 28).

4. src/Services/SKUGenerator.php
   - generate(string $pattern, array $attributes, array $ctx = []): string
   - Tokens: {product}, {index}, {attr_<slug>}.
   - validateUnique(array $skus): array — single SQL IN against postmeta._sku.

5. src/Services/PriceCalculator.php
   - apply(string $formula, float $current): float
   - Supports =PRICE*1.15, =PRICE+5, =ROUND(PRICE*0.9,2), plain number, "+15%".
   - Throws InvalidFormulaException on anything else; never evals user input.

6. src/Engine/BulkEditor.php (implements VariationServiceInterface)
   - Inject: JobRepositoryInterface, VariationRepository, HistoryLogger, int $chunk_size=300.
   - bulkUpdate():
     a. array_chunk into $chunk_size.
     b. Per chunk:
        - Snapshot meta via VariationRepository::getMetaSnapshot (one query).
        - HistoryLogger::recordChange for each field that will change.
        - Build batched CASE SQL per field (_price, _regular_price, _sale_price, _stock, _sku, _stock_status).
        - wc_transaction_query('start'); $wpdb->query(prepared); wc_transaction_query('commit').
        - HistoryLogger::flush.
        - Invoke $progress callback.
     c. After all chunks:
        - $parent_ids = VariationRepository::getParentIds(...).
        - foreach ($parent_ids as $pid) WC_Product_Variable::sync($pid); WC_Product_Variable::sync_stock_status($pid);
        - If function_exists('wc_update_product_lookup_tables_rows'), call for parent IDs; else fall back to wc_deferred_product_sync.
        - wp_cache_flush(); wc_delete_product_transients() per parent.

7. Unit tests:
   - tests/Unit/Engine/VariationGeneratorTest.php — 3×3×2 Cartesian count, dedupe, attribute validation.
   - tests/Unit/Services/SKUGeneratorTest.php
   - tests/Unit/Services/PriceCalculatorTest.php — every formula + reject eval-style.

Acceptance
- PHPStan zero errors.
- Tests green.
- grep -R "wc_get_product()->save()" src/ returns nothing.
```

**Verify**

```
composer test:unit -- --filter "Engine|SKU|Price"
grep -R "wc_get_product()->save()" src/ || echo "OK: no loop-saves"
```

---

## PROMPT 05 — Job System (Action Scheduler + pause/resume/cancel + concurrency lock + retention)

```
Goal: background job pipeline with admin controls and a daily retention cron.

Depends on: PROMPT 04.

Output files:

1. src/Services/ConcurrencyLock.php — acquire/release/isHeldBy via add_option atomicity. TTL default 900s.

2. src/Jobs/JobManager.php
   - dispatch(int $job_id, array $chunks, string $hook='bv_process_job_chunk'): void
     - Acquires "bv_lock_product_{$product_id}" if bulk_edit.
     - Stores chunks in option bv_job_{$job_id}_chunks (autoload=no).
     - If job.review_status === 'pending_review', returns WITHOUT enqueuing (PROMPT 25).
     - Else enqueues first chunk via as_enqueue_async_action.
   - processChunk(int $job_id, int $index): void
     - Early-exit if repo.get($id).control === 'cancelled'.
     - If control === 'paused', re-queue in 60s and return.
     - wp_suspend_cache_invalidation(true/false) bracket.
     - Delegate to BulkUpdateJob | ImportJob | RollbackJob | StagedExecutionJob | StagedRevertJob by job.type.
     - Update progress %, broadcast via ActivityRecorder (PROMPT 30).
     - Schedule next chunk with time()+5.
     - On final chunk: release lock, cleanup options, status=complete.
   - pause/resume/cancel(int $job_id): bool.

3. src/Jobs/BulkUpdateJob.php — stateless worker; one chunk via BulkEditor. No parent sync here (runs once in final chunk).

4. src/Jobs/ImportJob.php — per-row validate + upsert WC_Product_Variation. Writes errors to job.error_log JSON.

5. src/Jobs/RollbackJob.php — applies one chunk of inverse deltas via the same CASE SQL path.

6. src/Jobs/RetentionJob.php
   - Hooked to bv_retention_daily (as_schedule_recurring_action, DAY_IN_SECONDS).
   - Purges bv_jobs older than bv_retention_jobs_days (default 30).
   - Purges bv_job_changes older than bv_retention_changes_days (default 30).
   - Purges bv_ai_log older than bv_retention_ai_days (default 14).
   - Purges bv_events older than bv_retention_events_days (default 365) — see PROMPT 27.
   - Purges bv_activity older than bv_retention_activity_days (default 90) — see PROMPT 30.

7. Register hooks in ServiceProvider::boot:
   - 'bv_process_job_chunk' → JobManager::processChunk.
   - 'bv_retention_daily' → RetentionJob::run.
   - On activation: schedule bv_retention_daily if not already.

8. Integration test tests/Integration/JobManagerTest.php — dispatch 5 chunks, simulate AS, assert transitions + lock release.

Acceptance
- Dispatch + process + complete end-to-end on wp-env.
- Pause re-queues; cancel halts; lock releases.
- Retention purges old rows.
```

**Verify**

```
npx wp-env run tests-wordpress wp action-scheduler run --hooks='bv_process_job_chunk'
composer test:integration -- --filter "JobManager"
```

---

## PROMPT 06 — Rollback Service

```
Goal: preview + execute rollback for any completed job, backed by delta storage.

Depends on: PROMPT 05.

Output files:

1. src/Rollback/RollbackService.php
   - preview(int $job_id): array — dry run, no DB writes.
   - rollback(int $job_id): int — creates a new rollback job, enqueues RollbackJob.
   - Inverse: swap old_value ↔ new_value.
   - Validates: job exists, status === 'complete', not already 'rolled_back'.
   - Small jobs (< 50 deltas) run synchronously; larger dispatch via JobManager.
   - Marks source job status = 'rolled_back' on success.

2. src/Repository/RollbackRepository.php — getInverseDeltas(int $job_id), markRolledBack(int $job_id).

3. Integration test tests/Integration/RollbackTest.php — bulk change → verify → rollback → verify reverted (idempotent).

Acceptance
- Idempotent: running rollback twice on same job is rejected with WP_Error.
- Rollback itself records deltas (undo-the-undo is possible but explicit).
```

---

# Phase B — Surfaces

## PROMPT 07 — REST API with schemas, pagination, error shape, job controls

```
Goal: full REST surface with rigorous schemas, pagination, and standard error envelope. Every route enforces manage_woocommerce + nonce.

Depends on: PROMPT 06.

Output files:

1. src/REST/ErrorResponse.php
   - static make(string $code, string $message, int $status = 400, array $data = []): WP_Error
   - Shape: { code, message, data: { status, detail } }

2. src/REST/Schema.php — JSON-schema arrays per resource: Job, JobChange, Variation, Template, AiSuggestion, Rule, Schedule, HeatmapCell, ActivityEvent.

3. src/REST/RestController.php
   - Namespace 'bv/v1'. register_routes() on 'rest_api_init'.
   - CORE routes (extended in later prompts):
     - POST   /jobs                     (create bulk_edit | import | generate | rule | schedule)
     - GET    /jobs                     (page, per_page, X-WP-Total)
     - GET    /jobs/(?P<id>\d+)
     - POST   /jobs/(?P<id>\d+)/apply
     - POST   /jobs/(?P<id>\d+)/pause
     - POST   /jobs/(?P<id>\d+)/resume
     - POST   /jobs/(?P<id>\d+)/cancel
     - POST   /jobs/(?P<id>\d+)/rollback
     - GET    /jobs/(?P<id>\d+)/rollback/preview
     - GET    /variations               (product_id required, ?include=margin optional)
     - POST   /variations/generate
     - POST   /ai/suggest               (returns job_id)
     - GET    /ai/suggest/(?P<id>\d+)
     - GET    /templates                (Pro)
     - POST   /templates                (Pro)
     - DELETE /templates/(?P<id>\d+)    (Pro)
     - GET    /diagnostics              (system info JSON)
     - GET    /settings  POST /settings
   - Every route: permission_callback returns true only if current_user_can('manage_woocommerce'). 'args' uses Schema. On failure returns ErrorResponse.
   - LATER prompts extend with: /jobs/{id}/diff, /jobs/{id}/diff/decisions, /jobs/{id}/approve, /jobs/{id}/reject (PROMPT 25), /rules/* (PROMPT 26), /heatmap, /heatmap/cell (PROMPT 27), /schedule/* (PROMPT 29), /activity, /presence (PROMPT 30), /onboarding/* (PROMPT 33).

4. Integration test tests/Integration/RestApiTest.php — happy path + auth-failure per core route.

Acceptance
- wp rest namespace list includes bv/v1.
- Unauthenticated → 401 ErrorResponse. Authenticated non-manager → 403.
- Pagination headers correct on GET /jobs.
```

---

## PROMPT 08 — WP-CLI Commands

```
Goal: headless parity with the REST API.

Depends on: PROMPT 07.

Output files:

1. src/CLI/BulkVariationsCLI.php
   - wp bv jobs list [--status=…] [--type=…] [--limit=20]
   - wp bv job run <id>
   - wp bv job pause|resume|cancel <id>
   - wp bv job rollback <id> [--dry-run]
   - wp bv job approve <id>     # PROMPT 25
   - wp bv job reject <id>      # PROMPT 25
   - wp bv import <file> [--product-id=<id>] [--dry-run] [--chunk-size=300]
   - wp bv generate <product-id> --attributes='{"pa_color":["Red","Blue"]}' [--dry-run]
   - wp bv ai suggest --type=generate_variations --payload=<json-file>
   - wp bv rules list|run|enable|disable     # PROMPT 26
   - wp bv events migrate                     # imports old bvpro_analytics
   - wp bv schedule list|cancel <id>          # PROMPT 29
   - wp bv diagnostics export [--file=<path>]
   - wp bv smoke                              # PROMPT 36 - runs end-to-end smoke test
   - Each long command uses WP_CLI\Utils\make_progress_bar.

2. Register in ServiceProvider when WP_CLI is defined.

Acceptance
- wp bv jobs list works on wp-env.
- --dry-run prints planned changes without writing.
```

---

## PROMPT 09 — CSV Import / Export

```
Goal: streaming CSV import + export with per-row validation.

Depends on: PROMPT 05.

Output files:

1. src/ImportExport/ImportValidator.php
   - validateRow(array $row, array $existing_skus_lookup): array → [is_valid, fixed_row, issues[]].
   - Rules: price > 0 (unless blank), sku [A-Za-z0-9\-_]+, stock integer, no duplicate SKUs.
   - Batch duplicate SKU precheck via single SQL IN.

2. src/ImportExport/CsvImporter.php (implements ImporterInterface)
   - Streaming fopen/fgetcsv; never file_get_contents on > 1 MB.
   - MIME + extension check.
   - Auto-detect header. Map: sku, regular_price, sale_price, stock_quantity, stock_status, description, attribute_<slug>.
   - Creates bv_jobs row type=import, dispatches ImportJob via JobManager.

3. src/ImportExport/CsvExporter.php
   - export(int $product_id): void — streams to php://output.
   - exportToFile(int $product_id, string $path): string — for async export jobs.

4. templates/admin/import-preview.php — preview table.

5. tests/fixtures/sample-variations.csv — 10 valid + 3 invalid.

6. tests/Unit/ImportExport/CsvImporterTest.php + ImportValidatorTest.php.

Acceptance
- 5k-row CSV imports without PHP timeout.
- Invalid rows reported in job.error_log with row index + reason.
```

---

# Phase C — Admin UI

## PROMPT 10 — Admin Shell: AdminPage.php + Build + Design Tokens CSS

```
Goal: register the admin menu, enqueue assets, and ship the design-token CSS that both themes consume.

Depends on: PROMPT 02.

Output files:

1. src/Admin/AdminPage.php — exact code from bulk-variations-plan-improvements.md §8.2. Register top-level menu under manage_woocommerce; submenu entries deep-link via hash router (#/dashboard, #/heatmap, #/editor, #/jobs, #/rules, #/schedule, #/templates, #/ai, #/settings, #/help). Localise BulkVariationsAdmin globals: rest_url, nonce, version, is_pro, features (per-flag map from FeatureFlags::map()), capabilities, current_user, logos, currency, dateFormat, initial_theme.

2. src/Admin/RestBootstrapper.php — apiFetch nonce middleware bootstrap (JS-side from localised nonce, keeps PHP thin).

3. assets/admin/admin.css — token + shell CSS from improvements §8.3 (light + dark, WCAG AA, focus rings, reduced-motion, RTL). All classes prefixed bv-.

4. assets/admin/logo-light.png + logo-dark.png — placeholders 320×80; rendered at 28px.

5. Register AdminPage in ServiceProvider::boot on 'admin_menu' and 'admin_enqueue_scripts'.

Acceptance
- Plugin menu appears under a custom SVG/dashicon top-level item.
- Any BV admin page renders an empty <div id="bv-admin-root"> with CSS loaded.
- Lighthouse contrast > 4.5:1 on topbar/sidebar/cards in both themes.
```

---

## PROMPT 11 — Admin Shell: React App, Store, Router, API client, Hooks

```
Goal: bring up the single-page React shell: Topbar + Sidebar + hash Router + @wordpress/data store + apiFetch wrapper.

Depends on: PROMPT 10.

Output files:

1. assets/admin/src/index.js — hydrate window.BulkVariationsAdmin into the store, configure @wordpress/api-fetch nonce middleware, createRoot into #bv-admin-root, render <App />.

2. assets/admin/src/store/index.js — registerStore('bulk-variations/admin', …).
   State:
     { globals: {}, ui: { activeView, theme, toasts, snackbars, modals, shortcutsVisible, paletteOpen }, jobs: { list, total, polling }, editor: { productIds, columns, undo, redo, dirty }, rules: { list }, heatmap: { cells, filters }, schedule: { list }, activity: { feed, presence } }
   Actions: setGlobals, setActiveView, setTheme, pushToast, pushSnackbar, dismissSnackbar, openModal, closeModal, togglePalette, …

3. assets/admin/src/api/client.js — apiFetch wrapper: injects nonce; normalises error envelope; toasts 5xx; returns { data, total, totalPages } for lists.

4. assets/admin/src/api/endpoints.js — typed helpers: jobs.*, variations.*, ai.*, templates.*, rules.*, heatmap.*, schedule.*, activity.*, presence.*, settings.*, diagnostics.*, onboarding.*.

5. assets/admin/src/components/App.jsx — root; reads theme; respects prefers-color-scheme when theme==='auto'; wraps <Router /> in <ToastProvider />, <SnackbarProvider />, <ModalHost />, <CommandPalette />, <PresenceTracker />.

6. assets/admin/src/components/Topbar.jsx — logo swap, spacer, palette trigger ("⌘K · Search & commands"), dark-mode toggle, help icon, user chip, presence dots.

7. assets/admin/src/components/Sidebar.jsx — nav items (Dashboard, Heatmap, Bulk Editor, Jobs, Rules, Schedule, Templates, AI, Settings, Help). Pro-lock icon where !is_pro. Running-jobs badge with pulse animation when > 0.

8. assets/admin/src/components/Router.jsx — hash router: routes for every sidebar item + #/jobs/{id}, #/jobs/{id}/diff, #/rules/{id}, #/schedule/{id}, #/onboarding.

9. Hooks:
   - assets/admin/src/hooks/useTheme.js
   - assets/admin/src/hooks/useShortcut.js
   - assets/admin/src/hooks/useJobsPolling.js — polls /jobs every 3s while any running; backoff on error.
   - assets/admin/src/hooks/usePresence.js — heartbeat every 30s to /presence (PROMPT 30).

Acceptance
- Hash navigation swaps views without reload.
- Dark-mode toggle persists across reload.
- 4xx shows inline error; 5xx triggers toast.
```

---

## PROMPT 12 — Shared UI Components

```
Goal: ship the component library every view reuses.

Depends on: PROMPT 11.

Output files (under assets/admin/src/components/shared/):

- Button.jsx (variants: default, primary, danger, ghost; sizes: sm, md)
- Card.jsx ({ title, actions, children })
- Badge.jsx (status: ok|warn|err|info|muted with icon + label)
- ProgressBar.jsx ({ value, max, label, indeterminate })
- Toggle.jsx
- Toast.jsx + ToastProvider.jsx — useToast(); auto-dismiss 5s; respects reduced-motion.
- ConfirmModal.jsx — title, body, confirmLabel, destructive flag; returns Promise.
- EmptyState.jsx — illustrated SVG area, title, description, CTA.
- Skeleton.jsx — row|card|text variants.
- ProLock.jsx — if (!is_pro || !FeatureFlags[feature]) wraps blurred + lock tag + opens upgrade modal on click.
- KbdShortcut.jsx — <kbd>⌘</kbd>+<kbd>K</kbd> visual.
- Drawer.jsx — right slide-in; focus-trap; closes on Esc.
- Tabs.jsx — accessible role=tablist; keyboard arrow nav.
- Tooltip.jsx — @wordpress/components Popover wrapper.
- Icon.jsx — wraps @wordpress/icons.
- ColumnPicker.jsx — checkbox list + persist to localStorage per product.
- Diff/DiffViewer.jsx — used by PROMPT 25.
- Charts/MiniLine.jsx, MiniBar.jsx — sparkline-style with no external dep.
- Heatmap/Cell.jsx, Legend.jsx — used by PROMPT 27.
- Activity/FeedItem.jsx, PresenceAvatars.jsx — used by PROMPT 30.

Acceptance
- tests/E2E/shared-components.spec.ts renders each in isolation and passes axe-core.
```

---

## PROMPT 13 — Dashboard View

```
Goal: build the Dashboard view — KPI tiles, recent jobs, quick actions, time-saved hero card, upgrade banner (Free only). Will be extended in PROMPT 33 to surface persona-aware onboarding cards.

Depends on: PROMPT 12.

Output files:
- assets/admin/src/components/views/Dashboard.jsx
- tests/E2E/dashboard.spec.ts

Requirements
- Fetches: jobs.list({ per_page: 5 }), heatmap.summary() (small), activity.list({ per_page: 10 }).
- Tiles: Variations managed · Edits (7d) · Running jobs (pulse if > 0) · Failed jobs (30d, links to filtered jobs view).
- Hero: "Time saved this month" big number + trend mini-bar chart. Pulled from job count × est minutes (PROMPT 32 aha receipt math).
- Recent jobs list (5 rows) with Rollback shortcut.
- Recent activity (5 rows) feeding from PROMPT 30.
- Quick actions: Edit a product · Run CSV import · Ask AI · Create rule · See heatmap.
- Upgrade banner only when !is_pro.
- Empty state for fresh installs.

Acceptance
- axe-core 0 violations.
- E2E: seed 3 jobs → dashboard shows them.
```

---

## PROMPT 14 — Bulk Editor View (AG Grid) — the hero

```
Goal: AG Grid Community spreadsheet with custom cell editors, bulk actions, formula support, undo/redo, column picker, sticky Apply bar that routes through the Diff drawer (PROMPT 25). Margin column comes from PROMPT 28.

Depends on: PROMPT 12.

Output files (under assets/admin/src/components/views/BulkEditor/):

- index.jsx — ProductPicker (multi in Pro with <ProLock>) + AG Grid mount.
- columns.js — dynamic defs: variation_id (ro), sku, attribute_<slug>, regular_price, sale_price, stock_quantity, stock_status, status. Add 'profit_margin' column registered as inactive — PROMPT 28 activates it.
- cellEditors/PriceEditor.jsx — numeric + formula via PriceCalculator parser (client mirror).
- cellEditors/StatusEditor.jsx — agSelectCellEditor with publish|private.
- Toolbar.jsx — sticky; selected-row count; Increase price %, Decrease price %, Set stock, Enable, Disable, Delete; formula input.
- ColumnPicker.jsx (uses shared).
- UndoRedo.jsx — bounded stack of cell edits.
- ApplyBar.jsx — sticky bottom; "{n} changes pending"; Discard + "Preview & Approve" (always — opens diff via /jobs?dry=1 then routes to #/jobs/{id}/diff).
- ProductPicker.jsx — type-ahead /variations?search=.

Keyboard
- Ctrl/Cmd+Z|Y — undo/redo
- Ctrl/Cmd+A — select all rows on page
- / — focus picker
- ? — shortcut sheet
- Esc — clear selection
- Each registered as a Command in PROMPT 31 registry.

Acceptance
- 1,000 variations render smoothly (virtualised); cell edits queue into dirty state.
- Apply → creates a preview job → navigates to #/jobs/{id}/diff (PROMPT 25).
- No write until diff is approved.
```

---

## PROMPT 15 — Jobs View + Rollback Drawer

```
Goal: jobs table with polling, per-job drawer, pause/resume/cancel, rollback preview + execute. Will be extended in PROMPT 25 with diff/approval states and PROMPT 29 with scheduled-job states.

Depends on: PROMPT 12 + PROMPT 07.

Output files (under assets/admin/src/components/views/Jobs/):

- index.jsx — table: ID, type, source (manual|rule|schedule|ai), status badge, progress, created by, created at, processed/total, actions. Filters by status/source/type.
- JobRow.jsx — action menu: View, Pause, Resume, Cancel, Rollback, Re-run, Approve (PROMPT 25), Reject (PROMPT 25). Pause/Resume/Cancel Pro-locked.
- JobDrawer.jsx — opens on View; meta, per-row deltas (first 100, paginated), error_log, mini activity timeline.
- RollbackDrawer.jsx — preview; Confirm; progress.

Polling — useJobsPolling every 3s while any row running|queued; backoff 2× on error to cap 30s.

Acceptance
- E2E: create job → row appears → progresses → status complete within SLA.
- Cancel halts further chunks.
```

---

## PROMPT 16 — Templates View (Pro)

```
Goal: save/apply variation templates.

Depends on: PROMPT 12 + PROMPT 07.

Output files (under assets/admin/src/components/views/Templates/):

- index.jsx — card grid.
- TemplateCard.jsx — name, attributes, SKU pattern, actions: Apply to product, Duplicate, Delete.
- CreateTemplateModal.jsx — from scratch or from current editor state.
- ApplyTemplateModal.jsx — choose product, preview, Apply as Job (goes through Diff drawer).

Acceptance
- All operations through REST /templates.
- Wrapped in <ProLock feature="TEMPLATES">.
```

---

## PROMPT 17 — AI Assistant View

```
Goal: AI panel with four tabs, cost estimator, preview-then-apply pattern. Rule-suggestion mode added in PROMPT 26.

Depends on: PROMPT 12 + PROMPT 07.

Output files (under assets/admin/src/components/views/AiAssistant/):

- index.jsx — tab host.
- tabs/Generate.jsx — "Create sizes S-XXL in red and blue"; rules textarea; Preview button.
- tabs/SuggestPrices.jsx — paste or pick rows; returns [{sku, suggested_price, reason}]; accept individually or all.
- tabs/CleanImport.jsx — paste CSV or link a pending import job; returns normalised rows.
- tabs/Summarize.jsx — summarises a selected job in plain English.
- CostBadge.jsx — "Estimated cost ~$0.0012" from /ai/suggest-cost.
- ResultTable.jsx — row checkboxes + "Apply selected as Job" → routes through Diff drawer.

Acceptance
- Never calls a write endpoint without explicit Apply.
- "AI not configured" empty state with link to Settings → AI Provider.
- Free: Generate capped at 3 rows, others Pro-locked.
```

---

## PROMPT 18 — Settings + Help Views

```
Goal: settings with tabs (General, AI Provider, Retention, Diagnostics, Appearance) and the Help view.

Depends on: PROMPT 12 + PROMPT 07.

Output files (under assets/admin/src/components/views/Settings/):

- index.jsx — Tabs host.
- General.jsx — menu icon override (SVG picker), default chunk size, row-count guardrail thresholds, concurrency lock toggle, uninstall-data toggle, cost meta key (with auto-detect, PROMPT 28), margin thresholds, "Skip diff drawer for tiny jobs (≤N)" (PROMPT 25), "Show time-saved estimates" (PROMPT 32), "Disable undo button on jobs > N rows" (PROMPT 32).
- AiProvider.jsx — picker (OpenAI | Anthropic | Custom | Disabled), API key (password, test-connection), model, consent checkbox, per-day request cap, per-day token cap, usage meter.
- Retention.jsx — sliders: jobs TTL, deltas TTL, AI log TTL, events TTL (PROMPT 27), activity TTL (PROMPT 30); "Keep forever" toggles.
- Diagnostics.jsx — Download system-info JSON, Reset UI preferences, Open Action Scheduler, "Import from bulk-variations-pro" button (PROMPT 00).
- Appearance.jsx — theme picker (auto|light|dark|OLED), accent override, density compact/comfortable, motion toggle.

Under assets/admin/src/components/views/Help.jsx
- Auto-generated keyboard shortcuts from command registry (PROMPT 31), docs links, changelog, support contact, tour relaunch button.

Persistence
- All settings via REST /settings. Stored under option bv_settings (single JSON blob) with schema validation.

Acceptance
- Writing settings triggers a toast and re-reads values.
- Test-connection calls AI adapter with a trivial ping; surfaces latency.
```

---

# Phase D — AI + Storefront + Licensing

## PROMPT 19 — AI Backend: Adapters + Service + Prompt Library + Cost Estimator

```
Goal: plug the AI backend behind AiAdapterInterface with OpenAI + Anthropic + Null adapters, prompt library, repair loop, cost estimator, per-site quota.

Depends on: PROMPT 03.

Output files:

1. src/AI/Adapters/OpenAiAdapter.php — wp_remote_post to https://api.openai.com/v1/chat/completions; reads API key from bv_settings; default gpt-4o-mini; 30s timeout; returns { success, data, tokens, error }.

2. src/AI/Adapters/AnthropicAdapter.php — wp_remote_post to https://api.anthropic.com/v1/messages; default claude-haiku-4-5; same response shape.

3. src/AI/Adapters/NullAdapter.php — isConfigured() false; canned "AI not configured".

4. src/AI/PromptLibrary.php — static get(string $type, array $vars): string; types: generate_variations, suggest_prices, clean_import, summarize_job, suggest_rule (PROMPT 26), target_margin_pricing (PROMPT 28), suggest_schedule (PROMPT 29). Every prompt ends with "Return ONLY valid JSON. No prose."

5. src/AI/AiAssistantService.php
   - generate(string $type, array $context): array — sanitize → cache lookup → adapter request → JSON-parse. On parse failure do ONE repair attempt then fail.
   - Cache key: sha256(json_encode(sanitized)). TTL 3600s.
   - Logs to bv_ai_log with tokens_used, accepted=0.

6. src/Services/CostEstimator.php — estimateTokens(string $text); estimateCost(array $context): float using a built-in price table per model.

7. Quota enforcement — before request: read today's tokens_used from bv_ai_log; compare to bv_settings.ai_daily_token_cap; return error if exceeded.

8. Hard kill — if (defined('BV_DISABLE_AI') && BV_DISABLE_AI) always bind NullAdapter.

9. Pro-only — in Free, always bind NullAdapter EXCEPT for the "Generate 3 variations" teaser path.

10. Unit tests for sanitize, cache, repair loop, quota, cost estimator.

Acceptance
- Live wp-env call with stub server returns parsed structure.
- Invalid-JSON stub triggers exactly one repair attempt.
- define('BV_DISABLE_AI', true) forces NullAdapter even with valid key.
```

---

## PROMPT 20 — Storefront: Shortcode + Gutenberg Block + Cart Blocks Compatibility

```
Goal: storefront variation grid surfaces: shortcode, server-rendered Gutenberg block, Cart Blocks compatibility.

Depends on: PROMPT 02.

Output files:

1. src/Frontend/VariationGrid.php
   - shortcode [bv_variation_grid product_id="…"].
   - Optional auto-inject on variable product pages via 'woocommerce_variable_add_to_cart' (toggle in settings).

2. src/Frontend/Block.php
   - Registers block "bulk-variations/variation-grid" via register_block_type_from_metadata.
   - Server-rendered; editor preview via ServerSideRender.

3. assets/frontend/block/block.json — name, apiVersion 3, supports { anchor: true }, attributes { productId: number }.

4. assets/frontend/block/edit.jsx — product picker + InspectorControls.

5. templates/frontend/variation-grid.php — responsive table; rows = primary attribute, cols = secondary; qty inputs (min=0); out-of-stock cells greyed + label; proper aria.

6. assets/frontend/js/frontend-grid.js (vanilla)
   - Collect non-zero qty on Add-All-to-Cart.
   - Use wc_add_to_cart_params if present; else fall back to /wp-json/wc/store/v1/cart/add-item for Cart Blocks.
   - Dispatch 'wc-blocks_added_to_cart' + 'added_to_cart'.

7. assets/frontend/css/frontend.css — theme-agnostic, CSS custom properties, mobile stack.

8. Compatibility declarations already in bulk-variations.php (PROMPT 02).

9. tests/Unit/Frontend/VariationGridTest.php — shortcode + block render assertions.
```

---

## PROMPT 21 — Freemius Licensing + FeatureFlags + Pro/Free Split

```
Goal: integrate Freemius with a freemium single codebase, __premium_only markers, clean feature gating.

Depends on: most earlier prompts.

Output files:

1. src/Licensing/LicenseManager.php
   - bv_freemius() — Freemius SDK instance (slug, public_key, premium_slug).
   - isPro(): bool. Handles opt-in, skip, uninstall cleanup.

2. src/Licensing/FeatureFlags.php
   - Constants: AI_ASSISTANT, BULK_EDITOR_UNLIMITED, SCHEDULED_IMPORTS, ROLLBACK, REST_API, WP_CLI, MULTISITE, ADVANCED_FORMULAS, TEMPLATES, PAUSE_RESUME, DIFF_PER_ROW_REJECT, RULES_UNLIMITED, RULES_EVENT_TRIGGERS, HEATMAP_FULL_WINDOW, HEATMAP_DRILL_IN, MARGIN_AI_PRICING, SCHEDULE_AUTO_REVERT, ACTIVITY_PRESENCE, INLINE_HISTORY_FULL, PERSONA_DETECTION.
   - isEnabled(string $feature): bool.
   - map(): array — for localisation.

3. ServiceProvider::register() — bind NullAdapter when !isEnabled(AI_ASSISTANT).

4. Pro REST routes — wrap with FeatureFlags checks; return 402 + ErrorResponse on Free.

5. /* __premium_only */ markers around Pro-only PHP blocks for Freemius code-strip.

6. composer run build-free:
   - Strauss.
   - vendor/bin/fs strip.
   - Zip to dist/bulk-variations-free.zip.

Acceptance
- Free zip installs; Pro features show upgrade prompts.
- Pro zip unlocks gated features.
```

---

# Phase E — Compliance & QA

## PROMPT 22 — Privacy, Uninstall Cleanup, Retention, Diagnostics

```
Goal: WP.org review-ready privacy + uninstall story.

Depends on: PROMPT 02, PROMPT 19.

Output files:

1. src/Privacy/PolicyContent.php — registers wp_add_privacy_policy_content with a template describing data stored + AI provider transmissions.

2. src/Privacy/PrivacyExporter.php + PrivacyEraser.php — hook into wp_privacy_personal_data_exporters/erasers. Export/erase rows in bv_ai_log + bv_activity (PROMPT 30) keyed by user_id + admin-entered prompts.

3. Strengthen uninstall.php (from PROMPT 02): if bv_settings.remove_data_on_uninstall === true, drop all bv_* tables + options + user meta + AS actions for group 'bulk-variations'.

4. src/Services/Diagnostics.php
   - export(): PHP/WP/WC versions, theme, active plugins, BV version, feature flag states, job counts per status, retention settings, AI provider (yes/no/no keys), last 10 errors from Logger.
   - Surfaced via REST /diagnostics and CLI wp bv diagnostics export.

5. src/Support/Logger.php — info/warn/error; writes to WP debug log + bounded option bv_log (last 200).

Acceptance
- WP.org Privacy screen shows plugin section.
- Export + Erase return expected rows.
- Uninstall with flag true leaves zero plugin rows.
```

---

## PROMPT 23 — Accessibility & i18n Pass

```
Goal: bring admin UI to WCAG 2.1 AA and ship RTL + .pot.

Depends on: PROMPT 11..18 + later view prompts.

Tasks:

1. Admin CSS — generate admin-rtl.css via @wordpress/scripts or rtlcss; register with wp_style_add_data('bv-admin','rtl','replace').

2. AG Grid a11y
   - ensureDomOrder: true, rowHeight: 36, getRowDescription "Variation {sku}, {n} columns".
   - Sync rowSelection with aria-selected.
   - Skip-link above grid: "Skip grid to toolbar".

3. Keyboard — audit every interactive element; visible focus ring; shortcut sheet overlay via `?` (auto-generated from command registry, PROMPT 31).

4. Contrast — every token pair (text on bg, muted on surface, primary-ink on primary) in both themes; adjust below 4.5:1.

5. i18n — all strings wrapped; `wp i18n make-pot . languages/bulk-variations.pot --slug=bulk-variations`; wp_set_script_translations.

6. Tests — tests/E2E/a11y.spec.ts — axe-core on every view, 0 serious/critical.

Acceptance
- Tab order top→bottom, left→right.
- RTL screenshot sanity-check in Playwright.
```

---

## PROMPT 24 — Quality Engineering: Tests, Benchmarks, Visual Regression

```
Goal: lock the quality bar.

Depends on: all previous prompts.

Tasks:

1. Unit — fill gaps; > 80% lines on src/Engine, src/Services, src/ImportExport, src/Rollback, src/AI.

2. Integration (wp-env) — tests/Integration/EndToEndBulkEditTest.php — seed variable product with 50 variations → REST POST /jobs → approve → poll → assert applied → rollback → assert reverted.

3. Benchmarks — scripts/benchmark.php — seed 10k variations, time bulk price update + rollback. Writes JSON to tests/benchmarks/latest.json. CI fails if wall-time > thresholds (plan §10).

4. E2E (Playwright)
   - tests/E2E/editor-happy-path.spec.ts — create job from UI → row in Jobs → rollback.
   - tests/E2E/settings.spec.ts — change AI provider, save, test-connection.
   - tests/E2E/storefront-grid.spec.ts — shortcode page, add qty, verify cart updated.
   - tests/E2E/diff-approval.spec.ts (PROMPT 25)
   - tests/E2E/rules-builder.spec.ts (PROMPT 26)
   - tests/E2E/heatmap.spec.ts (PROMPT 27)
   - tests/E2E/margin-column.spec.ts (PROMPT 28)
   - tests/E2E/schedule.spec.ts (PROMPT 29)
   - tests/E2E/activity-feed.spec.ts (PROMPT 30)
   - tests/E2E/command-palette.spec.ts (PROMPT 31)
   - tests/E2E/snackbar-undo.spec.ts (PROMPT 32)
   - tests/E2E/first-run.spec.ts (PROMPT 33)

5. Visual regression (optional) — Playwright screenshots; 0.1 pixel threshold; both themes.

Acceptance
- CI green on matrix.
- Benchmark thresholds met.
```

---

# Phase F — Uniqueness Features ⭐

## PROMPT 25 — Visual Diff & Approval Flow (Feature B1)

```
Goal: every bulk job ≥ 5 rows opens a side-by-side diff before executing. Reviewer can approve, reject, or per-row accept. Reuses bv_job_changes delta storage. This becomes the safety net for the whole plugin.

Depends on: PROMPT 07 (REST), PROMPT 14 (Bulk Editor), PROMPT 15 (Jobs).

Output files:

1. migrations/0002_diff_approval.sql
   ALTER TABLE bv_jobs
     ADD COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'none' COMMENT 'none|pending_review|approved|rejected',
     ADD COLUMN reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
     ADD COLUMN reviewed_at DATETIME DEFAULT NULL,
     ADD COLUMN review_comment TEXT DEFAULT NULL;
   ALTER TABLE bv_job_changes
     ADD COLUMN review_decision VARCHAR(20) DEFAULT 'pending' COMMENT 'pending|accepted|rejected',
     ADD INDEX review_decision_idx (job_id, review_decision);

2. src/Diff/DiffService.php
   - buildDiff(int $job_id): array
       {
         summary: { rows_total, rows_changed, fields: {price:N, stock:N, sku:N, ...},
                    products_affected:int, revenue_impact:float, magnitude_buckets:{small,medium,large} },
         rows: [ { change_id, variation_id, sku, product_title, attributes, fields:{ _price:{old,new,delta_pct}, ... } } ]
       }
   - applyDecisions(int $job_id, array $decisions): void
   - filterPendingDeltas(int $job_id): array — returns only accepted rows.
   - On approve(): deletes rejected deltas, sets review_status=approved, calls JobManager::resumeAfterApproval.

3. src/Diff/DiffDecisionApplier.php — pure helper to apply UI decisions to bv_job_changes table in batches.

4. src/Services/RevenueImpactEstimator.php
   - estimate(array $deltas): float — sums (new_price - old_price) × avg_daily_orders_per_variation over 30 days from bv_events (PROMPT 27). 0 if no history.
   - Caches per job for 60s.

5. JobManager update
   - dispatch() now: stores deltas first, sets status='preview', review_status='pending_review' if rowCount > Settings.diff_threshold (default 5). Returns job_id without enqueuing chunks.
   - resumeAfterApproval(int $job_id): void — enqueues first chunk.
   - Skipping diff: Settings → "Auto-approve jobs ≤ N rows" (default 5).

6. REST extensions
   - GET  /jobs/{id}/diff
   - POST /jobs/{id}/diff/decisions  body: { decisions: [{change_id, accept}] }
   - POST /jobs/{id}/approve         body: { comment? }
   - POST /jobs/{id}/reject          body: { reason }

7. React: views/Jobs/DiffDrawer.jsx
   - Two-column diff. Sticky header with summary counts + RevenueImpactBadge.
   - Per-row checkbox (default accepted). "Accept all" / "Reject all" toolbar.
   - Filters: by attribute, by field, by change magnitude (>10%, >20%, >50%).
   - Magnitude colour scale on each delta cell.
   - Sticky footer: Approve / Reject / Save & Close.
   - Keyboard: J/K row nav, X toggle, A approve, R reject. All registered as Commands in PROMPT 31.

8. React: views/BulkEditor/ApplyBar.jsx (modify from PROMPT 14)
   - CTA always reads "Preview & Approve" (not "Apply"). Even with 1 row — sub-threshold jobs short-circuit through the drawer with a 1-row diff and the "Looks good — apply now" CTA.

9. React: shared/RevenueImpactBadge.jsx — green/red pill "+$420 / mo" with hover tooltip showing math.

10. Permissions
    - Optional cap bv_review_jobs. If defined, only users with this cap can approve; else falls back to manage_woocommerce.
    - Settings → "Require dual approval on jobs > N rows" (Pro).

11. Tests
    - tests/Unit/Diff/DiffServiceTest.php
    - tests/Integration/DiffWorkflowTest.php — create job → diff → reject 5 rows → approve → only accepted rows applied → rollback restores only applied rows.
    - tests/E2E/diff-approval.spec.ts — UI happy path.

Acceptance
- Bulk edit > 5 rows: clicking Apply opens the diff drawer, not the job page.
- Rejecting a row excludes it from the actual SQL UPDATE.
- Rollback of an approved job restores only the rows that were actually applied.
- Revenue impact shows numbers with the time window noted.
```

**Verify**

```
composer test -- --filter "Diff"
npx playwright test diff-approval.spec.ts
```

---

## PROMPT 26 — Auto-pilot Rules Engine (Feature B2)

```
Goal: visual if-then rules that run on a daily cron via Action Scheduler. Pro adds event-based triggers and AI rule suggestion. The retention loop.

Depends on: PROMPT 07 (REST), PROMPT 12 (shared), PROMPT 19 (AI), PROMPT 25 (rule-spawned jobs flow through diff).

Output files:

1. migrations/0003_rules.sql
   CREATE TABLE bv_rules (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     name VARCHAR(150) NOT NULL,
     description TEXT,
     enabled TINYINT(1) NOT NULL DEFAULT 1,
     trigger_type VARCHAR(60) NOT NULL,          -- 'daily' | 'on_demand' | 'on_event'
     event_type VARCHAR(60) DEFAULT NULL,        -- 'variation_created' | 'order_placed' | ...
     conditions LONGTEXT NOT NULL,               -- JSON tree
     actions LONGTEXT NOT NULL,                  -- JSON list
     require_approval TINYINT(1) DEFAULT 1,      -- if 1 rule-created jobs go through diff drawer
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
     job_id BIGINT(20) UNSIGNED DEFAULT NULL,
     status VARCHAR(30) DEFAULT 'running',
     PRIMARY KEY (id),
     KEY rule_idx (rule_id)
   );

2. src/Rules/RuleFieldRegistry.php
   - Built-in fields: stock_quantity, regular_price, sale_price, profit_margin (PROMPT 28), days_since_creation, days_since_last_order, image_count, attribute.<slug>, total_sales_30d, total_views_30d.
   - register(string $key, callable $sql_fragment, callable $php_extract): void
   - Each field declares the SQL fragment used in WHERE.

3. src/Rules/Condition.php
   - Tree evaluator:
     { op:"AND", children:[ {field, op, value}, ... ] }
   - Ops: =, !=, <, <=, >, >=, in, contains, is_empty, regex.
   - matchVariations(int $rule_id): array — builds ONE SQL query against postmeta + bv_events. Never iterates variations in PHP.

4. src/Rules/RuleActionRegistry.php + src/Rules/Action.php
   - AdjustPriceAction (percent|absolute)
   - SetStockAction
   - SetSaleAction (sale_price + window dates)
   - AddTagAction / RemoveTagAction
   - FlagForReviewAction
   - NotifyAction (email | Slack webhook | generic webhook URL)
   - Each translates to deltas → becomes the rule's job.

5. src/Rules/RulesEngine.php
   - evaluateRule(int $rule_id, bool $dry_run=false): array
     - Calls Condition::matchVariations.
     - Translates to deltas via actions.
     - If dry_run: returns matches without persisting.
     - Else: creates a bv_jobs row (type='rule', source='rule', meta.rule_id=$rule_id, review_status='pending_review' if rule.require_approval).
   - evaluateAll(): runs every enabled daily rule.

6. src/Jobs/RulesDailyJob.php — hooked to as_schedule_recurring_action('bv_rules_daily', DAY_IN_SECONDS). Calls RulesEngine::evaluateAll.

7. Event-trigger hooks (Pro) — bind WC actions (woocommerce_new_order_item, save_post_product_variation, etc.) → call RulesEngine::evaluateRulesForEvent.

8. REST extensions
   - GET    /rules
   - POST   /rules
   - GET    /rules/{id}
   - PUT    /rules/{id}
   - DELETE /rules/{id}
   - POST   /rules/{id}/run            dry-run
   - POST   /rules/{id}/enable
   - POST   /rules/{id}/disable
   - GET    /rules/{id}/runs

9. React: views/Rules/ (sidebar item between Templates and AI)
   - index.jsx — list with toggle, last-run time, match count, sparkline of run history.
   - RuleBuilder.jsx — visual condition builder. Row: [field dropdown] [op dropdown] [value input] × [AND/OR group]. Add condition / Add group. Drag-reorder.
   - ActionPicker.jsx — list of actions with parameter forms.
   - RulePreview.jsx — dry-run button → opens preview drawer (variations matched + projected deltas + revenue impact).
   - RunHistoryDrawer.jsx — per-rule run timeline.
   - SuggestRuleButton.jsx — Pro only; calls /ai/suggest with prompt_type='suggest_rule' + sanitized store profile. Returns up to 5 plausible rules. Each opens the RuleBuilder pre-filled.

10. Free / Pro
    - Free: up to 2 active rules, daily trigger only.
    - Pro: unlimited rules, event triggers, AI suggest, webhook actions.

11. Tests
    - tests/Unit/Rules/ConditionTest.php — tree evaluation + SQL snapshots.
    - tests/Integration/RulesEngineTest.php — seed stale variations → run rule → assert deltas.
    - tests/E2E/rules-builder.spec.ts.

Acceptance
- Daily action scheduled on activation.
- wp action-scheduler run --hooks=bv_rules_daily fires all enabled rules.
- Dry-run shows matches without writing.
- AI suggest returns valid rule JSON; Create persists it.
```

**Verify**

```
composer test -- --filter "Rules"
npx wp-env run tests-wordpress wp action-scheduler run --hooks='bv_rules_daily'
```

---

## PROMPT 27 — Performance Heatmap (Feature B3)

```
Goal: one-screen heatmap of variation sales velocity. The "screenshot for Twitter" feature. Reuses bvpro_analytics from the old plugin via migration.

Depends on: PROMPT 12, PROMPT 00 (migration path).

Output files:

1. migrations/0004_events.sql
   CREATE TABLE bv_events (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     variation_id BIGINT(20) UNSIGNED NOT NULL,
     product_id BIGINT(20) UNSIGNED NOT NULL,
     event_type VARCHAR(40) NOT NULL,
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
     - woocommerce_new_order_item → 'order' with qty + revenue.
     - woocommerce_add_to_cart → 'add_to_cart' qty=1.
     - woocommerce_after_single_product → 'view' (once-per-session cookie guard).
     - woocommerce_order_refunded → 'refund' negative qty/revenue.
   - Buffer writes to a per-request array; flush on 'shutdown' via single multi-row INSERT.

3. src/Analytics/HeatmapService.php
   - getHeatmap(array $filters): array
       { products: [ { product_id, title, variations: [ { variation_id, sku, attrs, velocity, units, revenue, last_sold_at } ] } ],
         buckets: [ { label:'hot', min:30, max:null, color:'--bv-ok' }, ... ],
         window_days: 30 }
   - SQL: single GROUP BY against bv_events + JOIN posts + postmeta. Cached 5min via transient on filter hash.
   - getCellDetail(int $variation_id, int $window_days): array — orders, top customers (hashed if PII), daily timeline.

4. src/Analytics/MigrationFromBvpro.php
   - One-shot: SELECT * FROM bvpro_analytics → INSERT INTO bv_events with column mapping.
   - Triggered from Settings → Diagnostics button or `wp bv events migrate`.

5. REST
   - GET /heatmap?window=30&product_ids[]=…&attr.color=red
   - GET /heatmap/cell?variation_id=…&window=30

6. React: views/Heatmap/ (sidebar item between Dashboard and Bulk Editor)
   - index.jsx — controls bar (window, product filter, attribute filter, colour-by selector: Velocity | Margin (PROMPT 28) | Stock) + grid.
   - HeatmapGrid.jsx — canvas-based for > 5k cells (fallback CSS grid < 1k cells). Hover shows tooltip with SKU + numbers.
   - CellDetail.jsx — drawer with daily timeline (Recharts), top orders, "Create rule from this view" CTA → opens RuleBuilder with selected variations preloaded.
   - EmptyState.jsx — "Need 7 days of sales to render. Come back tomorrow."
   - Legend.jsx — gradient ramp + bucket labels.
   - Export buttons — PNG (via html-to-image) + CSV (flattened table).

7. Free / Pro
   - Free: 7-day window only, no drill-in.
   - Pro: 7/30/90/365-day, drill-in, PNG export, colour-by-margin, colour-by-stock.

8. Tests
   - tests/Unit/Analytics/HeatmapServiceTest.php — bucketing maths.
   - tests/Integration/EventRecorderTest.php — order creates a row.
   - tests/E2E/heatmap.spec.ts — seed events, assert cell hue.

Acceptance
- Heatmap loads under 800ms with 1k variations × 30 days.
- PNG export downloads a non-empty file.
- "Create rule from this view" pre-fills RuleBuilder with the variation IDs in scope.
```

**Verify**

```
composer test -- --filter "Heatmap|EventRecorder"
npx playwright test heatmap.spec.ts
```

---

## PROMPT 28 — Profit Margin Column (Feature B6)

```
Goal: profit_margin column in the spreadsheet, computed from a configurable cost meta key. Cell tints by threshold. AI "Suggest price for target margin" inline.

Depends on: PROMPT 14 (Bulk Editor), PROMPT 18 (Settings), PROMPT 19 (AI).

Output files:

1. src/Services/MarginCalculator.php
   - getCost(int $variation_id, string $meta_key): float — reads configured meta key, defaults to _cost then ACF cost_price then Meta Box cost.
   - autoDetectCostKey(): ?string — scans first 50 variations for likely meta keys (heuristic: key contains 'cost' AND value is numeric > 0).
   - margin(float $price, float $cost): array — { absolute:float, percent:float }.
   - batchCosts(array $variation_ids, string $meta_key): array — single SQL IN.

2. Settings extension (PROMPT 18 General tab)
   - "Cost meta key" text input (default _cost) + "Auto-detect" button.
   - "Low margin threshold (%)" — default 10.
   - "High margin threshold (%)" — default 40.
   - "Show margin column in editor" toggle.

3. React: BulkEditor columns extension
   - Add column { field: 'profit_margin', headerName: 'Margin %', editable: false, sortable: true } when setting enabled.
   - cellRenderer/MarginCell.jsx — renders "23.4%" with background colour interpolated red→amber→green based on thresholds.
   - Hover tooltip: cost source + raw cost value.

4. AI integration
   - Toolbar bulk-action: "Suggest price for target margin…" → modal with target % slider → POST /ai/suggest type='target_margin_pricing' context: current prices + costs.
   - Preview returns suggested prices per row → opens Diff drawer (PROMPT 25).

5. REST extension
   - GET /variations now includes profit_margin when ?include=margin is set, so React doesn't recompute client-side for large grids.

6. Heatmap hookup (already wired in PROMPT 27)
   - "Colour by: Velocity | Margin | Stock" selector swaps the metric using HeatmapService data.

7. Free / Pro
   - Free: column visible.
   - Pro: AI "Suggest price for target margin" + colour-by-margin in heatmap.

8. Tests
   - tests/Unit/Services/MarginCalculatorTest.php — missing cost, zero price, negative margin.
   - tests/E2E/margin-column.spec.ts — set cost, verify cell colour.

Acceptance
- Cost=5, price=10 → margin shows 50.0% (green).
- Missing cost → "—" + one-time toast suggesting auto-detect.
- Sorting by margin asc/desc works in AG Grid.
```

**Verify**

```
composer test -- --filter "MarginCalculator"
npx playwright test margin-column.spec.ts
```

---

## PROMPT 29 — Schedule & Stage Workflows (Feature B5) — "Black Friday Mode"

```
Goal: prepare a bulk update now, stage it, auto-execute at a specific time. Optionally auto-revert on a second timer. The "schedule your Black Friday" feature.

Depends on: PROMPT 05 (JobManager), PROMPT 25 (Diff drawer — staged jobs are pre-approved bundles).

Output files:

1. migrations/0005_schedule.sql
   CREATE TABLE bv_schedule (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     name VARCHAR(150) NOT NULL,
     job_id BIGINT(20) UNSIGNED NOT NULL,        -- the prepared job (status='staged')
     execute_at DATETIME NOT NULL,
     revert_at DATETIME DEFAULT NULL,
     revert_job_id BIGINT(20) UNSIGNED DEFAULT NULL,
     status VARCHAR(30) DEFAULT 'pending',       -- pending|executed|reverted|cancelled|failed
     created_by BIGINT(20) UNSIGNED,
     created_at DATETIME NOT NULL,
     PRIMARY KEY (id),
     KEY execute_idx (status, execute_at)
   );

2. src/Schedule/SchedulerService.php
   - schedule(int $job_id, DateTime $execute_at, ?DateTime $revert_at, string $name): int (schedule_id).
   - cancel(int $schedule_id): bool.
   - listUpcoming(int $limit=50): array.
   - On schedule(): bv_jobs.status=staged, AS::schedule_single_action(execute_at, 'bv_execute_scheduled', [schedule_id]).
   - If revert_at provided: capture a pre-execution snapshot (postmeta dump of affected variations) into option bv_schedule_{$id}_snapshot.

3. src/Schedule/RevertScheduler.php — on bv_execute_scheduled completion, schedule bv_revert_scheduled at revert_at.

4. src/Jobs/StagedExecutionJob.php — hooked to bv_execute_scheduled. Resumes the staged job via JobManager (review_status auto-approved). Marks schedule.status=executed.

5. src/Jobs/StagedRevertJob.php — hooked to bv_revert_scheduled. Applies the inverse deltas from snapshot. Marks schedule.status=reverted.

6. REST extensions
   - GET    /schedule
   - POST   /schedule              body: { job_id, execute_at, revert_at?, name }
   - DELETE /schedule/{id}
   - POST   /schedule/{id}/cancel
   - GET    /schedule/{id}

7. React: views/Schedule/ (sidebar item between Rules and Templates)
   - index.jsx — calendar/cycle view (week + month) showing upcoming + past schedules. Linear-style.
   - ScheduleForm.jsx — pick prepared job (or "Create new from current editor"), set execute date+time, optional revert date+time, optional name.
   - ScheduleCard.jsx — name, countdown ("in 3d 4h"), execute_at, revert_at, status badge, Cancel button.
   - CountdownTimer.jsx — live countdown, switches to "Running…" when execute_at passes.
   - Quick presets: "Tonight at 3am", "Next Black Friday 23:59", "Christmas Eve midnight".
   - AI integration: "Suggest a schedule" → POST /ai/suggest type='suggest_schedule' with context (upcoming holidays, current sales). Returns a few suggested execute_at + revert_at pairs.

8. Bulk Editor extension
   - ApplyBar gets a chevron menu next to "Preview & Approve":
       → Apply now (default)
       → Schedule for later…  (opens SchedulePicker modal pre-filled with the current job)

9. Free / Pro
   - Free: schedule single jobs, no auto-revert.
   - Pro: auto-revert, recurring schedules (weekly/monthly), AI suggest.

10. Tests
    - tests/Integration/SchedulerServiceTest.php — schedule a job, fast-forward time, assert executed.
    - tests/E2E/schedule.spec.ts — UI flow.

Acceptance
- Scheduling a job + fast-forwarding AS clock executes it.
- Auto-revert restores values at revert_at.
- Cancelling a pending schedule unhooks AS actions.
- Calendar view renders 30 upcoming schedules in < 200ms.
```

**Verify**

```
composer test -- --filter "Scheduler"
npx playwright test schedule.spec.ts
```

---

## PROMPT 30 — Activity Feed + Live Presence (Feature B10)

```
Goal: an in-app activity feed ("Sarah edited 12 prices · 2 min ago") + live presence indicators (avatars next to cells being edited by other admins). Uses WP Heartbeat — no websocket infrastructure required. Makes the app feel alive.

Depends on: PROMPT 11 (store + hooks), PROMPT 12 (shared components), PROMPT 15 (Jobs).

Output files:

1. migrations/0006_activity.sql
   CREATE TABLE bv_activity (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     user_id BIGINT(20) UNSIGNED,
     verb VARCHAR(60) NOT NULL,                  -- 'job_created'|'job_completed'|'rule_fired'|'schedule_executed'|'rollback'|...
     subject_type VARCHAR(40) NOT NULL,          -- 'job'|'rule'|'schedule'|'variation'|'product'
     subject_id BIGINT(20) UNSIGNED,
     meta LONGTEXT,
     occurred_at DATETIME NOT NULL,
     PRIMARY KEY (id),
     KEY user_idx (user_id, occurred_at),
     KEY subject_idx (subject_type, subject_id, occurred_at),
     KEY recent_idx (occurred_at)
   );

2. src/Activity/ActivityFeedService.php
   - record(int $user_id, string $verb, string $subject_type, int $subject_id, array $meta=[]): void
   - list(array $filters=[], int $limit=20): array
   - For broadcast: stores in transient bv_activity_recent (last 50) for fast topbar reads.

3. src/Services/ActivityRecorder.php — wired into JobManager + RulesEngine + SchedulerService + RollbackService to fire on key events. Each fires an action 'bv_activity' that other plugins can listen to.

4. src/Activity/PresenceService.php
   - heartbeat(int $user_id, string $view, ?int $context_id, ?string $cell): void — TTL 90s in transient bv_presence_*.
   - getPresence(string $view, ?int $context_id): array — list of active users with their cell.
   - WP Heartbeat API hook: registers data filter to piggyback presence updates on existing heartbeat requests (no extra HTTP).

5. REST
   - GET  /activity
   - GET  /activity?subject_type=job&subject_id=42
   - POST /presence/heartbeat   body: { view, context_id?, cell? }
   - GET  /presence?view=editor&context_id=123

6. React: shared/Activity/FeedItem.jsx — single feed row with verb icon, "{actor} {verb_phrase} {subject_link} · {time_ago}".

7. React: shared/Activity/PresenceAvatars.jsx
   - Stack of 1-3 avatars; "+N more" chip beyond 3.
   - Used in: Topbar (global presence), JobDrawer (who's viewing), BulkEditor cells (tiny dot overlay on cells someone else is editing).

8. React: hooks/usePresence.js (already stubbed in PROMPT 11)
   - Sends heartbeat every 30s via WP Heartbeat data filter.
   - Surfaces other users' presence in store.activity.presence.
   - Conflict warning: if you edit a cell another user is also on, show inline warning "Sarah is editing this cell".

9. Dashboard + Topbar
   - Topbar: 2-3 right-side presence avatars showing "who's also here".
   - Dashboard: Recent activity card (PROMPT 13 already wires this).

10. Settings
    - "Show activity feed" toggle (default on).
    - "Show presence indicators" toggle (default on, Pro).
    - "Activity retention (days)" slider (default 90; ties to RetentionJob).

11. Free / Pro
    - Free: activity feed, no presence indicators.
    - Pro: presence indicators, cell-level conflict warnings.

12. Tests
    - tests/Unit/Activity/ActivityFeedServiceTest.php
    - tests/Integration/PresenceTest.php — two simulated heartbeats, verify list returns both.
    - tests/E2E/activity-feed.spec.ts.

Acceptance
- Creating a job fires a feed entry within 1s.
- Two browser sessions in the same view show each other's avatar in Topbar.
- Editing the same cell from session A while B is also there triggers the conflict warning.
- Retention purges old activity rows at the configured TTL.
```

**Verify**

```
composer test -- --filter "Activity|Presence"
npx playwright test activity-feed.spec.ts
```

---

# Phase G — UX Premium ⭐

## PROMPT 31 — Command Palette (Feature C1) — with favorites, AI suggestions, recipes

```
Goal: Cmd/Ctrl+K opens a global command palette. Fuzzy match navigates, runs actions, searches data. Single source of truth for every action — also feeds the shortcut sheet (PROMPT 23). Enhanced: favorites (pinned commands), recipes (multi-step macros), AI command suggestion, prefix modes.

Depends on: PROMPT 11 (Router + Topbar), PROMPT 12 (shared components).

Output files:

1. assets/admin/src/commands/registry.js
   - registerCommand({ id, title, category, keywords, run, hotkey, group, when, icon, badge? }): void
   - registerRecipe({ id, title, steps:[command_id, ...], confirm?:bool }): void
   - registerProvider({ id, search:(query)=>Promise<results> }): void — async results (e.g., jump-to-job-by-id, jump-to-product-by-sku).
   - getCommands(ctx): Command[]
   - searchCommands(query, ctx): Command[]  // fuse.js with score-based ranking.
   - favorites():string[] / toggleFavorite(id):void — persisted to localStorage.

2. assets/admin/src/commands/builtins.js — registers 30+ default commands across categories:
   - Navigate (Dashboard, Heatmap, Editor, Jobs, Rules, Schedule, Templates, AI, Settings, Help)
   - Edit (Create job, Apply formula, Toggle column, Open last job)
   - Jobs (Pause running, Resume, Cancel, Rollback latest, Approve pending review, Reject pending review)
   - Rules (Create rule, Run all rules now (dry-run), Disable all, Open rule by name)
   - Schedule (Create schedule, Cancel pending, Open today's schedule)
   - Heatmap (Switch metric, Export PNG, Drill into top-selling)
   - AI (Generate variations, Suggest prices, Suggest rule, Clean import, Toggle AI off)
   - Settings (Toggle dark mode, Toggle OLED, Toggle compact density, Reset UI preferences)
   - Data providers (Search variations, Open product…, Open job #…)
   - Recipes (e.g., "Black Friday prep" = create schedule with preset params)
   - Help (Open shortcuts, Email support, Open docs, Show changelog, Relaunch tour)

3. assets/admin/src/commands/recipes.js — built-in recipes:
   - "Mark all out-of-stock as draft" — generates a rule-style bulk action.
   - "Apply +15% to selected" — combines selection + formula + apply.
   - "Backup before changes" — runs CSV export, then opens editor.
   - User can save their own from the palette ("Save these last 3 actions as a recipe").

4. assets/admin/src/components/CommandPalette/index.jsx
   - Modal: input + categorized result list.
   - Prefix modes:
     - default → fuzzy commands
     - `>` → commands only
     - `?` → help / docs search
     - `#` → jump to job by ID
     - `@` → jump to user
     - `$` → jump to product/SKU
   - "★ Favorites" section at top.
   - "Recent" section (last 8) persisted to localStorage.
   - "Suggested" section (PROMPT 19 AI) — Pro: shows 3 AI-generated commands based on current view + recent activity ("Want to create a rule for stale variations?").
   - Inline previews for data commands (e.g., "Open job #42" shows mini summary inline).
   - Keyboard: ↑↓ move, Enter run, ⇥ scope to category, Esc dismiss, ⌘D toggle favorite, ⌘R run as recipe.

5. assets/admin/src/components/CommandPalette/triggers.js
   - Cmd/Ctrl+K toggles palette globally.
   - `/` from any view (when no input focused) opens palette pre-filtered to "Search".
   - Registered via the registry's hotkey field so they appear in the shortcut sheet.

6. Topbar.jsx (modify) — replace placeholder search with button "⌘K · Search & commands".

7. Help.jsx (modify) — "Keyboard shortcuts" section is auto-rendered from the command registry (every command with a hotkey).

8. Extension API
   - window.BulkVariations.commands.register(...) — public for third-party JS or other plugins to add commands.

9. A11y
   - role="dialog", aria-modal="true", focus-trap, return focus on close.
   - Each result row role="option", aria-selected, scrollIntoView on keyboard nav.
   - Reduced-motion: skips slide-in animation.

10. Free / Pro
    - Free: full command set + favorites + recipes.
    - Pro: AI command suggestion + cross-site favorites sync (via Freemius user account).

11. Tests
    - tests/Unit/commands/registry.test.js — register, search, run, favorites, recipes.
    - tests/E2E/command-palette.spec.ts — Cmd+K opens, "dash" matches Dashboard, Enter navigates, ⌘D toggles favorite, `#42` jumps to job.

Acceptance
- Cmd/Ctrl+K on any view opens palette in < 80ms.
- "rollback" returns command + recent jobs that match.
- Recent commands persist across reloads.
- Closing palette returns focus to the trigger.
- AI suggestion appears within 2s on a stale-store scenario.
```

**Verify**

```
npm test -- registry.test.js
npx playwright test command-palette.spec.ts
```

---

## PROMPT 32 — Snackbar with Undo + Aha Receipt + Action History (Features C2, C11)

```
Goal: every bulk action surfaces a snackbar with an undo button. Enhanced: stack undo (last N actions), edit-in-toast (modify and re-run), persistent action history sidebar.

Depends on: PROMPT 12 (Toast component to extend), PROMPT 06 (Rollback), PROMPT 11 (store), PROMPT 25 (diff drawer for re-run flow).

Output files:

1. assets/admin/src/components/shared/Snackbar.jsx
   - Variant of Toast: bottom-right stack, max 3 visible, auto-dismiss 8s with progress ring.
   - Primary line + optional secondary (smaller, muted).
   - Up to 2 action buttons (Undo + Re-run, or Undo + View).
   - Respects prefers-reduced-motion.
   - "Pin" icon — clicking pins the snackbar to prevent auto-dismiss; turns it into a sticky "Action card" in the bottom-right.

2. assets/admin/src/store/index.js (extend)
   - pushSnackbar({ id, primary, secondary, actions:[{label, run:()=>Promise, variant?}], context })
   - dismissSnackbar(id)
   - pinSnackbar(id)
   - history: array — last 50 actions persisted to localStorage (for action history view).

3. assets/admin/src/api/snackbarMiddleware.js — wraps apiFetch mutations.
   On successful POST /jobs/{id}/approve (or auto-approved jobs):
     pushSnackbar({
       primary: "Updated {rowCount} variations.",
       secondary: rowCount >= 5 ? `You'd have spent ~${estMinutes} min manually.` : null,
       actions: [
         { label: 'Undo', variant:'primary', run: () => apiFetch({path:`/bv/v1/jobs/${jobId}/rollback`, method:'POST'}) },
         { label: 'Re-run with edits', variant:'ghost', run: () => navigate(`#/editor?prefill=${jobId}`) }
       ],
       context: { jobId, rowCount, type:'bulk_edit' }
     });

4. assets/admin/src/components/UndoOrchestrator.jsx
   - Clicking Undo morphs snackbar into "Rolling back…" with progress ring.
   - On success → "Reverted. View job →" link to the rollback job.
   - On failure → inline error; snackbar stays for retry.
   - "Undo last 3" button appears at top of the snackbar stack if 3+ recent eligible actions.

5. assets/admin/src/components/views/ActionHistory/index.jsx (NEW small view, accessible from Help or palette)
   - Sidebar-style list of last 50 actions across all sessions for this user.
   - Each row: time, action description, rollback status, "Re-run with edits", "Pin to favorites".

6. Settings (PROMPT 18 extension)
   - "Show time-saved estimates" (default on).
   - "Disable undo button on jobs > N rows" (default off).
   - "Snackbar dwell time (s)" — default 8, range 4–20.
   - "Position" — bottom-right | bottom-center | top-right.

7. A11y
   - role="status", aria-live="polite".
   - Each action button is keyboard-reachable; "Undo" defaults to Enter for the focused snackbar.

8. Free / Pro
   - Free: undo + aha receipt.
   - Pro: stack undo (last 3), edit-in-toast, persistent action history view, configurable dwell time.

9. Tests
   - tests/Unit/snackbarMiddleware.test.js — intercepts mutations correctly.
   - tests/E2E/snackbar-undo.spec.ts — bulk edit → snackbar shows → Undo within 8s → variation price reverts.
   - tests/E2E/snackbar-edit.spec.ts — bulk edit → "Re-run with edits" → editor pre-filled.

Acceptance
- Every bulk_edit job triggers a snackbar with Undo.
- Clicking Undo within dwell window actually rolls back via /jobs/{id}/rollback.
- After dwell window, rollback is still available from Jobs page.
- Stack-undo undoes the last 3 jobs in reverse chronological order.
- Aha receipt hides for jobs < 5 rows.
- Action history persists across reloads.
```

**Verify**

```
npm test -- snackbarMiddleware.test.js
npx playwright test snackbar-undo.spec.ts
```

---

## PROMPT 33 — First-Run Experience (C3) + Dashboard Onboarding Cards (C10) + Persona Detection + Achievements

```
Goal: a 90-second interactive tour after activation that delivers the "aha moment" before the merchant gets bored. Plus a dashboard that grows smarter as the merchant uses the plugin. Enhanced: persona detection (B2B/B2C/wholesale tailors the tour + suggested rules), achievement log for engagement.

Depends on: PROMPT 13 (Dashboard), PROMPT 14 (Bulk Editor), PROMPT 17 (AI), PROMPT 06 (Rollback), PROMPT 26 (Rules).

Output files:

1. migrations/0007_achievements.sql
   CREATE TABLE bv_achievements (
     id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     user_id BIGINT(20) UNSIGNED NOT NULL,
     code VARCHAR(60) NOT NULL,                 -- 'first_bulk_edit'|'first_rollback'|'first_rule'|'100_variations_edited'|...
     unlocked_at DATETIME NOT NULL,
     meta LONGTEXT,
     PRIMARY KEY (id),
     UNIQUE KEY user_code (user_id, code)
   );

2. src/Onboarding/OnboardingService.php
   - hasCompletedTour(int $user_id): bool — option bv_user_onboarding_{user_id}.
   - markStepComplete(int $user_id, string $step): void.
   - createDemoProduct(): int — only on demand; tags it 'bv-demo'.
   - cleanupDemoProduct(): void.

3. src/Onboarding/PersonaDetector.php
   - detect(): string|null — heuristics on existing store data:
     - 'b2b' if avg order value > 500 OR > 20% of users have wholesale role.
     - 'wholesale' if products use quantity tiered pricing or >50% have minimum order quantity.
     - 'fashion' if many products use color/size attributes.
     - 'electronics' if products use brand/model/capacity attributes.
     - 'generic' otherwise.
   - suggestRules(string $persona): array — returns 3-5 starter rule templates tailored to persona.

4. src/Onboarding/AchievementLog.php
   - unlock(int $user_id, string $code, array $meta=[]): bool — idempotent.
   - list(int $user_id): array.
   - Built-in achievements: first_bulk_edit, first_rollback, first_rule_created, first_rule_fired, first_schedule, first_heatmap_view, first_ai_use, 100_variations_edited, 1000_variations_edited, time_saved_4_hours, time_saved_40_hours, week_streak, month_streak.

5. REST
   - GET  /onboarding                  → { steps_completed[], demo_product_id|null, persona|null }
   - POST /onboarding/complete          body: { step }
   - POST /onboarding/demo/create
   - POST /onboarding/demo/cleanup
   - GET  /onboarding/persona
   - GET  /achievements
   - POST /achievements/dismiss        body: { code }   // hide achievement notification

6. React: views/Onboarding/Tour.jsx
   - 4-step modal that overlays the admin shell (not full-screen).
   - Step 0 (NEW): persona pick. "What kind of store?" 4 cards: B2B · Retail · Fashion/Apparel · Other.
   - Step 1: "Pick a product" — opens Bulk Editor product picker. Or "Use demo product" → POST /onboarding/demo/create.
   - Step 2: "Generate 5 variations with AI" — opens AI panel pre-prompted. If no AI key, uses a stubbed adapter returning canned data so the demo works offline.
   - Step 3: "Bulk-edit price" — toolbar opens with +15% formula prefilled; user clicks → diff opens.
   - Step 4: "Rollback to safety" — surfaces rollback button; clicking reverts and shows snackbar with aha receipt.
   - Step 5 (NEW): "Want a starter rule?" — based on persona, suggests 1 rule the merchant can enable in one click (e.g., for B2B: "Notify me when wholesale variations stock < 5").
   - Progress dots top-right. "Skip tour" bottom-left. Skipping marks all steps complete.

7. React: components/AchievementToast.jsx
   - Small celebratory toast when an achievement unlocks. Confetti SVG (subtle, respects reduced-motion).
   - "View all achievements →" link to Help → Achievements panel.

8. React: components/MiniTour.jsx
   - Reusable inline tour overlay. Activated when first opening Rules, Schedule, Heatmap, AI — each surfaces a 2-step "Here's what this view does" inline tour.

9. React: views/Dashboard.jsx (extend)
   - Persona-aware "Next steps" card after tour completion.
   - After 1 job: "Try this: schedule a recurring CSV import →"
   - After 5 jobs: "Time saved this month" card with aggregated aha receipt.
   - After 10 jobs: "Want auto-pilot rules? See suggestions →" (links to rule suggestions).
   - After 20 jobs: "Ready to invite a teammate?" Pro upgrade nudge.
   - After failed job: "That one didn't go through. Here's what happened" card with fix link.
   - Achievements panel mini-widget: 3 most recent.

10. Help → Achievements panel (extend PROMPT 18 Help.jsx)
    - Grid of all built-in achievements with locked/unlocked state + unlock date + meta.

11. Cleanup
    - Uninstall OR "Remove demo data" button → purges demo product + variations + analytics rows.
    - Skipping cleanup leaves the demo product as a draft.

12. A11y
    - Tour modal keyboard-navigable.
    - Step transitions announce via aria-live.
    - Achievement toast respects reduced-motion (no confetti animation if set).

13. Free / Pro
    - Free: tour + achievements + dashboard cards.
    - Pro: persona detection + persona-tailored rule suggestions + mini-tours for new views.

14. Tests
    - tests/Integration/OnboardingTest.php — completing 5 steps marks tour done.
    - tests/Integration/PersonaDetectorTest.php — seed B2B-like store, assert detect()='b2b'.
    - tests/Integration/AchievementLogTest.php — unlock idempotency.
    - tests/E2E/first-run.spec.ts — fresh activation → tour appears → walking through completes → confetti on first achievement.

Acceptance
- Fresh wp-env activation shows the tour on next admin page load.
- Persona detection returns a non-null value on seeded stores.
- First bulk_edit unlocks 'first_bulk_edit' achievement exactly once.
- Demo product is cleanly removable.
- Dashboard onboarding cards appear at documented thresholds.
- Mini-tours fire once per view per user.
```

**Verify**

```
composer test -- --filter "Onboarding|Persona|Achievement"
npx playwright test first-run.spec.ts
```

---

## PROMPT 34 — Inline Cell History + Micro-Interactions (Features C6, C5)

```
Goal: hover any spreadsheet cell with a history badge → tooltip shows last 5 changes (who/when/from→to). Click → full timeline drawer. Plus the global micro-interaction layer that gives the app its character.

Depends on: PROMPT 14 (Bulk Editor), PROMPT 06 (Rollback / delta source), PROMPT 30 (Activity feed).

Output files:

1. src/Services/CellHistoryService.php
   - getCellHistory(int $variation_id, string $field, int $limit=5): array — joins bv_job_changes + bv_jobs + users.
   - getVariationTimeline(int $variation_id): array — full timeline grouped by job.

2. REST
   - GET /variations/{id}/history?field=_price&limit=5
   - GET /variations/{id}/timeline

3. React: BulkEditor cell renderer extension
   - cellRenderer wraps the default cell. If a cell has any prior change in bv_job_changes, render a tiny dot in the corner (top-right) using --bv-info.
   - On hover (delayed 250ms), shows a Tooltip with last 5 changes.
   - On click of the dot, opens a Drawer with the full variation timeline.

4. React: components/CellHistoryTooltip.jsx
   - List of last 5 changes: actor avatar · "{name} changed from X to Y · 3d ago".
   - Each row links to the source job.

5. React: components/CellHistoryDrawer.jsx
   - Right drawer: variation header (SKU, attributes), then a vertical timeline grouped by job.
   - Each timeline entry: job link · actor · diff of fields touched in that job.
   - "Revert this variation to value at job #X" button (creates a single-row rollback).

6. Micro-interactions layer (assets/admin/admin.css extension)
   - Animation tokens:
     --bv-anim-pulse: 400ms ease-out
     --bv-anim-slide: 250ms cubic-bezier(.2,.8,.2,1)
     --bv-anim-bounce: 200ms cubic-bezier(.34, 1.56, .64, 1)
   - .bv-cell--changed { animation: bvCellPulse var(--bv-anim-pulse); }
       @keyframes bvCellPulse { from { background: var(--bv-primary-soft); } to { background: transparent; } }
   - .bv-row--selected { box-shadow: inset 2px 0 0 var(--bv-primary); transition: box-shadow var(--bv-anim-slide); }
   - .bv-btn--saved { /* checkmark morph */ }
   - .bv-badge--pulse { animation: bvBadgePulse 1.6s infinite ease-in-out; }
   - .bv-prolock__lock:hover { animation: bvShake 80ms ease-in-out; }
   - All animations gated by @media (prefers-reduced-motion: reduce) { animation: none !important; }

7. Apply micro-interactions across views
   - Editor: cell pulse on value change, row slide-in on select.
   - Jobs: badge pulse for running, smooth progress bar transitions (CSS transition on width).
   - Sidebar: jobs badge does a single tick-scale on completion (use Animation API).
   - Topbar: pulsing dot when any job is running (already wired in PROMPT 30).
   - Snackbar: spring slide-up.
   - Modal: backdrop fade + content scale from 0.96 → 1.
   - Pro-lock: lock icon wiggle on hover.

8. Free / Pro
   - Free: cell history shows last 1 change in tooltip; drawer shows last 10 entries.
   - Pro: tooltip shows last 5; drawer is full timeline; per-variation revert button.

9. Tests
   - tests/Unit/Services/CellHistoryServiceTest.php — limits, ordering.
   - tests/E2E/cell-history.spec.ts — make 3 edits, hover cell, verify tooltip rows.

Acceptance
- Hovering a changed cell shows the tooltip within 300ms.
- Clicking the dot opens the drawer with the full timeline.
- "Revert this variation to value at job #X" produces a minimal rollback that touches only that variation/field.
- All animations disabled under prefers-reduced-motion.
```

**Verify**

```
composer test -- --filter "CellHistory"
npx playwright test cell-history.spec.ts
```

---

# Phase H — Release

## PROMPT 35 — WP.org readme.txt, Assets, Release Checklist

```
Goal: everything needed to ship to wordpress.org and Freemius.

Depends on: all previous prompts.

Output files:

1. readme.txt
   - Header: Contributors, Tags, Requires at least, Tested up to, Requires PHP, Stable tag, License.
   - Description — lead with the four uniqueness features (B1 diff, B2 rules, B3 heatmap, B6 margin) + the three premium UX pillars (palette, snackbar-undo, first-run).
   - Installation — 5 steps.
   - FAQ — 5 questions from plan §14 + 3 new ones (rules, heatmap, schedule).
   - Screenshots — 10 entries with captions:
       1. Bulk Editor + Floating Action Menu
       2. Visual Diff & Approval Drawer
       3. Auto-pilot Rules Builder
       4. Performance Heatmap
       5. Profit Margin Column
       6. Schedule & Stage (Calendar view)
       7. Command Palette (⌘K)
       8. Snackbar with Undo
       9. First-Run Tour
       10. Storefront Variation Grid
   - Changelog — 0.1.0 initial → 1.0.0 launch.
   - Upgrade Notice — short per-version.

2. assets/wp-org/
   - banner-772x250.png, banner-1544x500.png (retina).
   - icon-128x128.png, icon-256x256.png.
   - screenshot-1.png ... screenshot-10.png — generated from Playwright at fixed viewport 1280x800.

3. RELEASE.md
   - Steps: bump version in bulk-variations.php and readme.txt, regenerate .pot, tag, composer run build-free, composer run build-pro, upload, verify Freemius dashboard, publish to WP.org svn.

4. .github/workflows/release.yml — on tag push v*: runs build-free + build-pro, uploads zips to GitHub Release.

5. Marketing one-pagers (in docs/marketing/)
   - launch-tweet.md — 8 tweets in a thread.
   - blog-post-1.md — "Why we rebuilt the bulk variations plugin."
   - blog-post-2.md — "Schedule your Black Friday in advance" (PROMPT 29 marketing).
   - reddit-r-woocommerce.md — "Pull requests for your products" introduction.

Acceptance
- readme.txt passes wporg-readme-validator.
- Both zips install cleanly on a vanilla WP + WC.
- All 10 screenshots generated by Playwright are non-empty PNGs.
```

---

## PROMPT 36 — Final Wire & Smoke Test

```
Goal: make sure nothing was missed. Cross-cutting registration and a smoke script that exercises every major capability end-to-end.

Depends on: every previous prompt.

Tasks:

1. ServiceProvider audit
   - Output a Markdown table in your reply listing (class → hook → priority) for every registered hook.
   - Cross-check the FILE LAYOUT in MASTER CONTEXT — flag any class file that wasn't created.

2. Smoke CLI: wp bv smoke
   - Creates a test product with 10 variations.
   - Runs a tiny bulk_edit (synchronous path, < 50 rows).
   - Hits diff drawer (PROMPT 25): asserts review_status flow.
   - Creates a rule that matches 2 of the 10 variations; dry-runs it; verifies match count.
   - Schedules a job for 1 minute in the future; force-fires AS; asserts executed.
   - Records 3 fake events; calls /heatmap; asserts cells returned.
   - Calls /presence/heartbeat from two simulated users; asserts both visible.
   - Triggers an AI suggestion using NullAdapter; asserts canned response.
   - Rolls back the original bulk_edit; asserts reverted.
   - Cleans up: deletes test product, demo events, rules, schedules.
   - Reports ✔ / ✘ per step.

3. Final grep — zero hits for any of:
   - wc_get_product()->save() in loops
   - TODO, FIXME
   - var_dump, print_r without conditional
   - error_log( outside Support\Logger
   - permission_callback => '__return_true'

4. Final Lighthouse + axe run on each admin view; append scores to RELEASE.md.

5. CI matrix — verify the full Playwright suite is green:
   - editor-happy-path, settings, storefront-grid, diff-approval, rules-builder, heatmap, margin-column, schedule, activity-feed, command-palette, snackbar-undo, snackbar-edit, first-run, cell-history, a11y, shared-components.

Acceptance
- wp bv smoke prints ✔ on every step.
- Final grep returns no hits.
- CI green across the full matrix.
- Lighthouse on each view: Performance ≥ 80, Accessibility ≥ 95, Best Practices ≥ 90.
```

**Verify**

```
wp bv smoke
grep -RE "(wc_get_product\(\)->save\(\)|TODO|FIXME|var_dump|permission_callback\s*=>\s*['\"]__return_true['\"])" src/ assets/admin/src/ \
  && echo "FAIL: found banned patterns" || echo "OK"
npx playwright test
```

---

## Appendix A — Recommended Running Order

```
00 → 01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 09 → 10 → 11 → 12 → 13 → 14 → 15 → 16 → 17 → 18 → 19 → 20 → 21 → 22 → 23 → 24 → 25 → 26 → 27 → 28 → 29 → 30 → 31 → 32 → 33 → 34 → 35 → 36
```

### Phased shipping plan

If you want to ship in stages rather than waiting for all 36 prompts:

- **Phase 1 — Foundations + utility (alpha)**: 00 → 24. Plugin works end-to-end as a competent bulk editor. Internal release.
- **Phase 2 — Uniqueness (beta)**: 25 → 30. The four big differentiators + scheduled workflows + activity feed. Closed beta to design partners.
- **Phase 3 — UX premium (RC)**: 31 → 34. Command palette, snackbar+undo, first-run tour, cell history. Release candidate.
- **Phase 4 — Ship**: 35 → 36. WP.org + Freemius launch.

---

## Appendix B — Full Free / Pro split

| Feature | Free | Pro |
|---------|------|-----|
| Storefront variation grid | ✅ | ✅ |
| Basic spreadsheet editor | ✅ (1 product, ≤100 rows) | ✅ unlimited, cross-product |
| Variation generator (3 attrs) | ✅ | ✅ + AI suggest |
| CSV import / export | ✅ (≤100 rows) | ✅ streaming, large files |
| AI assistant | 🔓 3-row teaser | ✅ full (Generate, Suggest Prices, Clean Import, Summarize) |
| Visual diff & approval (B1) | ✅ (jobs > 5 rows) | ✅ + per-row reject + dual-approval |
| Auto-pilot rules (B2) | ✅ (2 active, daily only) | ✅ unlimited + event triggers + AI rule suggest |
| Performance heatmap (B3) | ✅ (7-day window) | ✅ up to 365d + drill-in + PNG + colour-by-margin |
| Profit margin column (B6) | ✅ view only | ✅ AI "Suggest price for target margin" |
| Schedule & stage (B5) | ✅ (single jobs, no revert) | ✅ + auto-revert + recurring + AI suggest |
| Activity feed (B10) | ✅ feed | ✅ + presence + cell conflict warnings |
| Command palette (C1) | ✅ full | ✅ + AI suggestions + cross-site favorites |
| Snackbar + Undo (C2) | ✅ | ✅ + stack-undo + edit-in-toast + action history |
| First-run tour + achievements (C3) | ✅ | ✅ + persona detection + persona-tailored rules + mini-tours |
| Inline cell history (C6) | ✅ (last 1) | ✅ (last 5 + full timeline + per-variation revert) |
| Background processing (10k+) | ❌ | ✅ |
| Job changelog + rollback | ❌ | ✅ |
| REST API | ❌ | ✅ |
| WP-CLI commands | ❌ | ✅ |
| Variation templates | ❌ | ✅ |
| Multi-site support | ❌ | ✅ |
| Priority support | ❌ | ✅ |

---

## Appendix C — If Cursor drifts

- **Combining prompts:** "Stop. Complete PROMPT <N> only. Re-read MASTER CONTEXT rule #10 and the prompt header."
- **Regenerating an existing file:** "Open <path>, make these specific changes:" and list them — avoid "regenerate the file".
- **Dropping the `bv-` prefix or renaming files:** "Revert. MASTER CONTEXT rule #11 is binding."
- **Synchronous bulk write:** "Stop. Rule #3. Route through JobManager."
- **`permission_callback => __return_true`:** "Stop. Rule #1. Every route requires manage_woocommerce + nonce. No exceptions, even for GET endpoints."
- **Looping `wc_get_product()->save()`:** "Stop. Rule #3 + Rule #4. Direct $wpdb batched CASE SQL only, with delta recording first."

---

## Appendix D — How this v2 differs from the original pack

The original `cursor-prompt-pack.md` (now superseded) had 26 prompts covering foundations + admin shell + AI + storefront + licensing + compliance/QA.

v2 adds:
- **PROMPT 00** — porting catalogue from the old bulk-variations-pro codebase.
- **PROMPT 25–30** — the six uniqueness features (Diff B1, Rules B2, Heatmap B3, Margin B6, Schedule B5, Activity B10).
- **PROMPT 31–34** — the four UX-premium prompts (Palette C1, Snackbar+Undo C2, First-Run+Personas C3, Cell-History C6).
- Promoted existing prompts: PROMPT 25 (readme.txt) became PROMPT 35; PROMPT 26 (smoke test) became PROMPT 36.
- MASTER CONTEXT updated with the new rules (#13 diff, #14 snackbar/undo, #15 commands).
- File layout extended with new dirs: Diff/, Rules/, Schedule/, Analytics/, Activity/, Onboarding/, plus the views/Heatmap, views/Rules, views/Schedule, views/Onboarding under assets/admin/src/components/.
- Free/Pro feature split table extended to include every new feature.

*Document prepared for: Cursor AI development + developer reference.*
*Plugin: Bulk Variations Add & Edit Product.*
*Version: Cursor Prompt Pack v2 — May 2026.*
