<?php
/**
 * Job scheduling coordinator.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Jobs;

use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Engine\BulkEditor;
use CoderEmbassy\BulkVariationsManager\Repository\RollbackRepository;
use CoderEmbassy\BulkVariationsManager\Services\ActivityRecorder;
use CoderEmbassy\BulkVariationsManager\Services\ConcurrencyLock;

class JobManager {
	public const ACTION_HOOK = 'coderembassy_bvm_process_chunk';

	public const RETENTION_HOOK = 'coderembassy_bvm_retention_cleanup';

	public function __construct(
		private JobRepositoryInterface $jobs,
		private ConcurrencyLock $lock,
		private BulkUpdateJob $bulk_update,
		private GenerateJob $generate,
		private ImportJob $import,
		private RollbackJob $rollback,
		private StagedExecutionJob $staged_execution,
		private StagedRevertJob $staged_revert,
		private ActivityRecorder $activity,
		private BulkEditor $editor,
		private RollbackRepository $rollback_repository
	) {
		unset( $this->lock, $this->activity, $this->editor, $this->rollback_repository );
	}

	/**
	 * @param array<int, array<int, array<string, mixed>>> $chunks
	 */
	public function dispatch( int $job_id, array $chunks ): void {
		update_option( $this->optionKey( $job_id, 'chunks' ), $chunks, false );
		update_option( $this->optionKey( $job_id, 'total' ), count( $chunks ), false );
		update_option( $this->optionKey( $job_id, 'current_chunk' ), 0, false );

		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) ) {
			return;
		}

		$review_status = (string) ( $job['review_status'] ?? ( $job['meta']['review_status'] ?? 'none' ) );
		if ( 'pending_review' === $review_status ) {
			$this->jobs->updateStatus( $job_id, 'preview' );
			return;
		}

		$this->jobs->updateStatus( $job_id, 'running' );
		$enqueue = as_enqueue_async_action( self::ACTION_HOOK, array( $job_id, 0 ), 'coderembassy-bulk-variations-manager' );
		if ( (int) $enqueue <= 0 ) {
			$this->jobs->updateStatus(
				$job_id,
				'failed',
				array(
					'error_log' => 'Unable to enqueue background job.',
				)
			);
		}
	}

	public function cancel( int $job_id ): bool {
		$this->jobs->setControl( $job_id, 'cancelled' );
		$this->cleanupChunkOptions( $job_id );
		$this->jobs->updateStatus(
			$job_id,
			'cancelled',
			array(
				'completed_at' => current_time( 'mysql' ),
			)
		);
		return true;
	}

	public function discard( int $job_id ): bool {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) || ! $this->isDiscardable( $job ) ) {
			return false;
		}
		$this->cleanupChunkOptions( $job_id );
		$meta                     = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
		$meta['review_status']    = 'discarded';
		$this->jobs->updateStatus(
			$job_id,
			'cancelled',
			array(
				'completed_at' => current_time( 'mysql' ),
				'meta'         => wp_json_encode( $meta ),
			)
		);
		return true;
	}

	/**
	 * @param array<string, mixed> $job
	 */
	public function isDiscardable( array $job ): bool {
		$status = (string) ( $job['status'] ?? '' );
		$review = (string) ( $job['review_status'] ?? ( $job['meta']['review_status'] ?? '' ) );
		return 'preview' === $status && 'pending_review' === $review;
	}

	public function processChunk( int $job_id, int $chunk_index ): void {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) ) {
			return;
		}
		$control = (string) ( $job['control'] ?? $job['status'] ?? 'queued' );
		if ( 'paused' === $control || 'paused' === (string) ( $job['status'] ?? '' ) ) {
			as_schedule_single_action( time() + 30, self::ACTION_HOOK, array( $job_id, $chunk_index ), 'coderembassy-bulk-variations-manager' );
			return;
		}

		$chunks = get_option( $this->optionKey( $job_id, 'chunks' ), array() );
		if ( ! is_array( $chunks ) || ! isset( $chunks[ $chunk_index ] ) || ! is_array( $chunks[ $chunk_index ] ) ) {
			return;
		}

		$result    = $this->runChunkWorker( (string) ( $job['type'] ?? 'bulk_edit' ), $job_id, $chunks[ $chunk_index ] );
		$errors    = $result['errors'] ?? array();
		$processed = (int) ( $result['processed'] ?? 0 );

		if ( ! empty( $errors ) ) {
			$this->jobs->updateStatus(
				$job_id,
				'failed',
				array(
					'error_log' => implode( '; ', array_map( 'strval', $errors ) ),
				)
			);
			return;
		}

		$total_processed = (int) ( $job['processed'] ?? 0 ) + $processed;
		$total_chunks    = (int) get_option( $this->optionKey( $job_id, 'total' ), count( $chunks ) );
		if ( $chunk_index + 1 < $total_chunks ) {
			update_option( $this->optionKey( $job_id, 'current_chunk' ), $chunk_index + 1 );
			as_enqueue_async_action( self::ACTION_HOOK, array( $job_id, $chunk_index + 1 ), 'coderembassy-bulk-variations-manager' );
			return;
		}

		$this->markJobComplete( $job_id, $total_processed, $job );
	}

	public function resumeAfterApproval( int $job_id ): bool {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) ) {
			return false;
		}
		$chunks = get_option( $this->optionKey( $job_id, 'chunks' ), array() );
		if ( ! is_array( $chunks ) ) {
			$chunks = array();
		}

		$total_items = (int) ( $job['total_items'] ?? 0 );
		if ( $total_items > 25 ) {
			$meta                  = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
			$meta['review_status'] = 'applied';
			$this->jobs->updateStatus(
				$job_id,
				(string) ( $job['status'] ?? 'queued' ),
				array( 'meta' => wp_json_encode( $meta ) )
			);
			$this->dispatch( $job_id, $chunks );
			return true;
		}

		$meta                  = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
		$meta['review_status'] = 'applied';
		$started_at            = (string) ( $job['started_at'] ?? '' );
		$this->jobs->updateStatus(
			$job_id,
			'running',
			array(
				'started_at' => '' === $started_at ? current_time( 'mysql' ) : $started_at,
				'meta'       => wp_json_encode( $meta ),
			)
		);
		$processed = 0;
		$errors    = array();
		foreach ( $chunks as $chunk ) {
			if ( ! is_array( $chunk ) ) {
				continue;
			}
			$result    = $this->runChunkWorker( (string) ( $job['type'] ?? 'bulk_edit' ), $job_id, $chunk );
			$processed += (int) ( $result['processed'] ?? 0 );
			$errors     = array_merge( $errors, $result['errors'] ?? array() );
		}

		if ( ! empty( $errors ) ) {
			$this->jobs->updateStatus(
				$job_id,
				'failed',
				array( 'error_log' => implode( '; ', array_map( 'strval', $errors ) ) )
			);
			return false;
		}

		$this->markJobComplete( $job_id, $processed, $this->jobs->get( $job_id ) ?? $job );
		$this->cleanupChunkOptions( $job_id );
		return true;
	}

	/**
	 * @param array<string, mixed>|null $job Latest job row.
	 */
	private function markJobComplete( int $job_id, int $processed, ?array $job ): void {
		$meta                  = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
		$meta['review_status'] = 'applied';
		$total                 = max( 1, (int) ( $job['total_items'] ?? 0 ) );
		$progress              = min( 100, (int) round( ( $processed / $total ) * 100 ) );
		$started_at            = (string) ( $job['started_at'] ?? '' );

		$this->jobs->updateStatus(
			$job_id,
			'complete',
			array(
				'processed'    => $processed,
				'progress'     => $progress,
				'completed_at' => current_time( 'mysql' ),
				'started_at'   => '' === $started_at ? current_time( 'mysql' ) : $started_at,
				'meta'         => wp_json_encode( $meta ),
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	private function runChunkWorker( string $type, int $job_id, array $rows ): array {
		return match ( $type ) {
			'import'   => $this->import->run( $job_id, $rows ),
			'generate' => $this->generate->run( $job_id, $rows ),
			'rollback' => $this->rollback->run( $job_id, $rows ),
			default    => $this->bulk_update->run( $job_id, $rows ),
		};
	}

	private function cleanupChunkOptions( int $job_id ): void {
		delete_option( $this->optionKey( $job_id, 'chunks' ) );
		delete_option( $this->optionKey( $job_id, 'total' ) );
		delete_option( $this->optionKey( $job_id, 'current_chunk' ) );
	}

	private function optionKey( int $job_id, string $suffix ): string {
		return 'coderembassy_bvm_job_' . $job_id . '_' . $suffix;
	}
}
