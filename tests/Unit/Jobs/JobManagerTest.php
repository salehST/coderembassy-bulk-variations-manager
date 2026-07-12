<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * JobManager unit tests.
 *
 * @package BulkVariations\Tests\Unit\Jobs
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Jobs;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\BulkEditor;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationWriter;
use BulkVariations\Jobs\BulkUpdateJob;
use BulkVariations\ImportExport\ImportValidator;
use BulkVariations\Jobs\GenerateJob;
use BulkVariations\Jobs\ImportJob;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Jobs\RollbackJob;
use BulkVariations\Jobs\StagedExecutionJob;
use BulkVariations\Jobs\StagedRevertJob;
use BulkVariations\Repository\RollbackRepository;
use BulkVariations\Services\ActivityRecorder;
use BulkVariations\Services\ConcurrencyLock;
use BulkVariations\Services\HistoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Jobs\JobManager
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

class JobManagerTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options = array();

		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class() {
			public string $prefix = 'wp_';
			public string $posts = 'wp_posts';
			public string $postmeta = 'wp_postmeta';

			/**
			 * @param mixed ...$args Update arguments (ignored).
			 * @return int
			 */
			public function update( ...$args ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				unset( $args );
				return 1;
			}
		};

		if ( ! defined( 'BV_PLUGIN_PATH' ) ) {
			define( 'BV_PLUGIN_PATH', dirname( __DIR__, 3 ) . '/' );
		}

		Functions\when( 'update_option' )->alias(
			function ( string $key, $value ) {
				$this->options[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				return $this->options[ $key ] ?? $default;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $key ) {
				unset( $this->options[ $key ] );
				return true;
			}
		);
		Functions\when( 'current_time' )->justReturn( '2026-05-18 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $data ) => json_encode( $data ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);

		$GLOBALS['bv_test_as_queue']           = array();
		$GLOBALS['bv_test_as_enqueue_result'] = 1;
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
	 * Pending review stores chunks but does not enqueue.
	 *
	 * @return void
	 */
	public function test_dispatch_pending_review_stores_without_enqueue(): void {
		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'             => 10,
				'type'           => 'bulk_edit',
				'meta'           => array( 'review_status' => 'pending_review' ),
				'review_status'  => 'pending_review',
				'status'         => 'queued',
				'control'        => 'queued',
			)
		);
		$repo->expects( $this->atLeastOnce() )->method( 'updateStatus' );

		$manager = $this->make_manager( $repo );
		$manager->dispatch( 10, array( array( 'variation_id' => 1 ) ) );

		$this->assertArrayHasKey( 'bv_job_10_chunks', $this->options );
		$this->assertEmpty( $GLOBALS['bv_test_as_queue'] );
	}

	/**
	 * Normal dispatch enqueues first chunk.
	 *
	 * @return void
	 */
	public function test_dispatch_enqueues_first_chunk(): void {
		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'            => 11,
				'type'          => 'bulk_edit',
				'meta'          => array( 'review_status' => 'none' ),
				'review_status' => 'none',
				'status'        => 'queued',
				'control'       => 'queued',
			)
		);
		$repo->expects( $this->atLeastOnce() )->method( 'updateStatus' );

		$manager = $this->make_manager( $repo );
		$manager->dispatch( 11, array( array( 'variation_id' => 1 ) ) );

		$queue = array_values( $GLOBALS['bv_test_as_queue'] );
		$this->assertCount( 1, $queue );
		$this->assertSame( JobManager::ACTION_HOOK, $queue[0]['hook'] );
		$this->assertSame( array( 11, 0 ), $queue[0]['args'] );
	}

	/**
	 * Cancel clears chunk options.
	 *
	 * @return void
	 */
	public function test_cancel_cleans_up_options(): void {
		$this->options['bv_job_12_chunks']        = array( array() );
		$this->options['bv_job_12_total']         = 1;
		$this->options['bv_job_12_current_chunk'] = 0;

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'     => 12,
				'type'   => 'bulk_edit',
				'meta'   => array(),
				'status' => 'running',
				'control' => 'running',
			)
		);
		$repo->method( 'setControl' )->willReturn( true );
		$repo->expects( $this->once() )->method( 'updateStatus' )->with( 12, 'cancelled', $this->anything() );

		$manager = $this->make_manager( $repo );
		$this->assertTrue( $manager->cancel( 12 ) );
		$this->assertArrayNotHasKey( 'bv_job_12_chunks', $this->options );
	}

	/**
	 * Dispatch marks job failed when Action Scheduler rejects enqueue.
	 *
	 * @return void
	 */
	public function test_dispatch_marks_failed_when_enqueue_rejected(): void {
		$GLOBALS['bv_test_as_enqueue_result'] = 0;

		$statuses = array();

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'            => 14,
				'type'          => 'bulk_edit',
				'meta'          => array( 'review_status' => 'none' ),
				'review_status' => 'none',
				'status'        => 'queued',
				'control'       => 'queued',
			)
		);
		$repo->method( 'updateStatus' )->willReturnCallback(
			static function ( int $id, string $status, array $extra = array() ) use ( &$statuses ): bool {
				unset( $id );
				$statuses[] = array(
					'status' => $status,
					'extra'  => $extra,
				);
				return true;
			}
		);

		$manager = $this->make_manager( $repo );
		$manager->dispatch( 14, array( array( 'variation_id' => 1 ) ) );

		$this->assertContains( 'failed', array_column( $statuses, 'status' ) );
		$failed = array_values(
			array_filter(
				$statuses,
				static fn( array $row ): bool => 'failed' === $row['status']
			)
		);
		$this->assertNotEmpty( $failed );
		$this->assertStringContainsString(
			'Unable to enqueue background job',
			(string) ( $failed[0]['extra']['error_log'] ?? '' )
		);
	}

	/**
	 * Discard pending-review preview cancels job and clears chunk options.
	 *
	 * @return void
	 */
	public function test_discard_pending_review_cleans_options_and_cancels(): void {
		$this->options['bv_job_3_chunks']        = array( array( 'variation_id' => 1 ) );
		$this->options['bv_job_3_total']         = 1;
		$this->options['bv_job_3_current_chunk'] = 0;

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'            => 3,
				'type'          => 'bulk_edit',
				'meta'          => array( 'review_status' => 'pending_review' ),
				'review_status' => 'pending_review',
				'status'        => 'preview',
				'control'       => 'queued',
			)
		);
		$repo->expects( $this->once() )->method( 'updateStatus' )->with(
			3,
			'cancelled',
			$this->callback(
				static function ( array $extra ): bool {
					return isset( $extra['completed_at'] );
				}
			)
		);

		$manager = $this->make_manager( $repo );
		$this->assertTrue( $manager->discard( 3 ) );
		$this->assertArrayNotHasKey( 'bv_job_3_chunks', $this->options );
		$this->assertTrue( $manager->isDiscardable(
			array(
				'status'        => 'preview',
				'review_status' => 'pending_review',
				'meta'          => array( 'review_status' => 'pending_review' ),
			)
		) );
		$this->assertFalse( $manager->isDiscardable(
			array(
				'status'        => 'complete',
				'review_status' => 'none',
				'meta'          => array(),
			)
		) );
	}

	/**
	 * Paused chunk re-schedules instead of processing.
	 *
	 * @return void
	 */
	public function test_process_chunk_paused_requeues(): void {
		$this->options['bv_job_13_chunks'] = array( array( array( 'variation_id' => 5 ) ) );
		$this->options['bv_job_13_total']  = 1;

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'      => 13,
				'type'    => 'bulk_edit',
				'meta'    => array(),
				'status'  => 'paused',
				'control' => 'paused',
			)
		);

		$manager = $this->make_manager( $repo );
		$GLOBALS['bv_test_as_queue'] = array();
		$manager->processChunk( 13, 0 );

		/** @var array<int, array{hook: string, args: array<int, mixed>, time: int|null, group?: string}> $queue */
		$queue = $GLOBALS['bv_test_as_queue'];
		$this->assertCount( 1, $queue );
		$this->assertNotNull( $queue[0]['time'] );
	}

	/**
	 * Small approved bulk_edit jobs process synchronously without Action Scheduler.
	 *
	 * @return void
	 */
	public function test_resume_after_approval_sync_skips_enqueue_for_small_editor_job(): void {
		$this->options['bv_job_15_chunks']        = array(
			array(
				array(
					'variation_id' => 20,
					'sku'          => 'SA0550',
				),
			),
		);
		$this->options['bv_job_15_total']         = 1;
		$this->options['bv_job_15_current_chunk'] = 0;

		$statuses = array();

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'            => 15,
				'type'          => 'bulk_edit',
				'meta'          => array( 'review_status' => 'pending_review' ),
				'review_status' => 'pending_review',
				'status'        => 'preview',
				'control'       => 'queued',
				'total_items'   => 1,
				'processed'     => 0,
			)
		);
		$repo->method( 'updateStatus' )->willReturnCallback(
			static function ( int $id, string $status, array $extra = array() ) use ( &$statuses ): bool {
				unset( $id );
				$statuses[] = array(
					'status' => $status,
					'extra'  => $extra,
				);
				return true;
			}
		);

		$bulk = $this->createMock( BulkUpdateJob::class );
		$bulk->expects( $this->once() )
			->method( 'run' )
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$GLOBALS['bv_test_as_queue'] = array();

		$manager = $this->make_manager( $repo, $bulk );
		$this->assertTrue( $manager->resumeAfterApproval( 15 ) );
		$this->assertEmpty( $GLOBALS['bv_test_as_queue'] );
		$this->assertContains( 'complete', array_column( $statuses, 'status' ) );
	}

	/**
	 * Small approved import jobs process synchronously without Action Scheduler.
	 *
	 * @return void
	 */
	public function test_resume_after_approval_sync_skips_enqueue_for_small_import_job(): void {
		$this->options['bv_job_16_chunks']        = array(
			array(
				array(
					'variation_id'  => 19,
					'product_id'    => 18,
					'sku'           => 'CSV-TEST-19',
					'regular_price' => '599',
				),
			),
		);
		$this->options['bv_job_16_total']         = 1;
		$this->options['bv_job_16_current_chunk'] = 0;

		$statuses = array();

		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'            => 16,
				'type'          => 'import',
				'meta'          => array(
					'review_status' => 'pending_review',
					'product_id'    => 18,
				),
				'review_status' => 'pending_review',
				'status'        => 'preview',
				'control'       => 'queued',
				'total_items'   => 1,
				'processed'     => 0,
			)
		);
		$repo->method( 'updateStatus' )->willReturnCallback(
			static function ( int $id, string $status, array $extra = array() ) use ( &$statuses ): bool {
				unset( $id );
				$statuses[] = array(
					'status' => $status,
					'extra'  => $extra,
				);
				return true;
			}
		);

		$import = $this->createMock( ImportJob::class );
		$import->expects( $this->once() )
			->method( 'run' )
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$GLOBALS['bv_test_as_queue'] = array();

		$manager = $this->make_manager( $repo, null, $import );
		$this->assertTrue( $manager->resumeAfterApproval( 16 ) );
		$this->assertEmpty( $GLOBALS['bv_test_as_queue'] );
		$this->assertContains( 'complete', array_column( $statuses, 'status' ) );
	}

	/**
	 * @param JobRepositoryInterface $repo Repository mock.
	 * @param BulkUpdateJob|null     $bulk Optional bulk worker mock.
	 * @param ImportJob|null         $import Optional import worker mock.
	 * @return JobManager
	 */
	private function make_manager(
		JobRepositoryInterface $repo,
		?BulkUpdateJob $bulk = null,
		?ImportJob $import = null
	): JobManager {
		$lock     = new ConcurrencyLock();
		$history  = new HistoryLogger();
		$editor   = new BulkEditor( $repo, new VariationRepository(), $history );
		$activity = new ActivityRecorder();

		$writer    = new VariationWriter();
		$generator = new VariationGenerator( new VariationRepository(), writer: $writer );

		return new JobManager(
			$repo,
			$lock,
			$bulk ?? new BulkUpdateJob( $editor ),
			new GenerateJob( $repo, $generator ),
			$import ?? new ImportJob( $repo, new ImportValidator(), $writer, $editor ),
			new RollbackJob( $editor ),
			new StagedExecutionJob(),
			new StagedRevertJob(),
			$activity,
			$editor,
			new RollbackRepository( $repo )
		);
	}
}
