<?php
/**
 * ImportAttributeReadiness editor column tests.
 *
 * @package BulkVariations\Tests\Unit\ImportExport
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\ImportExport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Engine\AttributeMatrix;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\ImportExport\ImportAttributeReadiness::getEditorAttributeColumns
 */
class ImportAttributeReadinessEditorColumnsTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_title' )->returnArg( 1 );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Editor columns use parent attribute labels and discover extra variation keys.
	 *
	 * @return void
	 */
	public function test_get_editor_attribute_columns_merges_parent_and_variation_keys(): void {
		$post = new \stdClass();
		$post->post_type = 'product';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_the_title' )->justReturn( 'Variable Hoodie' );
		Functions\when( 'has_term' )->justReturn( true );
		Functions\when( 'wc_get_product' )->justReturn( false );
		Functions\when( 'get_post_meta' )->justReturn(
			array(
				'pa_color' => array(
					'name'         => 'pa_color',
					'value'        => '',
					'is_variation' => 1,
					'is_taxonomy'  => 1,
				),
			)
		);
		Functions\when( 'get_terms' )->justReturn(
			array(
				(object) array(
					'slug' => 'blue',
					'name' => 'Blue',
				),
			)
		);

		$repository = $this->createMock( VariationRepository::class );
		$repository->method( 'getExistingCombinationSignatures' )->willReturn( array() );

		$service = new ImportAttributeReadiness( new AttributeMatrix(), $repository );
		$columns = $service->getEditorAttributeColumns(
			18,
			array(
				array(
					'attribute_pa_color'  => 'blue',
					'attribute_pa_frame' => 'metal',
				),
			)
		);

		$this->assertCount( 2, $columns );
		$this->assertSame( 'attribute_pa_color', $columns[0]['field'] );
		$this->assertSame( 'Color', $columns[0]['label'] );
		$this->assertSame( 'attribute_pa_frame', $columns[1]['field'] );
	}
}
