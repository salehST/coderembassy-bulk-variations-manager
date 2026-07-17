# Extension API

CoderEmbassy Bulk Variations Manager exposes a small set of developer hooks for extending the admin experience and editor behavior.

These hooks are inert by default. If no external code registers callbacks, the plugin behaves exactly as it does without this API.

## PHP Actions

### `bv_booted`

Runs after the plugin has registered its runtime services and WordPress hooks.

```php
do_action( 'bv_booted', $plugin );
```

The `$plugin` argument exposes a neutral service registry:

```php
$repository = $plugin->service( \BulkVariations\Engine\VariationRepository::class );
```

## PHP Filters

### `bv_admin_script_bundles`

Registers additional admin JavaScript bundles on the plugin admin screen.

Each bundle is an associative array:

```php
array(
	'handle'  => 'my-extension-admin',
	'src'     => 'https://example.test/index.js',
	'deps'    => array( 'wp-element', 'wp-i18n' ),
	'version' => '1.0.0',
	'type'    => '',
)
```

The `type` value is optional and is passed to `wp_script_add_data()`.

## JavaScript Filters

The admin app uses `@wordpress/hooks`.

### `bv_admin_views`

Filters registered admin views before routing and sidebar rendering.

### `bv_sidebar_nav`

Filters sidebar navigation items after registered views are sorted.

### `bv_grid_columns`

Filters AG Grid column definitions before visibility filtering.

### `bv_bulk_actions`

Filters bulk action definitions before the editor action row is rendered.
