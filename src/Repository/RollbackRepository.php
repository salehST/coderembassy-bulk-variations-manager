<?php
/**
 * Rollback helper repository.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Repository;

use BulkVariations\Contracts\JobRepositoryInterface;

class RollbackRepository {
	public function __construct( private JobRepositoryInterface $jobs ) {
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getInverseDeltas( int $job_id ): array {
		$changes = $this->jobs->getChanges( $job_id );
		$out     = array();
		foreach ( $changes as $change ) {
			$out[] = array(
				'id'          => (int) ( $change['id'] ?? 0 ),
				'object_type' => (string) ( $change['object_type'] ?? 'variation' ),
				'object_id'   => (int) ( $change['object_id'] ?? 0 ),
				'field'       => (string) ( $change['field'] ?? '' ),
				'old_value'   => (string) ( $change['new_value'] ?? '' ),
				'new_value'   => (string) ( $change['old_value'] ?? '' ),
			);
		}
		return $out;
	}

	public function markRolledBack( int $job_id ): bool {
		return $this->jobs->updateStatus(
			$job_id,
			'rolled_back',
			array(
				'completed_at' => current_time( 'mysql' ),
			)
		);
	}

	public function isRolledBack( int $job_id ): bool {
		$job = $this->jobs->get( $job_id );
		return is_array( $job ) && 'rolled_back' === ( $job['status'] ?? '' );
	}
}

