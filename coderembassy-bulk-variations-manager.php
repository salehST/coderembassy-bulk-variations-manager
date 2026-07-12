<?php

/**
 * Plugin Name:       CoderEmbassy Bulk Variations Manager 
 * Plugin URI:        https://coderembassy.com
 * Description:       Bulk edit and create WooCommerce product variations with a spreadsheet editor, CSV import, background jobs, and rollback.
 * Version:           1.0.0
 * Author:            CoderEmbassy
 * Author URI:        https://coderembassy.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       coderembassy-bulk-variations-manager
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 *
 * @package BulkVariations
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('BV_VERSION', '1.0.0');
define('BV_PLUGIN_FILE', __FILE__);
define('BV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BV_PLUGIN_URL', plugin_dir_url(__FILE__));

$bv_autoload = BV_PLUGIN_PATH . 'vendor/autoload.php';

if (! file_exists($bv_autoload)) {
	add_action(
		'admin_notices',
		static function (): void {
			if (! current_user_can('activate_plugins')) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__('CoderEmbassy Bulk Variations Manager', 'coderembassy-bulk-variations-manager'),
				esc_html__(
					'Composer dependencies are missing. Run composer install in the plugin directory.',
					'coderembassy-bulk-variations-manager'
				)
			);
		}
	);
	return;
}

require_once $bv_autoload;

add_action('plugins_loaded', 'bv_maybe_start_rest_output_buffer', -1000);

register_activation_hook(
	__FILE__,
	static function (): void {
		\BulkVariations\Updater\MigrationRunner::run();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		bv_deactivate_plugin();
	}
);

add_action(
	'before_woocommerce_init',
	static function (): void {
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				BV_PLUGIN_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				BV_PLUGIN_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain(
			'coderembassy-bulk-variations-manager',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages'
		);
	},
	1
);

add_action(
	'plugins_loaded',
	static function (): void {
		\BulkVariations\Plugin::instance()->boot();
	},
	20
);

add_action('action_scheduler_init', 'bv_schedule_retention_cron');

/**
 * Start a tiny output buffer for BV REST requests.
 *
 * Some local/dev sites display PHP notices or debug output from other plugins.
 * Any stray output before the JSON body makes apiFetch report an invalid
 * response, so we discard pre-response output only for this plugin namespace.
 *
 * @return void
 */
function bv_maybe_start_rest_output_buffer(): void
{
	if (! bv_is_bulk_variations_rest_request() || headers_sent()) {
		return;
	}

	if (! empty($GLOBALS['bv_rest_output_buffer_started'])) {
		return;
	}

	$GLOBALS['bv_rest_output_buffer_started'] = true;
	$GLOBALS['bv_rest_output_buffer_level']   = ob_get_level();

	ob_start();
	add_filter('rest_pre_serve_request', 'bv_discard_rest_output_buffer', 0);
}

/**
 * Whether the current request targets the BV REST namespace.
 *
 * @return bool
 */
function bv_is_bulk_variations_rest_request(): bool
{
	$rest_route = isset($_GET['rest_route'])
		? sanitize_text_field(wp_unslash((string) $_GET['rest_route']))
		: '';

	if (str_starts_with(ltrim($rest_route, '/'), 'bv/v1/')) {
		return true;
	}

	$request_uri = isset($_SERVER['REQUEST_URI'])
		? (string) wp_unslash($_SERVER['REQUEST_URI'])
		: '';

	$path = wp_parse_url($request_uri, PHP_URL_PATH);

	return is_string($path) && str_contains($path, '/wp-json/bv/v1/');
}

/**
 * Discard output captured before WordPress serves the JSON response.
 *
 * @param bool $served Whether the REST response has already been served.
 * @return bool
 */
function bv_discard_rest_output_buffer(bool $served): bool
{
	$target_level = isset($GLOBALS['bv_rest_output_buffer_level'])
		? (int) $GLOBALS['bv_rest_output_buffer_level']
		: null;

	if (null !== $target_level) {
		while (ob_get_level() > $target_level) {
			ob_end_clean();
		}
	}

	unset($GLOBALS['bv_rest_output_buffer_started'], $GLOBALS['bv_rest_output_buffer_level']);

	return $served;
}

/**
 * Schedule daily retention cron via Action Scheduler.
 *
 * @return void
 */
function bv_schedule_retention_cron(): void
{
	$as = BV_PLUGIN_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	if (file_exists($as)) {
		require_once $as;
	}

	if (function_exists('did_action') && 0 === did_action('action_scheduler_init')) {
		return;
	}

	if (! function_exists('as_schedule_recurring_action') || ! function_exists('as_next_scheduled_action')) {
		return;
	}

	$hook  = \BulkVariations\Jobs\JobManager::RETENTION_HOOK;
	$group = 'coderembassy-bulk-variations-manager';

	if (as_next_scheduled_action($hook, array(), $group)) {
		return;
	}

	$hour = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
	$day  = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;

	as_schedule_recurring_action(time() + $hour, $day, $hook, array(), $group);
}

/**
 * Deactivation: clear scheduled jobs and transients.
 *
 * @return void
 */
function bv_deactivate_plugin(): void
{
	bv_unschedule_bv_actions();
	bv_flush_plugin_transients();
}

/**
 * Unschedule all Action Scheduler hooks prefixed with bv_.
 *
 * @return void
 */
function bv_unschedule_bv_actions(): void
{
	if (! function_exists('as_unschedule_all_actions')) {
		return;
	}

	global $wpdb;

	$table = $wpdb->prefix . 'actionscheduler_actions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

	if ($exists !== $table) {
		return;
	}

	$like = $wpdb->esc_like('bv_') . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$hooks = $wpdb->get_col(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Action Scheduler table name validated above.
			"SELECT DISTINCT hook FROM `{$table}` WHERE hook LIKE %s",
			$like
		)
	);

	if (! is_array($hooks)) {
		return;
	}

	foreach ($hooks as $hook) {
		as_unschedule_all_actions($hook);
	}
}

/**
 * Delete plugin transients.
 *
 * @return void
 */
function bv_flush_plugin_transients(): void
{
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like('_transient_bv_') . '%',
			$wpdb->esc_like('_transient_timeout_bv_') . '%'
		)
	);
}
