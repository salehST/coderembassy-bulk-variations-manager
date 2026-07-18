<?php
/**
 * Job repository implementation.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Repository;

use BulkVariations\Contracts\JobRepositoryInterface;

class JobRepository implements JobRepositoryInterface {
	private const ALLOWED_CONTROL = array( 'queued', 'running', 'paused', 'cancelled' );

	private function jobsTable(): string {
		global $wpdb;
		return $wpdb->prefix . 'bv_jobs';
	}

	private function changesTable(): string {
		global $wpdb;
		return $wpdb->prefix . 'bv_job_changes';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function create( array $payload ): int {
		global $wpdb;
		$meta = $payload['meta'] ?? array();
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$data = array(
			'type'        => sanitize_text_field( (string) ( $payload['type'] ?? 'bulk_edit' ) ),
			'status'      => sanitize_text_field( (string) ( $payload['status'] ?? 'queued' ) ),
			'progress'    => (int) ( $payload['progress'] ?? 0 ),
			'total_items' => (int) ( $payload['total_items'] ?? 0 ),
			'processed'   => (int) ( $payload['processed'] ?? 0 ),
			'created_by'  => (int) get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
			'meta'        => wp_json_encode( $meta ),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to the plugin-owned jobs table.
		$wpdb->insert( $this->jobsTable(), $data, array( '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	public function get( int $job_id ): ?array {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->jobsTable(), $job_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; job state must be current.
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( empty( $row ) || ! is_array( $row ) ) {
			return null;
		}
		$meta = json_decode( (string) ( $row['meta'] ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'type'          => (string) ( $row['type'] ?? '' ),
			'status'        => (string) ( $row['status'] ?? '' ),
			'progress'      => (int) ( $row['progress'] ?? 0 ),
			'total_items'   => (int) ( $row['total_items'] ?? 0 ),
			'processed'     => (int) ( $row['processed'] ?? 0 ),
			'created_by'    => (int) ( $row['created_by'] ?? 0 ),
			'created_at'    => (string) ( $row['created_at'] ?? '' ),
			'started_at'    => $row['started_at'] ?? null,
			'completed_at'  => $row['completed_at'] ?? null,
			'error_log'     => $row['error_log'] ?? null,
			'meta'          => $meta,
			'source'        => (string) ( $meta['source'] ?? '' ),
			'review_status' => (string) ( $meta['review_status'] ?? 'none' ),
			'control'       => (string) ( $meta['control'] ?? ( $row['status'] ?? 'queued' ) ),
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public function count( array $filters = array() ): int {
		global $wpdb;
		$status = isset( $filters['status'] ) ? (string) $filters['status'] : '';
		if ( '' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; live count is required.
			$value = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->jobsTable() ) );
			return (int) $value;
		}
		$sql   = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->jobsTable(), $status );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; live count is required.
		$value = $wpdb->get_var( $sql );
		return (int) $value;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getChanges( int $job_id ): array {
		global $wpdb;
		$sql  = $wpdb->prepare(
			'SELECT id, object_type, object_id, field, old_value, new_value, applied_at FROM %i WHERE job_id = %d ORDER BY id ASC',
			$this->changesTable(),
			$job_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; job changes must be current.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $change
	 */
	public function addChange( int $job_id, array $change ): bool {
		$object_id = (int) ( $change['object_id'] ?? 0 );
		$field     = (string) ( $change['field'] ?? '' );
		if ( $object_id <= 0 || '' === $field ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to the plugin-owned job changes table.
		return false !== $wpdb->insert(
			$this->changesTable(),
			array(
				'job_id'      => $job_id,
				'object_type' => (string) ( $change['object_type'] ?? 'variation' ),
				'object_id'   => $object_id,
				'field'       => $field,
				'old_value'   => (string) ( $change['old_value'] ?? '' ),
				'new_value'   => (string) ( $change['new_value'] ?? '' ),
				'applied_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public function updateStatus( int $job_id, string $status, array $extra = array() ): bool {
		global $wpdb;
		$data = array_merge(
			array(
				'status' => sanitize_text_field( $status ),
			),
			$extra
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes to the plugin-owned jobs table.
		return false !== $wpdb->update( $this->jobsTable(), $data, array( 'id' => $job_id ) );
	}

	public function setControl( int $job_id, string $control ): bool {
		if ( ! in_array( $control, self::ALLOWED_CONTROL, true ) ) {
			return false;
		}
		$job = $this->get( $job_id );
		if ( null === $job ) {
			return false;
		}
		$meta            = $job['meta'] ?? array();
		$meta['control'] = $control;
		return $this->updateStatus(
			$job_id,
			(string) ( $job['status'] ?? 'queued' ),
			array(
				'meta' => wp_json_encode( $meta ),
			)
		);
	}
}
