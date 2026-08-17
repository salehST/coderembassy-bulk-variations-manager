=== CoderEmbassy Bulk Variations Manager ===
Contributors: codersaleh
Tags: woocommerce, variations, bulk edit, csv import, rollback
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.1
WC requires at least: 7.0
WC tested up to: 10.9
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk edit WooCommerce product variations with preview, approval, CSV import, jobs, and rollback.

== Description ==

CoderEmbassy Bulk Variations Manager helps store managers update WooCommerce variations across selected variable products.

Core features include:

* Multi-product variation spreadsheet editor.
* Product search for variable products.
* Editable SKU, prices, sale dates, stock, stock status, and variation status.
* Read-only variation attribute columns for easier row identification.
* Preview and approve workflow before changes are applied.
* Jobs screen with applied change history.
* Rollback for completed bulk edit and import jobs.
* CSV import for updating existing variations.
* CSV import for creating new variations from existing product attributes.
* CSV attribute helper and downloadable CSV template.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure WooCommerce is active.
4. Open Bulk Variations from the WordPress admin menu.

== Frequently Asked Questions ==

= Does this edit every product at once? =

The editor can load variations from multiple selected variable products in one editing session. CSV import can identify rows by product ID when updating or creating variations.

= Can I preview changes before applying them? =

Yes. Bulk edits and CSV imports use a preview and approval workflow before writing changes.

= Can I roll back changes? =

Yes. Completed jobs can be rolled back from the Jobs screen.

= Can CSV create new variations? =

Yes, when the parent product already has variation attributes and the CSV uses valid `attribute_*` columns and term slugs.

= Does uninstall remove plugin data? =

Only when you enable "Remove plugin data on uninstall" in Settings before uninstalling.

== External services ==

This plugin may load Google Fonts assets on the plugin's admin Bulk Editor screen through bundled grid interface assets. The font request is made by the administrator's browser when that admin screen is opened. Google may receive standard browser request data such as the requesting IP address, user agent, and referrer in order to serve the font CSS or font files.

Google Fonts is provided by Google. Terms: https://policies.google.com/terms Privacy policy: https://policies.google.com/privacy

== Screenshots ==

1. Admin dashboard.
2. Edit product.
3. Choose a product to edit.
4. Customize the table columns.
5. Filter rows.
6. Edit price.
7. Price updated.
8. Jobs.
9. CSV import.
10. Settings.
11. Dark theme for the dashboard.

== Changelog ==

= 1.0.3 =

* Fixed: background jobs larger than one chunk reported only the final chunk's processed count and stayed at 0% progress while running.
* Fixed: failed and completed background jobs left their queued row data behind in the options table.
* Fixed: variations created by CSV import or generation could show stale prices on the storefront until the parent product was saved again.
* Fixed: the plugin admin body class was added on every admin screen instead of only the plugin screen.
* Improved: database table checks no longer run on every request.

= 1.0.2 =

* Improved admin asset loading and interface compatibility.
* Updated public plugin identifiers for the WordPress.org release.

= 0.1.8 =

* Added inert developer extension hooks for admin views, script bundles, grid columns, and bulk actions.

= 0.1.7 =
* Initial release candidate.
