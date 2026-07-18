<?php
/**
 * Placeholder staged revert worker.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Jobs;

class StagedRevertJob {
	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	public function run( int $job_id, array $rows ): array {
		unset( $job_id );
		return array(
			'processed' => count( $rows ),
			'errors'    => array(),
		);
	}
}

