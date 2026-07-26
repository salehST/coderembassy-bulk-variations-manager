<?php
/**
 * Placeholder staged execution worker.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Jobs;

class StagedExecutionJob {
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

