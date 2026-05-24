# CoderEmbassy Bulk Variations Manager for WooCommerce

Free WooCommerce plugin for managing product variations in bulk: single-product spreadsheet editor, CSV import (update existing variations or create new ones), background jobs with approval, and rollback.

## Features (Free)

- **Bulk Editor** — Select a variable product and edit variations in a spreadsheet-style grid (prices, stock, SKU, sale dates, attributes, and more).
- **CSV Import** — Upload a CSV to preview changes, validate rows, then apply updates or create new variations via a job.
- **Jobs** — Review pending changes, approve or reject, and track progress for bulk edits and imports.
- **Rollback** — Revert applied job changes where history is available (see import/create docs for limits on new variation posts).

## Requirements

- PHP 8.1+
- WordPress 6.3+
- WooCommerce 7.0+
- Node.js 20+ (admin UI build)
- Composer 2.x

## Development quickstart

```bash
cd coderembassy-bulk-variations-manager
composer install
npm install
npm run start          # watch admin React bundle → assets/admin/dist/
npx wp-env start       # local WordPress + WooCommerce
```

Activate the plugin via `coderembassy-bulk-variations-manager.php` in the WordPress plugins screen.

## Quality checks

```bash
composer validate --strict
composer lint:php
composer phpstan
composer test:unit
npm run build
npm run lint:js
npm run test:e2e
```

## Porting from legacy Pro plugin

See [docs/PORTING.md](docs/PORTING.md). Reference code may live in a sibling Pro folder (read-only).
