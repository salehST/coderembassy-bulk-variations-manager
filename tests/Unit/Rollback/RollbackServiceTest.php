<?php
/**
 * RollbackService unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Rollback
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Rollback;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Jobs\JobManager;
use CoderEmbassy\BulkVariationsManager\Jobs\RollbackJob;
use CoderEmbassy\BulkVariationsManager\Repository\RollbackRepository;
use CoderEmbassy\BulkVariationsManager\Rollback\RollbackService;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Rollback\RollbackService
 */
class RollbackServiceTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'current_time' )->justReturn( '2026-05-18 12:00:00' );
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof WP_Error
		);
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Preview returns dry-run rows from inverse deltas.
	 *
	 * @return void
	 */
	public function test_preview_returns_dry_run_rows(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn(
			array(
				'id'     => 1,
				'status' => 'complete',
			)
		);

		$rollback_repo = $this->createMock( RollbackRepository::class );
		$rollback_repo->method( 'isRolledBack' )->willReturn( false );
		$rollback_repo->method( 'getInverseDeltas' )->willReturn(
			array(
				array(
					'object_id'   => 50,
					'object_type' => 'variation',
					'field'       => '_price',
					'old_value'   => '15.00',
					'new_value'   => '10.00',
				),
			)
		);

		$service = new RollbackService(
			$jobs,
			$rollback_repo,
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackJob::class )
		);

		$result = $service->preview( 1 );
		$this->assertIsArray( $result );
		$this->assertSame( 50, $result[0]['variation_id'] );
		$this->assertSame( '10.00', $result[0]['will_revert_to'] );
		$this->assertSame( '15.00', $result[0]['current_value'] );
	}

	/**
	 * Second rollback is rejected (idempotent).
	 *
	 * @return void
	 */
	public function test_rollback_rejects_already_rolled_back(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn(
			array(
				'id'     => 2,
				'status' => 'rolled_back',
			)
		);

		$rollback_repo = $this->createMock( RollbackRepository::class );
		$rollback_repo->method( 'isRolledBack' )->willReturn( true );

		$service = new RollbackService(
			$jobs,
			$rollback_repo,
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackJob::class )
		);

		$result = $service->rollback( 2 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'coderembassy_bvm_already_rolled_back', $result->get_error_code() );
	}

	/**
	 * Small jobs run synchronously and mark source rolled_back.
	 *
	 * @return void
	 */
	public function test_rollback_sync_marks_source_rolled_back(): void {
		$inverse = array(
			array(
				'object_id' => 10,
				'field'     => '_price',
				'old_value' => '20.00',
				'new_value' => '18.00',
			),
		);

		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn(
			array(
				'id'     => 3,
				'status' => 'complete',
			)
		);
		$jobs->expects( $this->once() )->method( 'create' )->willReturn( 99 );
		$update_calls = 0;
		$jobs->expects( $this->exactly( 2 ) )
			->method( 'updateStatus' )
			->willReturnCallback(
				function ( int $job_id, string $status, array $extra ) use ( &$update_calls ): bool {
					$update_calls++;
					$this->assertSame( 99, $job_id );
					if ( 1 === $update_calls ) {
						$this->assertSame( 'running', $status );
						$this->assertSame( '2026-05-18 12:00:00', $extra['started_at'] ?? '' );
						return true;
					}

					$this->assertSame( 'complete', $status );
					$this->assertSame( 1, (int) ( $extra['processed'] ?? 0 ) );
					$this->assertSame( 100, (int) ( $extra['progress'] ?? 0 ) );
					$this->assertSame( '2026-05-18 12:00:00', $extra['completed_at'] ?? '' );
					return true;
				}
			);

		$rollback_repo = $this->createMock( RollbackRepository::class );
		$rollback_repo->method( 'isRolledBack' )->willReturn( false );
		$rollback_repo->method( 'getInverseDeltas' )->willReturn( $inverse );
		$rollback_repo->expects( $this->once() )->method( 'markRolledBack' )->with( 3 );

		$rollback_job = $this->createMock( RollbackJob::class );
		$rollback_job->expects( $this->once() )->method( 'run' )->willReturn(
			array(
				'processed' => 1,
				'errors'    => array(),
			)
		);

		$job_manager = $this->createMock( JobManager::class );
		$job_manager->expects( $this->never() )->method( 'dispatch' );

		$service = new RollbackService( $jobs, $rollback_repo, $job_manager, $rollback_job );
		$result  = $service->rollback( 3 );

		$this->assertSame( 99, $result );
	}

	/**
	 * Large jobs dispatch via JobManager.
	 *
	 * @return void
	 */
	public function test_rollback_async_dispatches_job_manager(): void {
		$inverse = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$inverse[] = array(
				'object_id' => $i + 1,
				'field'     => '_price',
				'old_value' => '2.00',
				'new_value' => '1.00',
			);
		}

		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn(
			array(
				'id'     => 4,
				'status' => 'complete',
			)
		);
		$jobs->method( 'create' )->willReturn( 200 );

		$rollback_repo = $this->createMock( RollbackRepository::class );
		$rollback_repo->method( 'isRolledBack' )->willReturn( false );
		$rollback_repo->method( 'getInverseDeltas' )->willReturn( $inverse );
		$rollback_repo->expects( $this->never() )->method( 'markRolledBack' );

		$job_manager = $this->createMock( JobManager::class );
		$job_manager->expects( $this->once() )->method( 'dispatch' )->with( 200, $this->anything() );

		$rollback_job = $this->createMock( RollbackJob::class );
		$rollback_job->expects( $this->never() )->method( 'run' );

		$service = new RollbackService( $jobs, $rollback_repo, $job_manager, $rollback_job );
		$result  = $service->rollback( 4 );

		$this->assertSame( 200, $result );
	}
}
