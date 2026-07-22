<?php
/**
 * RollbackJob unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Jobs
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Jobs;

use CoderEmbassy\BulkVariationsManager\Engine\BulkEditor;
use CoderEmbassy\BulkVariationsManager\Jobs\RollbackJob;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Jobs\RollbackJob
 */
class RollbackJobTest extends TestCase {

	/**
	 * Inverse sale date meta deltas should be passed through as BulkEditor updates.
	 *
	 * @return void
	 */
	public function test_run_maps_sale_date_meta_delta_to_bulk_editor_row(): void {
		$rollback_ts = (string) strtotime( '2026-02-26 23:59:59' );

		$bulk_editor = $this->createMock( BulkEditor::class );
		$bulk_editor->expects( $this->once() )
			->method( 'processChunk' )
			->with(
				55,
				array(
					array(
						'variation_id' => 19,
						'sale_to'      => $rollback_ts,
					),
				)
			)
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$job = new RollbackJob( $bulk_editor );
		$result = $job->run(
			55,
			array(
				array(
					'object_id' => 19,
					'field'     => '_sale_price_dates_to',
					'new_value' => $rollback_ts,
				),
			)
		);

		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( array(), $result['errors'] );
	}

	/**
	 * Inverse attribute meta deltas pass through to BulkEditor.
	 *
	 * @return void
	 */
	public function test_run_maps_attribute_meta_delta_to_bulk_editor_row(): void {
		$bulk_editor = $this->createMock( BulkEditor::class );
		$bulk_editor->expects( $this->once() )
			->method( 'processChunk' )
			->with(
				12,
				array(
					array(
						'variation_id'       => 30,
						'attribute_pa_color' => 'red',
					),
				)
			)
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$job    = new RollbackJob( $bulk_editor );
		$result = $job->run(
			12,
			array(
				array(
					'object_id' => 30,
					'field'     => 'attribute_pa_color',
					'new_value' => 'red',
				),
			)
		);

		$this->assertSame( 1, $result['processed'] );
	}

	/**
	 * Regular price history deltas map to editor field names for BulkEditor.
	 *
	 * @return void
	 */
	public function test_run_maps_regular_price_meta_delta_to_editor_field(): void {
		$bulk_editor = $this->createMock( BulkEditor::class );
		$bulk_editor->expects( $this->once() )
			->method( 'processChunk' )
			->with(
				77,
				array(
					array(
						'variation_id'  => 19,
						'regular_price' => '501',
					),
				)
			)
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$job    = new RollbackJob( $bulk_editor );
		$result = $job->run(
			77,
			array(
				array(
					'object_id' => 19,
					'field'     => '_regular_price',
					'new_value' => '501',
				),
			)
		);

		$this->assertSame( 1, $result['processed'] );
	}

	/**
	 * Related rollback deltas for the same variation are grouped into one editor row.
	 *
	 * @return void
	 */
	public function test_run_groups_price_deltas_for_same_variation(): void {
		$bulk_editor = $this->createMock( BulkEditor::class );
		$bulk_editor->expects( $this->once() )
			->method( 'processChunk' )
			->with(
				91,
				array(
					array(
						'variation_id'  => 227,
						'regular_price' => '',
						'_price'        => '',
					),
				)
			)
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$job    = new RollbackJob( $bulk_editor );
		$result = $job->run(
			91,
			array(
				array(
					'object_id' => 227,
					'field'     => '_regular_price',
					'new_value' => '',
				),
				array(
					'object_id' => 227,
					'field'     => '_price',
					'new_value' => '',
				),
			)
		);

		$this->assertSame( 2, $result['processed'] );
		$this->assertSame( array(), $result['errors'] );
	}

	/**
	 * Post status history deltas map back to the editor status field.
	 *
	 * @return void
	 */
	public function test_run_maps_post_status_delta_to_editor_field(): void {
		$bulk_editor = $this->createMock( BulkEditor::class );
		$bulk_editor->expects( $this->once() )
			->method( 'processChunk' )
			->with(
				88,
				array(
					array(
						'variation_id' => 44,
						'status'       => 'publish',
					),
				)
			)
			->willReturn(
				array(
					'processed' => 1,
					'errors'    => array(),
				)
			);

		$job    = new RollbackJob( $bulk_editor );
		$result = $job->run(
			88,
			array(
				array(
					'object_id' => 44,
					'field'     => 'post_status',
					'new_value' => 'publish',
				),
			)
		);

		$this->assertSame( 1, $result['processed'] );
	}
}
