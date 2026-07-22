<?php
/**
 * Plugin Name:       CoderEmbassy Bulk Variations Manager for WooCommerce
 * Plugin URI:        https://coderembassy.com
 * Description:       Bulk edit and create WooCommerce product variations with a spreadsheet editor, CSV import, background jobs, and rollback.
 * Version:           0.1.8
 * Author:            CoderEmbassy
 * Author URI:        https://coderembassy.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       coderembassy-bulk-variations-manager
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CODEREMBASSY_BVM_VERSION', '0.1.8' );
define( 'CODEREMBASSY_BVM_PLUGIN_FILE', __FILE__ );
define( 'CODEREMBASSY_BVM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODEREMBASSY_BVM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$coderembassy_bvm_autoload = CODEREMBASSY_BVM_PLUGIN_PATH . 'vendor/autoload.php';

if ( ! file_exists( $coderembassy_bvm_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'CoderEmbassy Bulk Variations Manager', 'coderembassy-bulk-variations-manager' ),
				esc_html__(
					'Composer dependencies are missing. Run composer install in the plugin directory.',
					'coderembassy-bulk-variations-manager'
				)
			);
		}
	);
	return;
}

require_once $coderembassy_bvm_autoload;

add_action( 'plugins_loaded', 'coderembassy_bvm_maybe_start_rest_output_buffer', -1000 );

register_activation_hook(
	__FILE__,
	static function (): void {
		\CoderEmbassy\BulkVariationsManager\Updater\MigrationRunner::run();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		coderembassy_bvm_deactivate_plugin();
	}
);

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CODEREMBASSY_BVM_PLUGIN_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				CODEREMBASSY_BVM_PLUGIN_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\CoderEmbassy\BulkVariationsManager\Plugin::instance()->boot();
	},
	20
);

add_action( 'action_scheduler_init', 'coderembassy_bvm_schedule_retention_cron' );

/**
 * Start a tiny output buffer for BV REST requests.
 *
 * Some local/dev sites display PHP notices or debug output from other plugins.
 * Any stray output before the JSON body makes apiFetch report an invalid
 * response, so we discard pre-response output only for this plugin namespace.
 *
 * @return void
 */
function coderembassy_bvm_maybe_start_rest_output_buffer(): void {
	if ( ! coderembassy_bvm_is_rest_request() || headers_sent() ) {
		return;
	}

	if ( ! empty( $GLOBALS['coderembassy_bvm_rest_output_buffer_started'] ) ) {
		return;
	}

	$GLOBALS['coderembassy_bvm_rest_output_buffer_started'] = true;
	$GLOBALS['coderembassy_bvm_rest_output_buffer_level']   = ob_get_level();

	ob_start();
	add_filter( 'rest_pre_serve_request', 'coderembassy_bvm_discard_rest_output_buffer', 0 );
}

/**
 * Whether the current request targets the BV REST namespace.
 *
 * @return bool
 */
function coderembassy_bvm_is_rest_request(): bool {
	$rest_route = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Request routing only; REST permission callbacks authorize the request.
	if ( isset( $_GET['rest_route'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Request routing only; REST permission callbacks authorize the request.
		$rest_route = sanitize_text_field( wp_unslash( (string) $_GET['rest_route'] ) );
	}

	if ( str_starts_with( ltrim( $rest_route, '/' ), 'coderembassy-bvm/v1/' ) ) {
		return true;
	}

	$request_uri = '';
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Request routing only; REST permission callbacks authorize the request.
		$request_uri = esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
	}

	$path = wp_parse_url( $request_uri, PHP_URL_PATH );

	return is_string( $path ) && str_contains( $path, '/wp-json/coderembassy-bvm/v1/' );
}

/**
 * Discard output captured before WordPress serves the JSON response.
 *
 * @param bool $served Whether the REST response has already been served.
 * @return bool
 */
function coderembassy_bvm_discard_rest_output_buffer( bool $served ): bool {
	$target_level = isset( $GLOBALS['coderembassy_bvm_rest_output_buffer_level'] )
		? (int) $GLOBALS['coderembassy_bvm_rest_output_buffer_level']
		: null;

	if ( null !== $target_level ) {
		while ( ob_get_level() > $target_level ) {
			ob_end_clean();
		}
	}

	unset( $GLOBALS['coderembassy_bvm_rest_output_buffer_started'], $GLOBALS['coderembassy_bvm_rest_output_buffer_level'] );

	return $served;
}

/**
 * Schedule daily retention cron via Action Scheduler.
 *
 * @return void
 */
function coderembassy_bvm_schedule_retention_cron(): void {
	$as = CODEREMBASSY_BVM_PLUGIN_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	if ( file_exists( $as ) ) {
		require_once $as;
	}

	if ( function_exists( 'did_action' ) && 0 === did_action( 'action_scheduler_init' ) ) {
		return;
	}

	if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
		return;
	}

	$hook  = \CoderEmbassy\BulkVariationsManager\Jobs\JobManager::RETENTION_HOOK;
	$group = 'coderembassy-bulk-variations-manager';

	if ( as_next_scheduled_action( $hook, array(), $group ) ) {
		return;
	}

	$hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
	$day  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;

	as_schedule_recurring_action( time() + $hour, $day, $hook, array(), $group );
}

/**
 * Deactivation: clear scheduled jobs and transients.
 *
 * @return void
 */
function coderembassy_bvm_deactivate_plugin(): void {
	coderembassy_bvm_unschedule_actions();
	coderembassy_bvm_flush_transients();
}

/**
 * Unschedule all Action Scheduler hooks prefixed with coderembassy_bvm_.
 *
 * @return void
 */
function coderembassy_bvm_unschedule_actions(): void {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	global $wpdb;

	$table = $wpdb->prefix . 'actionscheduler_actions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	if ( $exists !== $table ) {
		return;
	}

	$like = $wpdb->esc_like( 'coderembassy_bvm_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler does not expose prefix-based unscheduling.
		$hooks = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT hook FROM %i WHERE hook LIKE %s',
				$table,
				$like
			)
		);

	if ( ! is_array( $hooks ) ) {
		return;
	}

	foreach ( $hooks as $hook ) {
		as_unschedule_all_actions( $hook );
	}
}

/**
 * Delete plugin transients.
 *
 * @return void
 */
function coderembassy_bvm_flush_transients(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_coderembassy_bvm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_coderembassy_bvm_' ) . '%'
		)
	);
}
