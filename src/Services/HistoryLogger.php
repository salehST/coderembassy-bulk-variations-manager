<?php
/**
 * Buffered job change logger.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Services;

class HistoryLogger {
	/**
	 * @var array<int, array<string, mixed>>
	 */
	private array $buffer = array();

	public function pendingCount(): int {
		return count( $this->buffer );
	}

	public function recordChange(
		int $job_id,
		string $object_type,
		int $object_id,
		string $field,
		?string $old_value,
		?string $new_value
	): void {
		$this->buffer[] = array(
			'job_id'      => $job_id,
			'object_type' => sanitize_text_field( $object_type ),
			'object_id'   => $object_id,
			'field'       => sanitize_text_field( $field ),
			'old_value'   => $old_value,
			'new_value'   => $new_value,
			'applied_at'  => current_time( 'mysql' ),
		);
	}

	public function flush(): int {
		if ( empty( $this->buffer ) ) {
			return 0;
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'bv_job_changes';
		$values = array();
		foreach ( $this->buffer as $row ) {
			$values[] = $wpdb->prepare(
				'(%d,%s,%d,%s,%s,%s,%s)',
				$row['job_id'],
				$row['object_type'],
				$row['object_id'],
				$row['field'],
				$row['old_value'],
				$row['new_value'],
				$row['applied_at']
			);
		}

		$sql      = 'INSERT INTO ' . $table . ' (job_id, object_type, object_id, field, old_value, new_value, applied_at) VALUES ';
		$sql     .= implode( ',', $values );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin table is internal and every value tuple was prepared above.
		$inserted = (int) $wpdb->query( $sql );
		$this->buffer = array();
		return $inserted;
	}
}
