<?php
/**
 * Uninstall cleanup.
 *
 * @package BulkVariations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

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
	$sql = 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $table . '`';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query( $sql );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'bv_' ) . '%'
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'bv_' ) . '%'
	)
);

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	$table = $wpdb->prefix . 'actionscheduler_actions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists === $table ) {
		$like = $wpdb->esc_like( 'bv_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$hooks = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Action Scheduler table name validated above.
				"SELECT DISTINCT hook FROM `{$table}` WHERE hook LIKE %s",
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
