<?php
/**
 * Rollback service.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Rollback;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Jobs\RollbackJob;
use BulkVariations\Repository\RollbackRepository;
use WP_Error;

class RollbackService {
	public function __construct(
		private JobRepositoryInterface $jobs,
		private RollbackRepository $rollback_repository,
		private JobManager $manager,
		private RollbackJob $rollback_job
	) {
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function preview( int $source_job_id ): array {
		$inverse = $this->rollback_repository->getInverseDeltas( $source_job_id );
		$rows    = array();
		foreach ( $inverse as $delta ) {
			$rows[] = array(
				'variation_id'  => (int) ( $delta['object_id'] ?? 0 ),
				'field'         => (string) ( $delta['field'] ?? '' ),
				'current_value' => (string) ( $delta['old_value'] ?? '' ),
				'will_revert_to'=> (string) ( $delta['new_value'] ?? '' ),
			);
		}
		return $rows;
	}

	/**
	 * @return int|WP_Error
	 */
	public function rollback( int $source_job_id ) {
		$source = $this->jobs->get( $source_job_id );
		if ( ! is_array( $source ) ) {
			return new WP_Error( 'bv_source_job_not_found', 'Source job not found.', array( 'status' => 404 ) );
		}

		if ( 'rolled_back' === ( $source['status'] ?? '' ) || $this->rollback_repository->isRolledBack( $source_job_id ) ) {
			return new WP_Error( 'bv_already_rolled_back', 'This job has already been rolled back.', array( 'status' => 409 ) );
		}

		$inverse = $this->rollback_repository->getInverseDeltas( $source_job_id );
		$new_id  = $this->jobs->create(
			array(
				'type'        => 'rollback',
				'status'      => 'queued',
				'total_items' => count( $inverse ),
				'meta'        => array(
					'source_job_id' => $source_job_id,
					'source'        => 'rollback',
				),
			)
		);

		if ( count( $inverse ) >= 50 ) {
			$this->manager->dispatch( $new_id, array_chunk( $inverse, 25 ) );
			return $new_id;
		}

		$this->jobs->updateStatus( $new_id, 'running', array( 'started_at' => current_time( 'mysql' ) ) );
		$result = $this->rollback_job->run( $new_id, $inverse );
		$errors = $result['errors'] ?? array();
		if ( ! empty( $errors ) ) {
			$this->jobs->updateStatus(
				$new_id,
				'failed',
				array( 'error_log' => implode( '; ', array_map( 'strval', $errors ) ) )
			);
			return $new_id;
		}

		$processed = (int) ( $result['processed'] ?? 0 );
		$total     = count( $inverse );
		$progress  = $total > 0
			? min( 100, (int) round( ( $processed / $total ) * 100 ) )
			: 100;

		$this->jobs->updateStatus(
			$new_id,
			'complete',
			array(
				'processed'    => $processed,
				'progress'     => $progress,
				'completed_at' => current_time( 'mysql' ),
			)
		);
		$this->rollback_repository->markRolledBack( $source_job_id );
		return $new_id;
	}
}

