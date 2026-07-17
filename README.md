# CoderEmbassy Bulk Variations Manager

Free WooCommerce plugin for editing product variations in bulk.

## Included features

- Spreadsheet-style variation editor.
- CSV validation, preview, import, and rollback workflows.
- Background jobs with approval and progress tracking.
- Variation generation and developer extension hooks.

## Requirements

- PHP 8.1 or newer.
- WordPress 6.3 or newer.
- WooCommerce 7.0 or newer.
- Composer 2 and Node.js 20 for development.

## Development

```bash
composer install
npm install
npm run build
composer test:unit
composer phpstan
npm run lint:js
```

The WordPress.org production package is built with:

```bash
composer package:release
```

See [docs/USER_GUIDE_FREE.md](docs/USER_GUIDE_FREE.md) for the user guide and [docs/EXTENSION_API.md](docs/EXTENSION_API.md) for developer hooks.
