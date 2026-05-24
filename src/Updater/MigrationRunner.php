<?php
/**
 * DB migration runner.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Updater;

class MigrationRunner {
	private const OPTION_KEY = 'bv_db_version';

	public static function run(): void {
		$runner = new self();
		$runner->migrate();
	}

	public static function get_db_version(): string {
		return (string) get_option( self::OPTION_KEY, '0' );
	}

	public function migrate(): void {
		$current = self::get_db_version();
		$target  = $this->targetVersion();
		$this->createTables();
		if ( $current === $target ) {
			return;
		}
		update_option( self::OPTION_KEY, $target );
	}

	private function createTables(): void {
		global $wpdb;

		$upgrade_file = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
		if ( '' !== $upgrade_file && file_exists( $upgrade_file ) ) {
			require_once $upgrade_file;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$jobs_table      = $wpdb->prefix . 'bv_jobs';
		$changes_table   = $wpdb->prefix . 'bv_job_changes';

		dbDelta(
			"CREATE TABLE {$jobs_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				type varchar(50) NOT NULL DEFAULT 'bulk_edit',
				status varchar(50) NOT NULL DEFAULT 'queued',
				progress int(11) NOT NULL DEFAULT 0,
				total_items int(11) NOT NULL DEFAULT 0,
				processed int(11) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				started_at datetime NULL,
				completed_at datetime NULL,
				error_log longtext NULL,
				meta longtext NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY type (type)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$changes_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_id bigint(20) unsigned NOT NULL,
				object_type varchar(50) NOT NULL DEFAULT 'variation',
				object_id bigint(20) unsigned NOT NULL,
				field varchar(191) NOT NULL,
				old_value longtext NULL,
				new_value longtext NULL,
				applied_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY job_id (job_id),
				KEY object_id (object_id)
			) {$charset_collate};"
		);
	}

	private function targetVersion(): string {
		return '0001';
	}
}
