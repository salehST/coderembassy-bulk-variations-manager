<?php
/**
 * ImportValidator unit tests.
 *
 * @package BulkVariations\Tests\Unit\ImportExport
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\ImportExport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\ImportExport\ImportValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\ImportExport\ImportValidator
 */
class ImportValidatorTest extends TestCase {

	/**
	 * @var object
	 */
	private object $wpdb;

	/**
	 * Set up Brain Monkey and $wpdb stub.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = new \stdClass();
		$this->wpdb->postmeta = 'wp_postmeta';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
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
	 * Valid row passes validation.
	 *
	 * @return void
	 */
	public function test_validate_row_accepts_valid_sku_and_price(): void {
		$validator = new ImportValidator();
		$result    = $validator->validateRow(
			array(
				'sku'            => 'ABC-123',
				'product_id'     => 55,
				'attribute_size' => 'large',
				'regular_price'  => '10.50',
				'stock_quantity' => '5',
				'stock_status'   => 'instock',
			),
			array(),
			array()
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( array(), $result['issues'] );
		$this->assertSame( 'ABC-123', $result['fixed_row']['sku'] );
	}

	/**
	 * Invalid SKU format is rejected.
	 *
	 * @return void
	 */
	public function test_validate_row_rejects_invalid_sku(): void {
		$validator = new ImportValidator();
		$result    = $validator->validateRow(
			array( 'sku' => 'bad sku!' ),
			array(),
			array()
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertNotEmpty( $result['issues'] );
	}

	/**
	 * Negative price is rejected.
	 *
	 * @return void
	 */
	public function test_validate_row_rejects_negative_price(): void {
		$validator = new ImportValidator();
		$result    = $validator->validateRow(
			array(
				'sku'           => 'SKU-1',
				'regular_price' => '-1',
			),
			array(),
			array()
		);

		$this->assertFalse( $result['is_valid'] );
	}

	/**
	 * US sale date values normalize and ordering warning is raised.
	 *
	 * @return void
	 */
	public function test_validate_row_normalizes_sale_dates_and_warns_on_order(): void {
		$validator = new ImportValidator();
		$result    = $validator->validateRow(
			array(
				'sku'            => 'SKU-DATES',
				'product_id'     => 55,
				'attribute_size' => 'large',
				'sale_from'      => '07/27/2026',
				'sale_to'        => '07/20/2026',
			),
			array(),
			array()
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( '2026-07-27', $result['fixed_row']['sale_from'] );
		$this->assertSame( '2026-07-20', $result['fixed_row']['sale_to'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Create rows require product_id and attribute_* columns.
	 *
	 * @return void
	 */
	public function test_validate_row_requires_product_id_and_attributes_for_create(): void {
		$validator = new ImportValidator();

		$missing_product = $validator->validateRow(
			array(
				'sku'                => 'SKU-NEW',
				'attribute_pa_color' => 'red',
			),
			array(),
			array()
		);
		$this->assertFalse( $missing_product['is_valid'] );
		$this->assertStringContainsString( 'product_id', implode( ' ', $missing_product['issues'] ) );

		$missing_attr = $validator->validateRow(
			array(
				'product_id' => 18,
				'sku'        => 'SKU-NEW-2',
			),
			array(),
			array()
		);
		$this->assertFalse( $missing_attr['is_valid'] );
		$this->assertStringContainsString( 'attribute_', implode( ' ', $missing_attr['issues'] ) );
	}

	/**
	 * Create rows warn when attribute slug is not allowed on the parent product.
	 *
	 * @return void
	 */
	public function test_validate_row_warns_on_unknown_create_attribute_slug(): void {
		$validator = new ImportValidator();
		$rules     = array(
			'attribute_pa_color' => array( 'blue', 'red' ),
		);

		$result = $validator->validateRow(
			array(
				'product_id'         => 18,
				'sku'                => 'SKU-NEW',
				'attribute_pa_color' => 'cyan',
			),
			array(),
			array(),
			$rules
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertNotEmpty( $result['warnings'] );
		$this->assertStringContainsString( 'cyan', implode( ' ', $result['warnings'] ) );
		$this->assertStringContainsString( 'attribute_pa_color', implode( ' ', $result['warnings'] ) );
	}

	/**
	 * Duplicate SKU in batch is rejected.
	 *
	 * @return void
	 */
	public function test_validate_row_rejects_duplicate_in_batch(): void {
		$validator = new ImportValidator();
		$result    = $validator->validateRow(
			array( 'sku' => 'SKU-DUP' ),
			array(),
			array( 'SKU-DUP' => true )
		);

		$this->assertFalse( $result['is_valid'] );
	}

	/**
	 * lookupExistingSkus uses SQL IN clause.
	 *
	 * @return void
	 */
	public function test_lookup_existing_skus_uses_in_query(): void {
		$capture = new \stdClass();
		$capture->sql = '';

		$this->wpdb = new class( $capture ) {
			public string $postmeta = 'wp_postmeta';

			/**
			 * @param \stdClass $capture SQL capture.
			 */
			public function __construct( private \stdClass $capture ) {
			}

			/**
			 * @param string             $sql    SQL.
			 * @param array<int, string> $values Values.
			 * @return string
			 */
			public function prepare( string $sql, array $values ): string {
				$this->capture->sql = $sql;
				unset( $values );
				return $sql;
			}

			/**
			 * @param string|null $sql SQL.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( ?string $sql, string $output = OBJECT ): array {
				unset( $output );
				return array(
					array(
						'post_id'    => 42,
						'meta_value' => 'SKU-EXIST',
					),
				);
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		$validator = new ImportValidator();
		$lookup    = $validator->lookupExistingSkus( array( 'SKU-EXIST', 'SKU-NEW' ) );

		$this->assertStringContainsString( 'IN (', $capture->sql );
		$this->assertSame( 42, $lookup['SKU-EXIST'] );
	}
}
