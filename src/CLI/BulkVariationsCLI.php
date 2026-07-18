<?php
/**
 * WP-CLI helpers.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\CLI;

class BulkVariationsCLI {
	/**
	 * @param array<string, mixed> $assoc_args
	 */
	public static function is_dry_run( array $assoc_args ): bool {
		return ! empty( $assoc_args['dry-run'] );
	}

	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>
	 */
	public static function format_job_row( array $job ): array {
		return array(
			'id'       => (int) ( $job['id'] ?? 0 ),
			'type'     => (string) ( $job['type'] ?? '' ),
			'status'   => (string) ( $job['status'] ?? '' ),
			'progress' => (int) ( $job['progress'] ?? 0 ),
			'processed'=> (int) ( $job['processed'] ?? 0 ),
			'total'    => (int) ( $job['total_items'] ?? 0 ),
			'source'   => (string) ( $job['source'] ?? '' ),
			'created'  => (string) ( $job['created_at'] ?? '' ),
		);
	}
}

