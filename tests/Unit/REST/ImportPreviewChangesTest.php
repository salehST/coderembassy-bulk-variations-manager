<?php
/**
 * ImportPreviewChanges unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\REST
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Engine\VariationRepository;
use CoderEmbassy\BulkVariationsManager\REST\ImportPreviewChanges;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\REST\ImportPreviewChanges
 */
class ImportPreviewChangesTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $data ) => json_encode( $data ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
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
	 * Omits product_id and unchanged stock/status fields for existing variations.
	 *
	 * @return void
	 */
	public function test_build_omits_context_and_unchanged_fields(): void {
		$repo = $this->createMock( VariationRepository::class );
		$repo->method( 'getMetaSnapshot' )->willReturn(
			array(
				19 => array(
					'_sku'                    => 'OLD-SKU',
					'_regular_price'          => '500',
					'_sale_price'             => '',
					'_sale_price_dates_from'  => '',
					'_sale_price_dates_to'    => '',
					'_stock'                  => '5',
					'_stock_status'           => 'instock',
				),
			)
		);
		$repo->method( 'getPostStatusSnapshot' )->willReturn( array( 19 => 'publish' ) );

		$rows = ImportPreviewChanges::build(
			array(
				array(
					'variation_id'   => 19,
					'product_id'     => 18,
					'sku'            => 'CSV-TEST-19',
					'regular_price'  => '599',
					'sale_price'     => '499',
					'sale_from'      => '2026-06-01',
					'sale_to'        => '2026-06-30',
					'stock_quantity' => 10,
					'stock_status'   => 'instock',
					'status'         => 'publish',
				),
			),
			$repo
		);

		$fields = array_column( $rows, 'field' );

		$this->assertNotContains( 'product_id', $fields );
		$this->assertNotContains( 'stock_status', $fields );
		$this->assertNotContains( 'status', $fields );
		$this->assertContains( 'sku', $fields );
		$this->assertContains( 'regular_price', $fields );
		$this->assertContains( 'sale_price', $fields );
		$this->assertContains( 'sale_from', $fields );
		$this->assertContains( 'sale_to', $fields );
		$this->assertContains( 'stock_quantity', $fields );
	}

	/**
	 * Numeric price strings that match after float cast are omitted.
	 *
	 * @return void
	 */
	public function test_build_omits_equal_numeric_prices(): void {
		$repo = $this->createMock( VariationRepository::class );
		$repo->method( 'getMetaSnapshot' )->willReturn(
			array(
				5 => array(
					'_regular_price' => '10.0',
					'_sale_price'    => '',
				),
			)
		);
		$repo->method( 'getPostStatusSnapshot' )->willReturn( array( 5 => 'publish' ) );

		$rows = ImportPreviewChanges::build(
			array(
				array(
					'variation_id'  => 5,
					'regular_price' => '10',
				),
			),
			$repo
		);

		$this->assertEmpty( $rows );
	}

	/**
	 * Create rows show attribute and meta fields with empty old values in preview.
	 *
	 * @return void
	 */
	public function test_build_includes_create_row_fields_with_placeholder_object_id(): void {
		$repo = $this->createMock( VariationRepository::class );
		$repo->method( 'getMetaSnapshot' )->willReturn( array() );
		$repo->method( 'getPostStatusSnapshot' )->willReturn( array() );

		$rows = ImportPreviewChanges::build(
			array(
				array(
					'product_id'         => 18,
					'attribute_pa_color' => 'cyan',
					'sku'                => 'CSV-CREATE-PREVIEW',
					'regular_price'      => '55',
				),
			),
			$repo
		);

		$fields = array_column( $rows, 'field' );
		$this->assertContains( 'attribute_pa_color', $fields );
		$this->assertContains( 'sku', $fields );
		$this->assertContains( 'regular_price', $fields );
		$this->assertNotContains( 'product_id', $fields );

		$sku_row = array_values(
			array_filter( $rows, static fn( array $row ): bool => 'sku' === $row['field'] )
		)[0];
		$this->assertSame( -1, $sku_row['object_id'] );
		$this->assertSame( '', $sku_row['old_value'] );
	}
}
