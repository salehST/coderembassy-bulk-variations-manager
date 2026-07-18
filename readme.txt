=== CoderEmbassy Bulk Variations Manager for WooCommerce ===
Contributors: codersaleh
Tags: woocommerce, variations, bulk edit, csv import, rollback
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.1
WC requires at least: 7.0
WC tested up to: 10.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk edit WooCommerce product variations with preview, approval, CSV import, jobs, and rollback.

== Description ==

CoderEmbassy Bulk Variations Manager for WooCommerce helps store managers update variations for one variable product at a time.

Free features include:

* Single-product variation spreadsheet editor.
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

No. The Free plugin edits one variable product at a time.

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

1. Bulk editor grid.
2. CSV import validation.
3. Jobs and rollback history.

== Changelog ==

= 1.0.0 =
* Initial release.
