<?php
/**
 * SKUGenerator tests.
 *
 * @package BulkVariations\Tests\Unit\Services
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Services;

use Brain\Monkey;
use BulkVariations\Services\SKUGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Services\SKUGenerator
 */
class SKUGeneratorTest extends TestCase {

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class() {
			public string $postmeta = 'wp_postmeta';

			/**
			 * @param string $sql SQL.
			 * @param mixed  $args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string|null $sql SQL.
			 * @return array<int, string>
			 */
			public function get_col( ?string $sql ): array {
				unset( $sql );
				return array();
			}
		};
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Token replacement works.
	 *
	 * @return void
	 */
	public function test_generate_replaces_tokens(): void {
		$generator = new SKUGenerator();
		$sku       = $generator->generate(
			'{product}-{index}-{attr_pa_color}-{attr_pa_size}',
			array(
				'pa_color' => 'Red',
				'pa_size'  => 'XL',
			),
			array(
				'product' => 'HOODIE',
				'index'   => 3,
			)
		);

		$this->assertSame( 'HOODIE-3-Red-XL', $sku );
	}

	/**
	 * validateUnique runs one IN query and returns duplicates.
	 *
	 * @return void
	 */
	public function test_validate_unique_returns_existing_skus(): void {
		global $wpdb;
		$wpdb = new class() {
			public string $postmeta = 'wp_postmeta';

			/**
			 * @param string $sql SQL.
			 * @param mixed  $args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string|null $sql SQL.
			 * @return array<int, string>
			 */
			public function get_col( ?string $sql ): array {
				unset( $sql );
				return array( 'SKU-2' );
			}
		};
		$GLOBALS['wpdb'] = $wpdb;

		$generator  = new SKUGenerator();
		$duplicates = $generator->validateUnique( array( 'SKU-1', 'SKU-2', 'SKU-3' ) );

		$this->assertSame( array( 'SKU-2' ), $duplicates );
	}
}

