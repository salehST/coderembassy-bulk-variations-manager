# Bulk Variations Add & Edit Product
## Complete Plugin Development Plan — Free & Pro Editions
### For Cursor AI & Developer Reference

> **Plugin Name:** Bulk Variations Add & Edit Product  
> **Target:** WordPress 6.x + WooCommerce 7.x+  
> **Namespace:** `BulkVariations\`  
> **License Platform:** Freemius  
> **Version:** Plan v1.0 — March 2026

---

## Table of Contents

1. [Executive Summary & Market Opportunity](#1-executive-summary--market-opportunity)
2. [Competitive Analysis](#2-competitive-analysis)
3. [Feature Plan — Free vs Pro](#3-feature-plan--free-vs-pro)
4. [Technical Architecture](#4-technical-architecture)
5. [Database Schema](#5-database-schema)
6. [PHP Classes & Interfaces](#6-php-classes--interfaces)
7. [REST API Design](#7-rest-api-design)
8. [Admin UI Specification](#8-admin-ui-specification)
9. [AI Assistant Design](#9-ai-assistant-design)
10. [Performance Strategy](#10-performance-strategy)
11. [Licensing & Monetisation](#11-licensing--monetisation)
12. [Cursor Prompt Pack](#12-cursor-prompt-pack)
13. [Release Roadmap](#13-release-roadmap)
14. [Marketing Copy & Pricing](#14-marketing-copy--pricing)
15. [Testing Plan](#15-testing-plan)

---

## 1. Executive Summary & Market Opportunity

### The Gap Nobody Has Filled

No single WooCommerce plugin on the market today combines:

- A **frontend variation grid** (for customers to bulk-order)
- **Cross-product backend editing** (for admins to edit many products at once)
- **Scheduled background imports**
- **CSV import/export**
- **Undo/history + rollback**

This is the product opportunity. The market is fragmented. Build the one plugin that does everything.

### Why Now

- **Barn2** (market leader, ~$79/yr) is frontend-only and locks advanced features behind ecosystem upsells
- **VBULKiT** (closest backend competitor) has ~100 installs, no reviews, poor support
- **No competitor** offers all five capabilities above in one plugin
- WooCommerce has 6.5M+ active installs — even 0.1% penetration = 6,500 customers

### Business Model Target

| Metric | Year 1 Target |
|--------|--------------|
| Free installs (WordPress.org) | 2,000+ |
| Pro conversions (2–3%) | 50–60 customers |
| Revenue @ $79 avg | ~$4,000–5,000 |
| Year 2 (Agency tier + renewals) | $20,000–40,000 |

---

## 2. Competitive Analysis

### Barn2 — WooCommerce Bulk Variations

**Price:** $79/yr single site · $299 lifetime  
**Strengths:**
- Best-in-class frontend variation grid/order form
- Price matrix for wholesale catalogs
- WPML and ecosystem integrations
- 3+ attributes (extras become dropdowns)
- Responsive, shortcode placement

**Critical Weaknesses (exploit these):**
- Edits ONE product at a time only — no cross-product bulk editing
- Zero CSV import/export
- Zero undo/history or rollback
- No AI assistance
- No scheduled imports or automation
- Advanced features require buying MORE Barn2 plugins (lock-in tax)
- Backend editing uses the native WooCommerce editor — slow and clunky

### VBULKiT (iThemeland)

**Price:** ~$49–69/yr (estimated)  
**Strengths:** Cross-product spreadsheet editing, Pro has scheduled updates, undo/history, CSV  
**Weaknesses:** ~100 active installs, zero WordPress.org reviews, support complaints, no frontend display

### Plugin Republic — Better Variations

**Price:** $69/yr  
**Strengths:** Shows variations as individual products on shop pages, great out-of-stock management  
**Weaknesses:** Frontend-only, no backend bulk editing, limited to 1–2 attributes in grid

### PW WooCommerce Bulk Edit (Pimwick)

**Price:** Free + Pro  
**Strengths:** 234+ WordPress.org reviews, battle-tested general bulk editor, inline editing  
**Weaknesses:** Not variation-display focused, zero frontend grid

### Our Competitive Position

```
                    FRONTEND GRID
                         ↑
                         │
    Plugin Republic      │      ★ OUR PLUGIN ★
    Barn2                │      (all quadrants)
                         │
    ←────────────────────┼────────────────────→
    ADMIN-ONLY           │           ADMIN + FRONT
                         │
    PW Bulk Edit         │      VBULKiT (weak execution)
                         │
                         ↓
                    ADMIN ONLY
```

**We own the top-right quadrant. Nobody else does.**

---

## 3. Feature Plan — Free vs Pro

### Free Edition (WordPress.org)

| Feature | Details |
|---------|---------|
| Storefront variation grid | Responsive grid, qty inputs, bulk add-to-cart |
| Basic spreadsheet editor | Edit price & stock for 1 product, up to 100 variations |
| Variation generator | Generate combinations from attributes (up to 3 attributes) |
| Basic bulk actions | Increase/decrease price %, set stock for selected variations |
| CSV import (small) | Up to 100 rows, preview before import |
| CSV export | Export current product variations |
| SKU pattern generator | Basic `{product}-{attr1}-{attr2}` pattern |
| Developer hooks | Filters & actions for extensibility |
| AI preview (teaser) | Generate 3 sample variations via AI (rate-limited) |

### Pro Edition

| Feature | Details |
|---------|---------|
| Everything in Free | + all below |
| Full spreadsheet editor | Cross-product editing, keyboard nav, copy/paste, formulas |
| Unlimited variations | No row limits |
| AI assistant (full) | Generate, price-suggest, clean imports, natural language |
| Scheduled imports | Cron-based + Action Scheduler, email reports |
| Background job processing | Chunked workers, no timeouts for 10k+ variations |
| Job changelog + rollback | Full delta storage, revert any job |
| CSV/JSON import + export | Streaming for large files |
| Advanced bulk formulas | `=PRICE*1.15`, range operations |
| Variation templates | Save and apply templates across products |
| REST API | Full endpoints for automation |
| WP-CLI commands | Headless/scripted workflows |
| Multi-site support | Network-level management |
| Role-based access | Custom caps, per-role feature restrictions |
| Priority support | Direct support queue |

---

## 4. Technical Architecture

### Stack Decisions (Reasoned)

| Decision | Choice | Why |
|----------|--------|-----|
| Autoloading | Composer PSR-4 | Industry standard, testable |
| DI Container | `lucatume/di52` | Used by The Events Calendar, WordPress-native |
| Namespace prefixing | Strauss | Prevents dependency conflicts in distribution |
| Admin grid UI | AG Grid Community (MIT) | Free, 100k+ row virtualization, native React |
| Background jobs | Action Scheduler | Bundled with WooCommerce, 10k+ actions/hr |
| Bulk DB writes | Direct `$wpdb->query()` SQL | 60× faster than WC CRUD objects in loops |
| Testing | PHPUnit + Brain Monkey + WP_UnitTestCase | Fast unit + real integration tests |
| Licensing | Freemius | Freemium support, in-dashboard checkout, MoR |

### Why NOT Handsontable

Handsontable has the best Excel-like UX but costs ~$881+/developer/year for commercial use. It cannot be bundled in a distributed WordPress plugin economically. **AG Grid Community (MIT, free) with React provides 95% of the same capability at zero cost.**

### Directory Structure

```
bulk-variations-add-edit-product/
│
├── bulk-variations.php              # Plugin bootstrap
├── composer.json                    # PSR-4 autoload + dependencies
├── composer-strauss.json            # Strauss namespace prefixing config
├── package.json                     # JS build config
├── webpack.config.js                # AG Grid + React build
├── readme.txt                       # WordPress.org readme
├── uninstall.php                    # Clean uninstall
│
├── src/                             # PHP source (BulkVariations\ namespace)
│   ├── Plugin.php                   # Main plugin class (singleton)
│   ├── ServiceProvider.php          # DI container registrations
│   │
│   ├── Contracts/                   # Interfaces (always code to these)
│   │   ├── JobRepositoryInterface.php
│   │   ├── VariationServiceInterface.php
│   │   ├── ImporterInterface.php
│   │   └── AiAdapterInterface.php
│   │
│   ├── Admin/                       # WP admin integration
│   │   ├── AdminController.php
│   │   ├── Pages/
│   │   │   ├── BulkEditorPage.php
│   │   │   ├── JobsPage.php
│   │   │   └── SettingsPage.php
│   │   └── Assets/
│   │       └── AdminAssets.php
│   │
│   ├── Engine/                      # Core business logic
│   │   ├── VariationGenerator.php   # Cartesian product generator
│   │   ├── BulkEditor.php           # Bulk price/stock/sku editor
│   │   ├── AttributeMatrix.php      # Attribute combination logic
│   │   └── VariationRepository.php  # Product/variation data access
│   │
│   ├── Services/                    # Standalone services
│   │   ├── SKUGenerator.php         # Pattern-based SKU generation
│   │   ├── PriceCalculator.php      # Formula evaluation
│   │   ├── JobService.php           # Job lifecycle management
│   │   └── HistoryLogger.php        # Change delta recording
│   │
│   ├── ImportExport/
│   │   ├── CsvImporter.php
│   │   ├── CsvExporter.php
│   │   └── ImportValidator.php
│   │
│   ├── Jobs/                        # Action Scheduler workers
│   │   ├── JobManager.php
│   │   ├── BulkUpdateJob.php
│   │   └── ImportJob.php
│   │
│   ├── Rollback/
│   │   └── RollbackService.php
│   │
│   ├── AI/
│   │   ├── AiAssistantService.php
│   │   ├── Adapters/
│   │   │   ├── OpenAiAdapter.php    # Default adapter
│   │   │   └── NullAdapter.php      # Stub for Free tier
│   │   └── PromptLibrary.php        # Server-side prompt templates
│   │
│   ├── REST/
│   │   └── RestController.php
│   │
│   ├── Frontend/
│   │   └── VariationGrid.php        # Storefront grid shortcode
│   │
│   ├── Updater/
│   │   └── MigrationRunner.php
│   │
│   └── Licensing/                   # Freemius integration
│       └── LicenseManager.php
│
├── admin/                           # React admin app
│   ├── src/
│   │   ├── index.jsx                # Entry point
│   │   └── components/
│   │       ├── SpreadsheetEditor/
│   │       │   ├── index.jsx        # AG Grid wrapper
│   │       │   ├── columns.js       # Column definitions
│   │       │   ├── cellEditors.jsx  # Custom cell editors
│   │       │   └── toolbar.jsx      # Bulk action toolbar
│   │       ├── AiAssistantPanel/
│   │       │   ├── index.jsx
│   │       │   └── tabs/
│   │       │       ├── Generate.jsx
│   │       │       ├── SuggestPrices.jsx
│   │       │       └── CleanImport.jsx
│   │       ├── JobsPage/
│   │       │   ├── index.jsx
│   │       │   └── JobRow.jsx
│   │       └── shared/
│   │           ├── ProgressBar.jsx
│   │           └── ConfirmModal.jsx
│   └── build/                       # Compiled assets (gitignored)
│
├── assets/
│   ├── js/
│   │   └── frontend-grid.js         # Storefront variation grid (vanilla JS)
│   └── css/
│       ├── admin.css
│       └── frontend.css
│
├── migrations/
│   ├── 0001_create_bv_tables.sql
│   └── 0002_add_ai_request_log.sql
│
├── templates/
│   ├── admin/
│   │   ├── bulk-editor.php
│   │   └── settings.php
│   └── frontend/
│       └── variation-grid.php
│
└── tests/
    ├── Unit/
    │   ├── Engine/VariationGeneratorTest.php
    │   ├── Services/SKUGeneratorTest.php
    │   └── Services/PriceCalculatorTest.php
    └── Integration/
        ├── ImportPipelineTest.php
        └── RollbackTest.php
```

---

## 5. Database Schema

### Migration SQL — `migrations/0001_create_bv_tables.sql`

```sql
-- Job tracking table
CREATE TABLE IF NOT EXISTS `{prefix}bv_jobs` (
  `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`         VARCHAR(60)  NOT NULL COMMENT 'bulk_edit|import|generate|ai_suggest',
  `meta`         LONGTEXT     DEFAULT NULL COMMENT 'JSON: job parameters',
  `status`       VARCHAR(30)  NOT NULL DEFAULT 'queued' COMMENT 'queued|running|complete|failed|rolled_back',
  `progress`     SMALLINT     NOT NULL DEFAULT 0 COMMENT '0-100 percentage',
  `total_items`  INT          NOT NULL DEFAULT 0,
  `processed`    INT          NOT NULL DEFAULT 0,
  `created_by`   BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL,
  `started_at`   DATETIME     DEFAULT NULL,
  `completed_at` DATETIME     DEFAULT NULL,
  `error_log`    LONGTEXT     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status_idx` (`status`),
  KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-row change deltas for rollback
CREATE TABLE IF NOT EXISTS `{prefix}bv_job_changes` (
  `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id`      BIGINT(20) UNSIGNED NOT NULL,
  `object_type` VARCHAR(60) NOT NULL COMMENT 'variation|product',
  `object_id`   BIGINT(20) UNSIGNED NOT NULL,
  `field`       VARCHAR(100) NOT NULL COMMENT '_price|_stock|_sku|_description',
  `old_value`   LONGTEXT DEFAULT NULL,
  `new_value`   LONGTEXT DEFAULT NULL,
  `applied_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_id_idx` (`job_id`),
  KEY `object_idx` (`object_type`, `object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved variation templates
CREATE TABLE IF NOT EXISTS `{prefix}bv_templates` (
  `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `author_id`     BIGINT(20) UNSIGNED DEFAULT NULL,
  `template_json` LONGTEXT NOT NULL COMMENT 'JSON: attributes, SKU pattern, defaults',
  `created_at`    DATETIME NOT NULL,
  `updated_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `author_idx` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI request/response cache + audit log
CREATE TABLE IF NOT EXISTS `{prefix}bv_ai_log` (
  `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id`       BIGINT(20) UNSIGNED DEFAULT NULL,
  `prompt_hash`  VARCHAR(64) NOT NULL COMMENT 'SHA256 of sanitised prompt for cache key',
  `prompt_type`  VARCHAR(60) NOT NULL COMMENT 'generate|suggest_price|clean_import',
  `status`       VARCHAR(30) NOT NULL DEFAULT 'pending',
  `tokens_used`  INT DEFAULT 0,
  `accepted`     TINYINT(1) DEFAULT 0 COMMENT '1 if merchant approved suggestion',
  `created_at`   DATETIME NOT NULL,
  `expires_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hash_idx` (`prompt_hash`),
  KEY `job_idx` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. PHP Classes & Interfaces

### Core Interfaces (`src/Contracts/`)

```php
<?php
// src/Contracts/VariationServiceInterface.php
namespace BulkVariations\Contracts;

interface VariationServiceInterface {
    /**
     * Bulk update variation fields in batches.
     * MUST use direct SQL + Action Scheduler — never WC CRUD in a loop.
     *
     * @param array    $updates   [ ['variation_id'=>int, 'price'=>float, 'stock'=>int, ...], ... ]
     * @param int      $job_id    Associated job ID for delta logging
     * @param callable $progress  Optional progress callback ($processed, $total)
     * @return array   [ 'processed'=>int, 'errors'=>array ]
     */
    public function bulkUpdate(array $updates, int $job_id, callable $progress = null): array;

    /**
     * Generate all attribute combinations (Cartesian product).
     *
     * @param int   $product_id
     * @param array $attributes  [ 'pa_color' => ['Red','Blue'], 'pa_size' => ['S','M','L'] ]
     * @return array  Array of variation definition arrays
     */
    public function generateCombinations(int $product_id, array $attributes): array;
}
```

```php
<?php
// src/Contracts/JobRepositoryInterface.php
namespace BulkVariations\Contracts;

interface JobRepositoryInterface {
    public function create(array $meta): int;
    public function get(int $id): ?array;
    public function updateStatus(int $id, string $status, array $extra = []): bool;
    public function addChange(int $job_id, array $delta): bool;
    public function getChanges(int $job_id): array;
    public function listJobs(array $filters = [], int $limit = 20, int $offset = 0): array;
}
```

```php
<?php
// src/Contracts/AiAdapterInterface.php
namespace BulkVariations\Contracts;

interface AiAdapterInterface {
    /**
     * Send a prompt and get a structured response.
     * Adapter handles provider-specific formatting.
     *
     * @param string $prompt_type  e.g. 'generate_variations'
     * @param array  $context      Sanitised data (no PII)
     * @return array  [ 'success'=>bool, 'data'=>mixed, 'tokens'=>int, 'error'=>string|null ]
     */
    public function request(string $prompt_type, array $context): array;

    public function isConfigured(): bool;
}
```

### VariationService Implementation (Performance-Correct)

```php
<?php
// src/Engine/BulkEditor.php
namespace BulkVariations\Engine;

use BulkVariations\Contracts\VariationServiceInterface;
use BulkVariations\Contracts\JobRepositoryInterface;

class BulkEditor implements VariationServiceInterface {

    private const CHUNK_SIZE = 300; // Variations per Action Scheduler batch

    public function __construct(
        private JobRepositoryInterface $jobRepo,
        private int $chunk_size = self::CHUNK_SIZE
    ) {}

    /**
     * CRITICAL: Use direct SQL — NOT wc_get_product()->save() in a loop.
     * wc_get_product()->save() for 10k rows = 30+ minutes.
     * Direct $wpdb->query() with batched SQL = ~2 minutes for 10k rows.
     */
    public function bulkUpdate(array $updates, int $job_id, callable $progress = null): array {
        global $wpdb;

        $chunks    = array_chunk($updates, $this->chunk_size);
        $processed = 0;
        $errors    = [];

        foreach ($chunks as $chunk) {
            // Build a single batched UPDATE using CASE statements
            $price_cases  = '';
            $stock_cases  = '';
            $ids          = [];

            foreach ($chunk as $row) {
                $id            = (int) $row['variation_id'];
                $ids[]         = $id;

                // Record delta BEFORE writing (for rollback)
                $this->recordDelta($job_id, $id, $row);

                if (isset($row['price'])) {
                    $price = floatval($row['price']);
                    $price_cases .= $wpdb->prepare(" WHEN %d THEN %f", $id, $price);
                }
                if (isset($row['stock'])) {
                    $stock = intval($row['stock']);
                    $stock_cases .= $wpdb->prepare(" WHEN %d THEN %d", $id, $stock);
                }
            }

            $id_list = implode(',', $ids);

            wc_transaction_query('start');

            if ($price_cases) {
                $wpdb->query(
                    "UPDATE {$wpdb->postmeta}
                     SET meta_value = CASE post_id {$price_cases} END
                     WHERE post_id IN ({$id_list})
                     AND meta_key IN ('_price','_regular_price')"
                );
            }

            if ($stock_cases) {
                $wpdb->query(
                    "UPDATE {$wpdb->postmeta}
                     SET meta_value = CASE post_id {$stock_cases} END
                     WHERE post_id IN ({$id_list})
                     AND meta_key = '_stock'"
                );
            }

            wc_transaction_query('commit');

            $processed += count($chunk);
            if ($progress) $progress($processed, count($updates));
        }

        // CRITICAL: Post-write cleanup — must not be skipped
        wp_cache_flush();
        wc_delete_product_transients();
        // Update WooCommerce lookup tables so search/filter works
        WC_Product_Variable::sync_with_children( /* affected parent IDs */ );

        return ['processed' => $processed, 'errors' => $errors];
    }

    public function generateCombinations(int $product_id, array $attributes): array {
        // Cartesian product
        $result = [[]];
        foreach ($attributes as $attribute => $values) {
            $append = [];
            foreach ($result as $existing) {
                foreach ($values as $value) {
                    $existing[$attribute] = $value;
                    $append[] = $existing;
                }
            }
            $result = $append;
        }
        return $result;
    }

    private function recordDelta(int $job_id, int $variation_id, array $new_values): void {
        global $wpdb;
        $field_map = ['price' => '_price', 'stock' => '_stock', 'sku' => '_sku'];
        foreach ($field_map as $key => $meta_key) {
            if (!isset($new_values[$key])) continue;
            $old = get_post_meta($variation_id, $meta_key, true);
            $this->jobRepo->addChange($job_id, [
                'object_type' => 'variation',
                'object_id'   => $variation_id,
                'field'       => $meta_key,
                'old_value'   => json_encode($old),
                'new_value'   => json_encode($new_values[$key]),
                'applied_at'  => current_time('mysql'),
            ]);
        }
    }
}
```

### JobManager (Action Scheduler Integration)

```php
<?php
// src/Jobs/JobManager.php
namespace BulkVariations\Jobs;

use BulkVariations\Contracts\JobRepositoryInterface;

class JobManager {

    private const ACTION_HOOK   = 'bv_process_job_chunk';
    private const CHUNK_DELAY   = 5; // seconds between batches

    public function __construct(private JobRepositoryInterface $repo) {
        add_action(self::ACTION_HOOK, [$this, 'processChunk'], 10, 2);
    }

    /**
     * Schedule the first chunk of a bulk job.
     * MUST be called after init (Action Scheduler requires init to be complete).
     */
    public function dispatch(int $job_id, array $chunks): void {
        // Store all chunks in options for retrieval by each batch worker
        update_option("bv_job_{$job_id}_chunks", $chunks, false);
        update_option("bv_job_{$job_id}_total", count($chunks), false);

        // Schedule first chunk immediately
        as_enqueue_async_action(self::ACTION_HOOK, [$job_id, 0], 'bulk-variations');
        $this->repo->updateStatus($job_id, 'running');
    }

    public function processChunk(int $job_id, int $chunk_index): void {
        $chunks = get_option("bv_job_{$job_id}_chunks", []);
        $total  = get_option("bv_job_{$job_id}_total", 0);

        if (empty($chunks[$chunk_index])) {
            $this->repo->updateStatus($job_id, 'complete', ['completed_at' => current_time('mysql')]);
            delete_option("bv_job_{$job_id}_chunks");
            delete_option("bv_job_{$job_id}_total");
            return;
        }

        // Suspend object cache invalidation during bulk writes
        wp_suspend_cache_invalidation(true);

        // Process the chunk (BulkEditor handles actual SQL)
        do_action('bv_process_chunk', $job_id, $chunks[$chunk_index]);

        wp_suspend_cache_invalidation(false);

        // Update progress
        $progress = round((($chunk_index + 1) / $total) * 100);
        $this->repo->updateStatus($job_id, 'running', ['progress' => $progress]);

        // Schedule next chunk with a small delay
        $next = $chunk_index + 1;
        if ($next < $total) {
            as_schedule_single_action(
                time() + self::CHUNK_DELAY,
                self::ACTION_HOOK,
                [$job_id, $next],
                'bulk-variations'
            );
        } else {
            $this->repo->updateStatus($job_id, 'complete', [
                'completed_at' => current_time('mysql'),
                'progress'     => 100,
            ]);
            // Flush caches & rebuild lookup tables after all chunks complete
            wp_cache_flush();
            wc_delete_product_transients();
        }
    }
}
```

### RollbackService

```php
<?php
// src/Rollback/RollbackService.php
namespace BulkVariations\Rollback;

use BulkVariations\Contracts\JobRepositoryInterface;

class RollbackService {

    public function __construct(private JobRepositoryInterface $repo) {}

    /**
     * Rollback a completed job by replaying inverse deltas.
     * For large jobs this schedules a background rollback job.
     */
    public function rollback(int $job_id): int {
        $changes = $this->repo->getChanges($job_id);

        if (empty($changes)) {
            throw new \RuntimeException("No changes found for job #{$job_id}");
        }

        // Create a rollback job
        $rollback_job_id = $this->repo->create([
            'type'         => 'rollback',
            'source_job'   => $job_id,
            'total_items'  => count($changes),
            'created_at'   => current_time('mysql'),
        ]);

        // Store inverse deltas: swap old_value and new_value
        $inverse = array_map(fn($c) => array_merge($c, [
            'old_value' => $c['new_value'],
            'new_value' => $c['old_value'],
        ]), $changes);

        update_option("bv_rollback_{$rollback_job_id}", $inverse, false);

        // Schedule as background job
        as_enqueue_async_action('bv_process_job_chunk', [$rollback_job_id, 0], 'bulk-variations');

        return $rollback_job_id;
    }

    /**
     * Preview what a rollback would change (dry-run — no DB writes).
     */
    public function preview(int $job_id): array {
        $changes = $this->repo->getChanges($job_id);
        return array_map(fn($c) => [
            'variation_id' => $c['object_id'],
            'field'        => $c['field'],
            'will_revert_to' => $c['old_value'],
            'current_value'  => $c['new_value'],
        ], $changes);
    }
}
```

### HPOS Compatibility Declaration

```php
<?php
// In bulk-variations.php — REQUIRED for WooCommerce compatibility badge
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', // Feature slug — NOT 'hpos'
            __FILE__,
            true
        );
    }
});
```

---

## 7. REST API Design

### Base URL: `/wp-json/bv/v1/`

All endpoints require `manage_woocommerce` capability + nonce verification.

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/jobs` | Create a bulk job (edit/import/generate) | `manage_woocommerce` |
| GET | `/jobs` | List jobs with status/filters | `manage_woocommerce` |
| GET | `/jobs/{id}` | Get job status + progress | `manage_woocommerce` |
| POST | `/jobs/{id}/apply` | Apply an AI suggestion job | `manage_woocommerce` |
| POST | `/jobs/{id}/rollback` | Enqueue rollback for a job | `manage_woocommerce` |
| GET | `/jobs/{id}/rollback/preview` | Dry-run preview of rollback | `manage_woocommerce` |
| GET | `/variations` | List variations for a product | `manage_woocommerce` |
| POST | `/variations/generate` | Generate combinations preview | `manage_woocommerce` |
| POST | `/ai/suggest` | Get AI suggestion (async — returns job_id) | `manage_woocommerce` |
| GET | `/ai/suggest/{job_id}` | Poll AI suggestion status | `manage_woocommerce` |

### Example Request/Response

```json
// POST /wp-json/bv/v1/jobs
// Body:
{
  "type": "bulk_edit",
  "product_id": 123,
  "updates": [
    { "variation_id": 456, "price": 24.99, "stock": 50 },
    { "variation_id": 457, "price": 27.99, "stock": 30 }
  ]
}

// Response:
{
  "success": true,
  "job_id": 42,
  "status": "queued",
  "message": "Job queued. 2 variations will be updated in the background."
}
```

---

## 8. Admin UI Specification

### Spreadsheet Editor — AG Grid Implementation

**Why AG Grid Community:** MIT license (free for commercial use), 100k+ row virtualization, native React components for cell editors/renderers, no licensing fees.

**Key Grid Features:**

| Feature | Implementation |
|---------|---------------|
| Cell editing | `editable: true` on column defs + custom `cellEditor` components |
| Arrow key nav | AG Grid default — works out of the box |
| Copy/paste (Excel-compatible) | `enableRangeSelection + enableClipboard` (Community supports basic copy) |
| Multi-row selection | `rowSelection: 'multiple'` with checkboxes |
| Column hide/show | `columnState` saved to localStorage |
| Sort + filter | `sortable: true, filter: true` per column |
| Undo/redo (session) | Custom `useUndoRedo` hook with state stack |
| Bulk action toolbar | Custom React component above grid |
| Formula support | Custom `cellValueParser` — evaluate `=PRICE*1.15` expressions |
| Save as template | Button → POST to `/wp-json/bv/v1/templates` |

**Column Definitions (core):**

```javascript
const columnDefs = [
  { field: 'variation_id',    headerName: 'ID',    width: 80,  editable: false },
  { field: 'sku',             headerName: 'SKU',   width: 180, editable: true },
  { field: 'pa_color',        headerName: 'Color', width: 120, editable: true,
    cellEditor: 'agSelectCellEditor',
    cellEditorParams: { values: colors }  },
  { field: 'pa_size',         headerName: 'Size',  width: 100, editable: true },
  { field: 'regular_price',   headerName: 'Price', width: 120, editable: true,
    valueParser: params => parseFormula(params.newValue),
    cellRenderer: 'priceCellRenderer' },
  { field: 'stock_quantity',  headerName: 'Stock', width: 100, editable: true,
    valueParser: params => parseInt(params.newValue) },
  { field: 'status',          headerName: 'Status',width: 120, editable: true,
    cellEditor: 'agSelectCellEditor',
    cellEditorParams: { values: ['publish','private'] } },
];
```

### AI Assistant Panel — UX Flow

```
┌─────────────────────────────────────────┐
│  AI Assistant                    [×]    │
├─────────────────────────────────────────┤
│  [Generate] [Prices] [Clean] [Summarize]│
├─────────────────────────────────────────┤
│  Describe what you want:                │
│  ┌─────────────────────────────────┐   │
│  │ Create sizes S-XXL, colors red  │   │
│  │ blue. Increase XL price 10%.    │   │
│  └─────────────────────────────────┘   │
│  [ API: ● Connected ] [ Generate ]      │
├─────────────────────────────────────────┤
│  Preview (8 variations)                 │
│  ─────────────────────                  │
│  SKU: SHIRT-{SIZE}-{COLOR}    ✓         │
│  Price rules applied          ✓         │
│  Stock default: 20            ✓         │
│                                         │
│  [  Apply as Job  ]  [Edit]  [Cancel]   │
│  ⚠ Admin approval required              │
└─────────────────────────────────────────┘
```

**CRITICAL UX RULE:** AI never writes to the database automatically. Every AI action creates a preview/suggestion job. The admin must click **"Apply as Job"** to trigger the actual write.

### Jobs Page

Lists all jobs with: ID, type, status, created by, created at, processed/total, actions (View Log, Rollback, Re-run). Rollback button shown only for completed jobs within the retention window (default 30 days).

---

## 9. AI Assistant Design

### Principles (Non-Negotiable)

1. **AI suggests only — never auto-writes** to the database
2. **Provider-agnostic** — OpenAI adapter by default, BYO-key supported
3. **Strip PII** before any external call (no customer names, emails, order data)
4. **Cache by input hash** — avoid redundant API calls
5. **Quota per site** — prevent cost runaway

### AiAssistantService

```php
<?php
// src/AI/AiAssistantService.php
namespace BulkVariations\AI;

use BulkVariations\Contracts\AiAdapterInterface;

class AiAssistantService {

    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(private AiAdapterInterface $adapter) {}

    public function generateVariations(array $context): array {
        if (!$this->adapter->isConfigured()) {
            return ['success' => false, 'error' => 'AI not configured. Add API key in Settings.'];
        }
        $sanitized   = $this->sanitize($context);
        $cache_key   = 'bv_ai_' . hash('sha256', json_encode($sanitized));
        $cached      = get_transient($cache_key);

        if ($cached !== false) return $cached;

        $result = $this->adapter->request('generate_variations', $sanitized);

        if ($result['success']) {
            set_transient($cache_key, $result, self::CACHE_TTL);
        }
        return $result;
    }

    /** Remove any potentially sensitive data before sending to external API */
    private function sanitize(array $context): array {
        $allowed = ['product_title', 'attributes', 'base_price', 'sku_prefix', 'rules'];
        return array_intersect_key($context, array_flip($allowed));
    }
}
```

### Prompt Templates (Server-Side — `src/AI/PromptLibrary.php`)

```php
public static function get(string $type, array $vars): string {
    return match($type) {

        'generate_variations' => "You are a WooCommerce assistant. Return ONLY valid JSON, no commentary.
Input:
  product_title: \"{$vars['product_title']}\"
  attributes: {$vars['attributes_json']}
  base_price: {$vars['base_price']}
  sku_prefix: {$vars['sku_prefix']}
Rules: {$vars['rules_text']}
Output format: [{\"sku\":\"\",\"attributes\":{},\"price\":0.00,\"stock\":20,\"title\":\"\"}]",

        'suggest_prices' => "You are a pricing assistant. Return ONLY valid JSON.
For each variation, suggest a selling price and a one-sentence reason.
Input: {$vars['variations_json']}
Output format: [{\"sku\":\"\",\"suggested_price\":0.00,\"reason\":\"\"}]",

        'clean_import' => "Validate and normalise these CSV rows. Return ONLY valid JSON.
Rules: price > 0, sku alphanumeric+dash, stock integer, no duplicate SKUs.
Input: {$vars['rows_json']}
Output format: [{\"index\":0,\"is_valid\":true,\"fixed_row\":{},\"issues\":[]}]",

        default => throw new \InvalidArgumentException("Unknown prompt type: {$type}")
    };
}
```

---

## 10. Performance Strategy

### The Core Problem

- `wc_get_product()->save()` for 10,000 rows = **30–60 minutes** (fires all hooks, object hydration, individual cache invalidation)
- Direct `$wpdb->query()` with batched SQL = **2–5 minutes** for 10,000 rows (**60× faster**)

### Architecture for Scale

```
User triggers bulk action
         ↓
API validates + sanitizes inputs
         ↓
JobManager splits into chunks of 300 variations
         ↓
Stores chunk data in wp_options
         ↓
as_enqueue_async_action() → first chunk
         ↓
Action Scheduler processes chunk:
  1. wp_suspend_cache_invalidation(true)
  2. Record deltas (for rollback)
  3. Batched CASE-based UPDATE SQL (inside wc_transaction_query)
  4. wp_suspend_cache_invalidation(false)
  5. Schedule next chunk (time() + 5 seconds)
         ↓
Final chunk:
  1. wp_cache_flush()
  2. wc_delete_product_transients()
  3. WC_Product_Variable::sync_with_children()
  4. wc_update_product_lookup_tables()
  5. Job marked 'complete'
```

### Benchmark Targets

| Operation | Row Count | Target Time |
|-----------|-----------|-------------|
| Bulk price update | 1,000 variations | < 30 seconds |
| Bulk price update | 10,000 variations | < 5 minutes |
| CSV import | 5,000 rows | < 3 minutes |
| Variation generate | 500 combinations | < 10 seconds |
| Rollback (1,000 rows) | 1,000 deltas | < 60 seconds |

---

## 11. Licensing & Monetisation

### Platform: Freemius

**Why Freemius over alternatives:**

| Platform | Fee | Freemium Support | MoR (Tax Handling) | WP-Native |
|----------|-----|------------------|--------------------|-----------|
| **Freemius** | ~7% + 3.5% gateway | ✅ Auto code-strip | ✅ Full | ✅ In-dashboard checkout |
| EDD + Software Licensing | $299–599/yr flat | ❌ Manual | ❌ You handle tax | ❌ External |
| WooCommerce.com | 30% | ❌ | ✅ | ✅ (their dashboard) |
| Lemon Squeezy | 5% + $0.50 + extras | ❌ | ✅ | ❌ Middleware plugin needed |

**Freemius key features for this plugin:**
- Auto-strips Pro code to generate a clean WordPress.org free version from a single codebase
- In-dashboard upgrade checkout (reportedly +12% conversion vs external checkout)
- Cart abandonment recovery (~7.5–10% revenue recovered automatically)
- Full EU VAT, US sales tax handling

### Suggested Pricing

| Plan | Price | Sites |
|------|-------|-------|
| Free | $0 | 1 |
| Pro — Starter | $79/year | 1 |
| Pro — Business | $149/year | 5 |
| Pro — Agency | $399/year | Unlimited |
| Lifetime (single) | $299 one-time | 1 |
| Lifetime (agency) | $999 one-time | Unlimited |

---

## 12. Cursor Prompt Pack

Use each prompt as a **separate Cursor task**. Run them in sequence or assign to parallel workers. Each prompt includes expected output files.

---

### MASTER PROMPT — Project Context (Paste First)

```
You are a senior WordPress/WooCommerce plugin engineer. We are building a commercial plugin called "Bulk Variations Add & Edit Product".

STACK:
- PHP 8.1+, WordPress 6.x, WooCommerce 7.x+
- Namespace: BulkVariations\
- Autoloading: Composer PSR-4
- DI Container: lucatume/di52
- Admin UI: React + AG Grid Community (MIT)
- Background jobs: Action Scheduler (bundled with WooCommerce)
- Bulk DB writes: Direct $wpdb->query() SQL — NEVER wc_get_product()->save() in a loop
- Licensing: Freemius
- Testing: PHPUnit + Brain Monkey (unit) + WP_UnitTestCase (integration)

CRITICAL RULES:
1. Bulk operations MUST use Action Scheduler + batched SQL. Never loop wc_get_product()->save().
2. AI assistant NEVER writes to the DB automatically. Always create a suggestion job, require admin approval.
3. Every bulk write records a delta in wp_bv_job_changes for rollback capability.
4. Declare HPOS compatibility using FeaturesUtil::declare_compatibility('custom_order_tables').
5. All REST endpoints check current_user_can('manage_woocommerce') + verify_nonce().
6. Use AiAdapterInterface — provider-agnostic, support BYO API key, default OpenAI adapter.
7. Strip PII from all AI payloads. Only send product data (titles, attributes, prices) — never customer data.

ARCHITECTURE GOAL: Beat Barn2 WooCommerce Bulk Variations by adding what they are missing:
cross-product editing, scheduled imports, CSV import/export, job rollback, and an optional AI assistant.
```

---

### PROMPT 1 — Plugin Bootstrap & Core Structure

```
Using the project context above, generate the complete plugin bootstrap.

Output these files:

1. bulk-variations.php
   - Plugin header with Name, Version, Author, WC requires header
   - define() for BV_PLUGIN_PATH, BV_PLUGIN_URL, BV_VERSION
   - Require composer autoload
   - Activation hook: run MigrationRunner
   - Deactivation hook: clear scheduled actions
   - HPOS compatibility declaration (FeaturesUtil::declare_compatibility)
   - Boot via Plugin::instance()->boot() on plugins_loaded

2. composer.json
   - PSR-4: BulkVariations\ → src/
   - Require: lucatume/di52, woocommerce/action-scheduler
   - Require-dev: phpunit/phpunit, brain/monkey, 10up/wp_mock
   - Scripts: test, phpstan, build-free (Strauss strip)

3. src/Plugin.php
   - Singleton instance()
   - boot() method: check WooCommerce active, register service providers
   - Separate providers for: Admin, Frontend, REST, Jobs, AI

4. src/ServiceProvider.php
   - Register all services in the DI container
   - Bind interfaces to concrete implementations

5. migrations/0001_create_bv_tables.sql
   - Full SQL for wp_bv_jobs, wp_bv_job_changes, wp_bv_templates, wp_bv_ai_log
   - Use {prefix} placeholder, utf8mb4_unicode_ci

6. src/Updater/MigrationRunner.php
   - run() method: check current DB version in options, execute migrations in order
   - Safe to run multiple times (idempotent via dbDelta)

Include inline docblocks. Follow WordPress Coding Standards for hooks/filters naming.
```

---

### PROMPT 2 — Variation Engine & Bulk Editor

```
Using the project context, build the core variation engine.

Output these files:

1. src/Contracts/VariationServiceInterface.php
2. src/Contracts/JobRepositoryInterface.php

3. src/Engine/BulkEditor.php
   - Implements VariationServiceInterface
   - bulkUpdate(): MUST use batched CASE-based SQL via $wpdb->query(), NOT wc_get_product()->save()
   - Chunk size: 300 variations per batch
   - Each batch: wc_transaction_query('start'), SQL writes, wc_transaction_query('commit')
   - Records delta for every change via HistoryLogger before writing
   - After all chunks: wp_cache_flush(), wc_delete_product_transients(), update lookup tables

4. src/Engine/VariationGenerator.php
   - generateCombinations(int $product_id, array $attributes): array
   - Cartesian product algorithm
   - createVariations(): actually write WC_Product_Variation objects
   - Preview mode: return combinations without writing
   - Detect and skip duplicate combinations

5. src/Repository/JobRepository.php
   - Implements JobRepositoryInterface
   - All methods use $wpdb->prepare() — no raw string interpolation of user data

6. src/Services/HistoryLogger.php
   - recordChange(int $job_id, string $object_type, int $object_id, string $field, $old, $new)
   - Writes to wp_bv_job_changes

7. src/Services/SKUGenerator.php
   - generate(string $pattern, array $attributes): string
   - Pattern tokens: {product}, {attr_name}, {index}
   - Validates uniqueness against existing SKUs (batch check via single SQL IN query)

Include PHPUnit test stubs in tests/Unit/Engine/ for each class.
```

---

### PROMPT 3 — Background Job System

```
Using the project context, build the background job processing system using Action Scheduler.

Output these files:

1. src/Jobs/JobManager.php
   - dispatch(int $job_id, array $variation_ids, array $changes): void
   - Splits variation_ids into chunks of 300
   - Stores chunks in wp_options keyed by job_id
   - Schedules first chunk: as_enqueue_async_action('bv_process_job_chunk', [$job_id, 0])
   - processChunk(int $job_id, int $chunk_index): void (the Action Scheduler callback)
   - wp_suspend_cache_invalidation(true) during chunk, false after
   - Schedules next chunk with as_schedule_single_action(time()+5, ...)
   - Final chunk: cleanup, cache flush, job status = 'complete'

2. src/Jobs/BulkUpdateJob.php
   - Handles the actual SQL for one chunk
   - Receives chunk of [variation_id => [price, stock, sku, ...]] data
   - Uses CASE-based batched UPDATE (see BulkEditor pattern)
   - Updates wp_wc_product_meta_lookup after writes

3. src/Jobs/ImportJob.php
   - Handles CSV import chunks
   - Validates each row using ImportValidator
   - Creates or updates WC_Product_Variation per row
   - Reports per-row errors back to job error_log

Add Action Scheduler hook registration in ServiceProvider.
Include integration test stub in tests/Integration/JobProcessingTest.php.
```

---

### PROMPT 4 — REST API & WP-CLI

```
Build the REST API and WP-CLI commands.

Output these files:

1. src/REST/RestController.php
   - register_routes() called on rest_api_init
   - All routes under 'bv/v1' namespace
   - Routes: POST /jobs, GET /jobs, GET /jobs/{id}, POST /jobs/{id}/apply,
             POST /jobs/{id}/rollback, GET /jobs/{id}/rollback/preview,
             GET /variations, POST /variations/generate,
             POST /ai/suggest, GET /ai/suggest/{id}
   - Every route callback: check_permission() verifies manage_woocommerce + nonce
   - Return WP_REST_Response with consistent structure: {success, data, message}
   - Input sanitization on all params

2. src/CLI/BulkVariationsCLI.php
   - Register WP_CLI commands under 'bv' parent
   - bv jobs list [--status=queued|running|complete] [--limit=20]
   - bv job run <id>
   - bv job rollback <id> [--dry-run]
   - bv import <file> [--product-id=<id>] [--dry-run] [--chunk-size=300]
   - bv generate <product-id> --attributes='{"pa_color":["Red","Blue"]}'
   - Each command outputs a progress bar for long operations
   - CLI commands skip REST/nonce and use direct service calls

Include example curl commands in docblocks for each REST endpoint.
```

---

### PROMPT 5 — CSV Import/Export System

```
Build the CSV import and export system.

Output these files:

1. src/ImportExport/CsvImporter.php
   - import(string $file_path, int $product_id, array $options): int (returns job_id)
   - Streaming read for large files (fopen/fgetcsv — never file_get_contents for large CSVs)
   - Detect header row automatically
   - Map columns: sku, regular_price, stock_quantity, attribute_*, description
   - Create a bv_jobs record, dispatch to ImportJob via JobManager

2. src/ImportExport/ImportValidator.php
   - validateRow(array $row): array returns [is_valid, fixed_row, issues[]]
   - Checks: price > 0, stock is integer, sku is alphanumeric+dash
   - Batch duplicate SKU check: collect all SKUs first, single SQL IN query, then flag dupes

3. src/ImportExport/CsvExporter.php
   - export(int $product_id): void — streams CSV directly to browser (no temp file for small exports)
   - exportToFile(int $product_id, string $path): string — writes file for large/async export
   - Include all variation fields as columns
   - Proper CSV escaping (fputcsv)

4. templates/admin/import-preview.php
   - PHP template to show a preview table before committing an import
   - Shows valid/invalid row counts, sample of errors

Include a sample test CSV in tests/fixtures/sample-variations.csv.
Include test class tests/Unit/ImportExport/CsvImporterTest.php.
```

---

### PROMPT 6 — Admin React UI (Spreadsheet Editor)

```
Build the React admin application with AG Grid Community spreadsheet editor.

Output these files:

1. package.json
   - @wordpress/scripts for building (externalises React, wp.* globals)
   - ag-grid-community, ag-grid-react (latest Community versions — MIT license)
   - Build: wp-scripts build admin/src/index.jsx --output-path=admin/build

2. admin/src/index.jsx
   - Entry point, renders <BulkVariationsApp /> into #bv-spreadsheet-root
   - Pass window.bvData (PHP-localized data) as props

3. admin/src/components/SpreadsheetEditor/index.jsx
   - AG Grid React component
   - Column defs for: variation_id (readonly), sku, attributes (dynamic), regular_price, stock_quantity, status
   - Custom cellEditor for price: validates numeric, supports formulas like =PRICE*1.15
   - Custom cellRenderer for price: shows currency symbol
   - rowSelection: 'multiple' with checkboxes
   - onCellValueChanged: track changes in local state (not auto-save)
   - Undo/redo via custom hook (maintain change stack in useState)
   - "Apply Changes" button sends changed rows to POST /wp-json/bv/v1/jobs

4. admin/src/components/SpreadsheetEditor/toolbar.jsx
   - Bulk action bar (appears when rows selected)
   - Actions: Increase price %, Decrease price %, Set stock, Enable, Disable, Delete
   - Formula input: "=PRICE*1.1" applied to selected rows

5. admin/src/components/AiAssistantPanel/index.jsx
   - Slide-in side panel
   - Tabs: Generate | Suggest Prices | Clean Import | Summarize
   - Each tab: text input, Generate button, loading state, preview table
   - "Apply as Job" button (only enabled after preview loaded)
   - Never applies without explicit user action

6. admin/src/components/JobsPage/index.jsx
   - Table of jobs: ID, type, status, progress bar, created, actions
   - Rollback button: shows confirmation modal → POST /wp-json/bv/v1/jobs/{id}/rollback
   - Real-time status: polls GET /wp-json/bv/v1/jobs/{id} every 3 seconds for running jobs

7. admin/loader.php
   - Enqueue compiled admin JS/CSS only on bv admin pages
   - wp_localize_script to pass: nonce, rest_url, current_product_id, is_pro

Use WordPress admin colour variables (--wp-admin-theme-color) for styling.
```

---

### PROMPT 7 — AI Integration

```
Build the AI assistant backend service.

Output these files:

1. src/Contracts/AiAdapterInterface.php
   - request(string $prompt_type, array $sanitized_context): array
   - isConfigured(): bool

2. src/AI/Adapters/OpenAiAdapter.php
   - Implements AiAdapterInterface
   - Uses wp_remote_post() (no Guzzle — stays in WordPress ecosystem)
   - Model: gpt-4o-mini by default (cheapest capable model)
   - Reads API key from: get_option('bv_ai_api_key') — merchant BYO key
   - Timeout: 30 seconds
   - Handles: rate limits (429), errors, timeouts gracefully
   - Returns structured response matching interface

3. src/AI/Adapters/NullAdapter.php
   - Used for Free tier / unconfigured state
   - isConfigured() returns false
   - request() returns ['success'=>false, 'error'=>'AI not configured']

4. src/AI/PromptLibrary.php
   - Static get(string $type, array $vars): string
   - Types: generate_variations, suggest_prices, clean_import, summarize_job
   - Prompts instruct model to return ONLY valid JSON, no commentary
   - Include output format spec in each prompt

5. src/AI/AiAssistantService.php
   - generateVariations(array $context): array
   - suggestPrices(array $variations): array
   - cleanImportRows(array $rows): array
   - Each method: sanitize inputs → check cache → call adapter → cache result → return
   - Cache key: sha256(json_encode(sanitized_context)), TTL: 3600 seconds
   - Log request to wp_bv_ai_log (prompt_hash, type, tokens_used, accepted=0)

6. src/Admin/Pages/SettingsPage.php (AI section)
   - API key field (password input, masked)
   - Provider selector (OpenAI | Custom endpoint | Disabled)
   - Data consent toggle (required for external calls)
   - Usage stats: requests today, tokens used, estimated cost
   - Test connection button

Include unit tests in tests/Unit/AI/AiAssistantServiceTest.php.
```

---

### PROMPT 8 — Rollback System

```
Build the rollback system.

Output these files:

1. src/Rollback/RollbackService.php
   - preview(int $job_id): array — dry run, no DB writes
   - rollback(int $job_id): int — creates inverse job, schedules background worker
   - Inverse operation: swap old_value ↔ new_value in deltas from wp_bv_job_changes
   - Validates: job exists, status is 'complete', not already rolled back
   - Large rollbacks (>500 rows): dispatch via JobManager in chunks
   - Small rollbacks (<50 rows): execute synchronously

2. src/Repository/RollbackRepository.php
   - getJobChanges(int $job_id): array
   - markRolledBack(int $job_id): bool (sets status = 'rolled_back')

3. admin/src/components/RollbackPreview/index.jsx
   - Modal showing: variation ID, field, current value, will revert to
   - Confirm + Cancel buttons
   - Progress UI for background rollbacks

Include integration test in tests/Integration/RollbackTest.php:
- Create variations, run bulk price change, verify prices changed, rollback, verify prices restored.
- Test must be idempotent and clean up after itself.
```

---

### PROMPT 9 — Freemius Licensing Integration

```
Integrate Freemius for the Free/Pro licensing system.

Output these files:

1. src/Licensing/LicenseManager.php
   - freemius() function that returns Freemius SDK singleton
   - Init Freemius with plugin details (slug, public key placeholders)
   - isPro(): bool — checks Freemius license status
   - Feature gate helper: requiresPro(string $feature): void — throws if not Pro

2. src/Licensing/FeatureFlags.php
   - Constants for Pro features: AI_ASSISTANT, BULK_EDITOR_UNLIMITED, SCHEDULED_IMPORTS,
     ROLLBACK, REST_API, WP_CLI, MULTISITE, ADVANCED_FORMULAS
   - isEnabled(string $feature): bool — returns true if Pro license active

3. Modify ServiceProvider.php:
   - Register NullAdapter for AI if not Pro (FeatureFlags::isEnabled(AI_ASSISTANT))
   - Restrict REST routes if not Pro

4. admin/loader.php additions:
   - Pass is_pro to bvData so React can show/hide Pro UI elements
   - Pro features show upgrade tooltip if not Pro

Include the Freemius SDK download instructions as a comment (it must be downloaded separately
from freemius.com — cannot be committed to the repo due to their terms).
```

---

### PROMPT 10 — Storefront Variation Grid

```
Build the frontend storefront variation grid.

Output these files:

1. src/Frontend/VariationGrid.php
   - Register shortcode [bv_variation_grid product_id="123"]
   - Auto-inject on variable product pages (filter woocommerce_variable_add_to_cart)
   - Query all variations for the product
   - Build data structure: attributes × variations matrix
   - Pass to template via extract()

2. templates/frontend/variation-grid.php
   - Responsive HTML table: rows = values of primary attribute, cols = secondary attribute
   - Each cell: qty input (type number, min=0) + current price
   - "Add All to Cart" button
   - Out-of-stock cells: greyed out with "Out of stock" label
   - Accessible: aria-labels on inputs, proper table headers

3. assets/js/frontend-grid.js (vanilla JS — no jQuery dependency)
   - Collect all non-zero qty inputs on "Add All to Cart" click
   - Batch add-to-cart via WooCommerce AJAX (wc-ajax=add_to_cart)
   - Show success/error notice
   - Update cart count in header

4. assets/css/frontend.css
   - Responsive: stacks to vertical list on mobile
   - WordPress-theme agnostic (no hardcoded colors)
   - CSS custom properties for easy theming

Include PHP unit test for VariationGrid shortcode output in tests/Unit/Frontend/VariationGridTest.php.
```

---

## 13. Release Roadmap

### MVP — Weeks 1–6 (Free on WordPress.org)

| Week | Deliverables |
|------|-------------|
| 1 | Plugin bootstrap, migrations, MigrationRunner, HPOS declaration |
| 2 | VariationGenerator, BulkEditor (SQL version), JobRepository, SKUGenerator |
| 3 | CsvImporter + validator, basic admin page, storefront grid |
| 4 | JobManager + Action Scheduler integration, BulkUpdateJob |
| 5 | React spreadsheet (basic AG Grid), REST API core endpoints |
| 6 | PHPUnit tests, WordPress.org submission, Free release |

### v1.0 — Weeks 7–14 (Pro Launch)

| Week | Deliverables |
|------|-------------|
| 7–8 | Full React spreadsheet editor (formulas, undo/redo, copy/paste) |
| 9 | AI assistant service + OpenAI adapter + BYO-key settings |
| 10 | Rollback system (delta storage + revert worker) |
| 11 | Scheduled import jobs + email reports |
| 12 | WP-CLI commands, REST API (Pro endpoints) |
| 13 | Freemius licensing integration, upgrade flow, Pro feature gates |
| 14 | Full integration test suite, Pro release |

### v1.5 — Weeks 15–26

- Multi-site support
- Variation templates (save/apply)
- Advanced price formula editor
- Scheduled AI jobs
- Performance benchmarking + optimisation pass
- Documentation site

### v2.0 — Month 6–12

- B2B/wholesale mode with role-based pricing grid
- Marketplace integrations (WPML, Polylang, WooCommerce Subscriptions)
- Analytics dashboard (variation performance, top-edited products)
- WooCommerce.com Marketplace listing

---

## 14. Marketing Copy & Pricing

### Headline

**Bulk Variations Add & Edit Product** — The only WooCommerce plugin that combines a frontend ordering grid with enterprise-grade backend bulk editing.

### Sub-headline

Spreadsheet editing. AI-assisted generation. Scheduled imports. Rollback. Built for stores with hundreds — or tens of thousands — of variations.

### Three Core Value Props

**1. Never time out again.** Background processing handles 10,000+ variations in minutes, not hours. No more PHP timeout errors.

**2. Excel in your browser.** Arrow key navigation, copy/paste from Excel, bulk formulas, undo/redo — all the shortcuts you already know.

**3. Safe and auditable.** Every bulk change is logged. Roll back any job to its previous state with one click.

### Feature Comparison Table

| Feature | Free | Pro |
|---------|------|-----|
| Storefront variation grid | ✅ | ✅ |
| Basic bulk edit (price + stock) | ✅ | ✅ |
| Variation generator (3 attributes) | ✅ | ✅ |
| CSV import (up to 100 rows) | ✅ | ✅ |
| CSV export | ✅ | ✅ |
| AI teaser (3 variations) | ✅ | ✅ |
| Full spreadsheet editor | ❌ | ✅ |
| Cross-product editing | ❌ | ✅ |
| Unlimited variations | ❌ | ✅ |
| AI assistant (full) | ❌ | ✅ |
| Scheduled imports | ❌ | ✅ |
| Background processing (10k+) | ❌ | ✅ |
| Job changelog + rollback | ❌ | ✅ |
| REST API | ❌ | ✅ |
| WP-CLI commands | ❌ | ✅ |
| Variation templates | ❌ | ✅ |
| Multi-site support | ❌ | ✅ |
| Priority support | ❌ | ✅ |

### Upgrade Modal Copy

> **Unlock the full power of Bulk Variations.**  
> You're one step away from AI-assisted generation, 10k+ variation processing, and full job rollback.  
> Start a **14-day Pro trial** — no credit card required.  
> [Start Free Trial] [See All Features]

### 5 FAQs

**1. Will it work on my store with 5,000+ variations?**  
Yes. The Pro edition uses background processing with Action Scheduler — the same technology WooCommerce uses internally. Jobs run in the background and you get notified when they complete. No PHP timeouts, no browser waiting.

**2. Will AI change my prices automatically?**  
No, never. AI suggestions are always shown as a preview first. You review them, edit if needed, then click "Apply" to make changes. Nothing touches your database until you confirm.

**3. Can I undo a bulk import that went wrong?**  
Yes (Pro). Every bulk operation records a changelog. You can view exactly what changed and roll back any job to its previous state from the Jobs page.

**4. Can I use my own OpenAI API key?**  
Yes. In Settings → AI Assistant, enter your own API key. This gives you full control over costs and keeps your product data on your own API account.

**5. Does it work with Barn2, WPML, or other plugins?**  
The plugin is designed to be compatible. We provide developer hooks and filters on all major operations. Compatibility testing with top plugins is ongoing and documented in our changelog.

---

## 15. Testing Plan

### Unit Tests (Brain Monkey — no WordPress required, fast)

| Test Class | What It Tests |
|-----------|---------------|
| `VariationGeneratorTest` | Cartesian product correctness, duplicate detection |
| `SKUGeneratorTest` | Pattern replacement, edge cases |
| `PriceCalculatorTest` | Formula evaluation `=PRICE*1.15`, invalid input handling |
| `CsvImporterTest` | Row parsing, header detection, validator integration |
| `ImportValidatorTest` | Valid/invalid rows, duplicate SKU detection |
| `RollbackServiceTest` | Inverse delta generation, preview output |
| `AiAssistantServiceTest` | Cache hit/miss, sanitization, NullAdapter fallback |
| `PromptLibraryTest` | All prompt types produce valid non-empty strings |

### Integration Tests (WP_UnitTestCase — real WordPress + WooCommerce)

| Test Class | What It Tests |
|-----------|---------------|
| `ImportPipelineTest` | Full import: CSV → job → Action Scheduler → DB → verify variation values |
| `BulkEditorTest` | Create 500 test variations → bulk price change → verify all prices updated |
| `RollbackTest` | Bulk change → verify changed → rollback → verify reverted (idempotent) |
| `RestApiTest` | POST /jobs → verify response shape → GET /jobs/{id} → verify status |
| `JobManagerTest` | Dispatch job → simulate Action Scheduler → verify chunks processed in order |

### Performance Test

Create 10,000 variations on a test product. Run bulk price update. Assert:
- Memory usage stays below 128MB peak
- Total wall-clock time under 5 minutes
- All variations have correct new price
- Lookup tables updated

### Security Checklist

- [ ] All REST callbacks call `check_permission()` — capability + nonce
- [ ] All `$wpdb->query()` calls use `$wpdb->prepare()`
- [ ] All user inputs sanitized: `sanitize_text_field()`, `floatval()`, `intval()`
- [ ] CSV files validated: check MIME type + extension before processing
- [ ] AI payloads: only product fields sent, no customer/order data
- [ ] File upload paths sanitized: no path traversal (`../`)
- [ ] PHPStan level 6+ passes with no errors

---

*Document prepared for: Cursor AI development + developer reference*  
*Plugin: Bulk Variations Add & Edit Product*  
*Version: Plan v1.0 — March 2026*
