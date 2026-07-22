# CoderEmbassy Bulk Variations Manager

User guide for the WordPress.org plugin.

## What the Plugin Does

CoderEmbassy Bulk Variations Manager helps store owners update WooCommerce variable product variations from one admin screen. The editor works on one selected variable product per editing session and also supports CSV import, review-before-save jobs, and rollback support.

## Requirements

- WordPress with WooCommerce installed and active.
- A WooCommerce variable product with variations.
- An admin user with permission to manage WooCommerce products.

## Install and Activate

1. Install the plugin from your WordPress admin plugin screen or upload the plugin zip.
2. Activate `CoderEmbassy Bulk Variations Manager`.
3. Open the plugin from the WordPress admin menu.

## Main Workflow

Most edits follow the same safe workflow:

1. Choose the product or upload a CSV.
2. Make changes or validate imported rows.
3. Review the pending changes.
4. Click Preview & Approve.
5. Apply the job only after checking the old and new values.

Changes are not written immediately while you edit. They are staged first so you can inspect them before saving.

## Bulk Editor

Use Bulk Editor when you want to edit variations for a selected variable product.

### Select a Product

1. Open Bulk Editor.
2. Search for a variable product.
3. Select the product.
4. Load the variations.

If no products appear, confirm the product type is `Variable product` in WooCommerce.

### Choose Visible Fields

Use the editable fields controls to choose which columns appear in the variation table. Showing fewer fields makes the table easier to scan.

Common fields include:

- SKU
- Regular price
- Sale price
- Sale start date
- Sale end date
- Stock quantity
- Stock status
- Variation status
- Variation attributes

### Edit Variation Rows

Change values directly in the table. The plugin tracks changed rows and shows pending edits before you approve them.

Examples:

- Change a regular price from `49` to `59`.
- Set a sale price.
- Add sale start and end dates.
- Update stock quantity.
- Set stock status to in stock, out of stock, or on backorder.
- Change variation status.
- Update variation SKU.

### Preview and Apply

After making edits:

1. Check the pending change summary.
2. Click Preview & Approve.
3. Review each old value and new value.
4. Click Apply to save changes.

If the preview does not look right, discard the job instead of applying it.

## CSV Import

Use CSV Import when you want to create or update variations from a spreadsheet.

### CSV Import Workflow

1. Open CSV Import.
2. Upload a CSV file.
3. Optionally enter a default product ID.
4. Load attributes if you are creating variations.
5. Validate the CSV.
6. Review valid rows, invalid rows, and warnings.
7. Click Preview & Approve.
8. Apply the job after checking the changes.

### Updating Existing Variations

To update existing variations, include a variation identifier such as:

- `variation_id`
- `sku`

Then include the fields you want to update, such as prices or stock values.

### Creating New Variations

To create variations, include:

- `product_id`, or use the default product ID field.
- At least one `attribute_` column, such as `attribute_color` or `attribute_size`.

Create rows need enough attribute data for WooCommerce to create a real variation.

### Common CSV Columns

Supported columns include:

- `product_id`
- `variation_id`
- `sku`
- `regular_price`
- `sale_price`
- `sale_from`
- `sale_to`
- `stock_quantity`
- `stock_status`
- `status`
- `attribute_color`
- `attribute_size`
- Other `attribute_` columns used by your product

Dates should use `YYYY-MM-DD`.

### Validation Results

Validation can show:

- Valid rows: rows that can be previewed.
- Invalid rows: rows that cannot be imported until fixed.
- Warnings: rows that can continue but may need attention.

Examples of invalid rows:

- Missing product ID when creating a variation.
- Missing required attribute columns.
- Negative regular price.
- Duplicate SKU in the import batch.
- Stock quantity is not a whole number.

Examples of warnings:

- Sale start date is after sale end date.
- A value is unusual but not dangerous enough to block the import.

## Jobs

Jobs are the review and processing records created by the plugin.

### Job Statuses

- Pending review: the job is waiting for you to apply or discard it.
- Processing: the job is currently being applied.
- Completed: the job finished successfully.
- Failed: the job could not finish.
- Rolled back: the job was reverted where rollback data was available.

### Rollback

Rollback attempts to restore old values from a completed job. Review rollback results after running it.

Important notes:

- Rollback depends on the old values captured in the job.
- Rollback is safest for updates to existing variation fields.
- If a job created new variation posts, review those manually after rollback.

## Settings

Settings may include:

- Admin theme preference.
- Default product ID for CSV import.
- Jobs per page.
- Whether plugin data should be removed when uninstalling.

Only enable data removal if you are sure you want plugin job records and settings deleted during uninstall.

## Troubleshooting

### Product Does Not Appear in Search

Check that the product is a WooCommerce variable product and that WooCommerce is active.

### CSV Has No Valid Rows

Check the validation errors first. Most CSV issues are caused by missing `product_id`, missing `attribute_` columns for create rows, duplicate SKUs, or invalid numeric values.

### Preview Looks Different Than Expected

Discard the job and adjust the source edit or CSV. Do not apply a job unless the preview is correct.

### Changes Do Not Show on the Storefront

Clear any site cache and check the product variation selected on the storefront. WooCommerce may also need product lookup tables or transients refreshed after large updates.

### Rollback Failed

Open the job details and review the error message. A rollback can fail if WooCommerce rejects a value, a variation no longer exists, or another plugin changed the product data.

## Best Practices

- Test with a small set of variations first.
- Use Preview & Approve before every important change.
- Keep a backup before large imports.
- Use clear SKUs so variations are easy to identify.
- Fix invalid CSV rows before importing again.
