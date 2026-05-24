<?php
/**
 * RollbackRepository unit tests.
 *
 * @package BulkVariations\Tests\Unit\Repository
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Repository;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Repository\RollbackRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Repository\RollbackRepository
 */
class RollbackRepositoryTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'current_time' )->justReturn( '2026-05-18 12:00:00' );
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
	 * getInverseDeltas swaps old/new values.
	 *
	 * @return void
	 */
	public function test_get_inverse_deltas_swaps_values(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'getChanges' )->with( 5 )->willReturn(
			array(
				array(
					'id'          => 1,
					'object_type' => 'variation',
					'object_id'   => 100,
					'field'       => '_price',
					'old_value'   => '10.00',
					'new_value'   => '12.00',
				),
			)
		);

		$repo    = new RollbackRepository( $jobs );
		$inverse = $repo->getInverseDeltas( 5 );

		$this->assertCount( 1, $inverse );
		$this->assertSame( '12.00', $inverse[0]['old_value'] );
		$this->assertSame( '10.00', $inverse[0]['new_value'] );
		$this->assertSame( 100, $inverse[0]['object_id'] );
	}

	/**
	 * markRolledBack updates job status.
	 *
	 * @return void
	 */
	public function test_mark_rolled_back_updates_status(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->expects( $this->once() )
			->method( 'updateStatus' )
			->with(
				9,
				'rolled_back',
				$this->callback(
					static function ( array $extra ): bool {
						return isset( $extra['completed_at'] );
					}
				)
			)
			->willReturn( true );

		$repo = new RollbackRepository( $jobs );
		$this->assertTrue( $repo->markRolledBack( 9 ) );
	}

	/**
	 * isRolledBack reflects job status.
	 *
	 * @return void
	 */
	public function test_is_rolled_back(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturnOnConsecutiveCalls(
			array( 'id' => 1, 'status' => 'rolled_back' ),
			array( 'id' => 2, 'status' => 'complete' ),
			null
		);

		$repo = new RollbackRepository( $jobs );
		$this->assertTrue( $repo->isRolledBack( 1 ) );
		$this->assertFalse( $repo->isRolledBack( 2 ) );
		$this->assertFalse( $repo->isRolledBack( 3 ) );
	}
}
