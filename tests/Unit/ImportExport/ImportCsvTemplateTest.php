<?php
/**
 * ImportCsvTemplate unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\ImportExport
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\ImportExport;

use CoderEmbassy\BulkVariationsManager\ImportExport\ImportCsvTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\ImportExport\ImportCsvTemplate
 */
class ImportCsvTemplateTest extends TestCase {

	/**
	 * Headers include base, attribute, and editable Free columns.
	 *
	 * @return void
	 */
	public function test_get_headers_includes_attribute_and_editable_columns(): void {
		$readiness = array(
			'product_id'  => 18,
			'is_variable' => true,
			'attributes'  => array(
				array(
					'csv_column' => 'attribute_pa_color',
					'options'    => array(
						array( 'slug' => 'blue', 'label' => 'Blue' ),
						array( 'slug' => 'red', 'label' => 'Red' ),
					),
				),
				array(
					'csv_column' => 'attribute_pa_size',
					'options'    => array(
						array( 'slug' => 'large', 'label' => 'Large' ),
					),
				),
			),
		);

		$template = new ImportCsvTemplate();
		$headers  = $template->getHeaders( $readiness );

		$this->assertSame(
			array(
				'product_id',
				'variation_id',
				'sku',
				'attribute_pa_color',
				'attribute_pa_size',
				'regular_price',
				'sale_price',
				'sale_from',
				'sale_to',
				'stock_quantity',
				'stock_status',
				'status',
			),
			$headers
		);
	}

	/**
	 * Sample rows include update and create examples with valid attribute slugs.
	 *
	 * @return void
	 */
	public function test_get_sample_rows_includes_update_and_create_examples(): void {
		$readiness = array(
			'product_id'            => 18,
			'is_variable'           => true,
			'attributes'            => array(
				array(
					'csv_column' => 'attribute_pa_color',
					'options'    => array(
						array( 'slug' => 'blue', 'label' => 'Blue' ),
						array( 'slug' => 'red', 'label' => 'Red' ),
					),
				),
			),
			'existing_combinations' => array(
				array( 'attribute_pa_color' => 'blue' ),
			),
		);

		$template = new ImportCsvTemplate();
		$rows     = $template->getSampleRows( $readiness );

		$this->assertCount( 2, $rows );
		$this->assertSame( '18', $rows[0]['product_id'] );
		$this->assertSame( '', $rows[0]['variation_id'] );
		$this->assertSame( 'your-existing-sku', $rows[0]['sku'] );

		$this->assertSame( '18', $rows[1]['product_id'] );
		$this->assertSame( '', $rows[1]['variation_id'] );
		$this->assertSame( 'new-variation-sku', $rows[1]['sku'] );
		$this->assertSame( 'red', $rows[1]['attribute_pa_color'] );
	}

	/**
	 * Built CSV contains header row and sample data.
	 *
	 * @return void
	 */
	public function test_build_outputs_valid_csv(): void {
		$readiness = array(
			'product_id'  => 42,
			'is_variable' => false,
			'attributes'  => array(),
		);

		$template = new ImportCsvTemplate();
		$csv      = $template->build( $readiness );

		$this->assertStringContainsString( 'product_id,variation_id,sku', $csv );
		$this->assertStringContainsString( '42,,your-existing-sku', $csv );
		$this->assertSame( 'import-template-product-42.csv', $template->getFilename( $readiness ) );
	}
}
