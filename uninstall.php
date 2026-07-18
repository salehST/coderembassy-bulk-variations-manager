<?php
/**
 * Uninstall cleanup.
 *
 * @package BulkVariations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data when the administrator opted into destructive cleanup.
 */
function coderembassy_bvm_uninstall_cleanup(): void {
	$remove_data = (bool) get_option( 'bv_uninstall_remove_data', false );

	if ( ! $remove_data ) {
		return;
	}

	global $wpdb;

	$tables = array(
		'bv_jobs',
		'bv_job_changes',
		'bv_templates',
		'bv_ai_log',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only allowlisted plugin-owned tables.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only plugin-prefixed options during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'bv_' ) . '%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only plugin-prefixed user metadata during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'bv_' ) . '%'
		)
	);

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		$table = $wpdb->prefix . 'actionscheduler_actions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema existence check.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists === $table ) {
			$like = $wpdb->esc_like( 'bv_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler does not expose prefix-based unscheduling.
			$hooks = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT hook FROM %i WHERE hook LIKE %s',
					$table,
					$like
				)
			);
			if ( is_array( $hooks ) ) {
				foreach ( $hooks as $hook ) {
					as_unschedule_all_actions( $hook );
				}
			}
		}
	}
}

coderembassy_bvm_uninstall_cleanup();
