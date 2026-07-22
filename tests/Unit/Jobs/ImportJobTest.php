<?php
/**
 * ImportJob unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Jobs
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Jobs;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Engine\BulkEditor;
use CoderEmbassy\BulkVariationsManager\Engine\VariationWriter;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportValidator;
use CoderEmbassy\BulkVariationsManager\Jobs\ImportJob;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Jobs\ImportJob
 */
class ImportJobTest extends TestCase {

	/**
	 * Set up Brain Monkey for ImportValidator dependencies.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->returnArg( 1 );
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
	 * mapRowToBulkUpdate passes supported import fields to BulkEditor rows.
	 *
	 * @return void
	 */
	public function test_map_row_to_bulk_update_includes_supported_fields(): void {
		$method = new ReflectionMethod( ImportJob::class, 'mapRowToBulkUpdate' );
		$method->setAccessible( true );

		$job = new ImportJob(
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\ImportExport\ImportValidator::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Engine\VariationWriter::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Engine\BulkEditor::class )
		);

		$row = $method->invoke(
			$job,
			array(
				'variation_id'   => 19,
				'sku'            => 'CSV-TEST-19',
				'regular_price'  => '599',
				'sale_price'     => '499',
				'sale_from'      => '2026-06-01',
				'sale_to'        => '2026-06-30',
				'stock_quantity' => 10,
				'stock_status'   => 'instock',
				'status'         => 'publish',
			),
			19
		);

		$this->assertSame( 19, $row['variation_id'] );
		$this->assertSame( 'CSV-TEST-19', $row['sku'] );
		$this->assertSame( '599', $row['regular_price'] );
		$this->assertSame( '499', $row['sale_price'] );
		$this->assertSame( '2026-06-01', $row['sale_from'] );
		$this->assertSame( '2026-06-30', $row['sale_to'] );
		$this->assertSame( 10, $row['stock_quantity'] );
		$this->assertSame( 'yes', $row['manage_stock'] );
		$this->assertSame( 'instock', $row['stock_status'] );
		$this->assertSame( 'publish', $row['status'] );
	}

	/**
	 * Empty import cells are omitted so BulkEditor does not clear existing values.
	 *
	 * @return void
	 */
	public function test_map_row_to_bulk_update_skips_empty_values(): void {
		$method = new ReflectionMethod( ImportJob::class, 'mapRowToBulkUpdate' );
		$method->setAccessible( true );

		$job = new ImportJob(
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\ImportExport\ImportValidator::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Engine\VariationWriter::class ),
			$this->createMock( \CoderEmbassy\BulkVariationsManager\Engine\BulkEditor::class )
		);

		$row = $method->invoke(
			$job,
			array(
				'variation_id'  => 5,
				'regular_price' => '10',
				'sale_price'    => '',
			),
			5
		);

		$this->assertArrayNotHasKey( 'sale_price', $row );
		$this->assertArrayNotHasKey( 'manage_stock', $row );
	}

	/**
	 * Rows without variation_id route to VariationWriter::createBatch.
	 *
	 * @return void
	 */
	public function test_run_routes_create_rows_to_variation_writer(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn(
			array(
				'id'   => 7,
				'meta' => array( 'product_id' => 18 ),
			)
		);

		$writer = $this->createMock( VariationWriter::class );
		$writer->expects( $this->once() )
			->method( 'createBatch' )
			->with(
				18,
				$this->callback(
					static function ( array $rows ): bool {
						return isset( $rows[0]['attribute_pa_color'], $rows[0]['sku'] )
							&& 'green' === $rows[0]['attribute_pa_color'];
					}
				),
				7
			)
			->willReturn( array( 901 ) );

		$bulk = $this->createMock( BulkEditor::class );
		$bulk->expects( $this->never() )->method( 'processChunk' );

		$job = new ImportJob(
			$jobs,
			new ImportValidator(),
			$writer,
			$bulk
		);

		$result = $job->run(
			7,
			array(
				array(
					'product_id'         => 18,
					'attribute_pa_color' => 'green',
					'sku'                => 'CSV-CREATE-1',
					'regular_price'      => '49',
				),
			)
		);

		$this->assertSame( 1, $result['processed'] );
	}

	/**
	 * Create rows are grouped by their own product IDs.
	 *
	 * @return void
	 */
	public function test_run_routes_create_rows_to_each_product(): void {
		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'get' )->willReturn( array( 'id' => 8, 'meta' => array() ) );

		$writer = $this->createMock( VariationWriter::class );
		$writer->expects( $this->exactly( 2 ) )
			->method( 'createBatch' )
			->willReturnCallback(
				static function ( int $product_id, array $rows, int $job_id ): array {
					return array( 18 === $product_id ? 901 : 902 );
				}
			);

		$job = new ImportJob(
			$jobs,
			new ImportValidator(),
			$writer,
			$this->createMock( BulkEditor::class )
		);

		$result = $job->run(
			8,
			array(
				array( 'product_id' => 18, 'attribute_pa_color' => 'green' ),
				array( 'product_id' => 42, 'attribute_pa_color' => 'blue' ),
			)
		);

		$this->assertSame( 2, $result['processed'] );
	}
}
