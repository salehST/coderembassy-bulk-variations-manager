<?php
/**
 * EditorPreviewChanges unit tests.
 *
 * @package BulkVariations\Tests\Unit\REST
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\REST\EditorPreviewChanges;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\REST\EditorPreviewChanges
 */
class EditorPreviewChangesTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->returnArg( 1 );
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
	 * build() maps editor rows to preview diff rows.
	 *
	 * @return void
	 */
	public function test_build_maps_editor_change_rows(): void {
		$rows = EditorPreviewChanges::build(
			array(
				array(
					'variation_id' => 42,
					'field'        => 'regular_price',
					'old_value'    => '10.00',
					'new_value'    => '12.50',
				),
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 42, $rows[0]['variation_id'] );
		$this->assertSame( 42, $rows[0]['object_id'] );
		$this->assertSame( 'regular_price', $rows[0]['field'] );
		$this->assertSame( '10.00', $rows[0]['old_value'] );
		$this->assertSame( '12.50', $rows[0]['new_value'] );
	}

	/**
	 * build() accepts alternate object_id/from/to keys.
	 *
	 * @return void
	 */
	public function test_build_accepts_alternate_keys(): void {
		$rows = EditorPreviewChanges::build(
			array(
				array(
					'object_id' => 7,
					'field'     => 'sku',
					'from'      => 'OLD',
					'to'        => 'NEW',
				),
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 7, $rows[0]['variation_id'] );
		$this->assertSame( 'OLD', $rows[0]['old_value'] );
		$this->assertSame( 'NEW', $rows[0]['new_value'] );
	}
}
