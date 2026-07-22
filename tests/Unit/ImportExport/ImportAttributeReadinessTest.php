<?php
/**
 * ImportAttributeReadiness unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\ImportExport
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\ImportExport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Engine\AttributeMatrix;
use CoderEmbassy\BulkVariationsManager\Engine\VariationGenerator;
use CoderEmbassy\BulkVariationsManager\Engine\VariationRepository;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportAttributeReadiness;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\ImportExport\ImportAttributeReadiness
 */
class ImportAttributeReadinessTest extends TestCase {

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
	 * Variable product meta produces CSV columns and slug index.
	 *
	 * @return void
	 */
	public function test_get_for_product_builds_attributes_from_product_meta(): void {
		$post = new \stdClass();
		$post->post_type = 'product';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_the_title' )->justReturn( 'Test Variable Shirt' );
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
		$blue_term = new \WP_Term(
			(object) array(
				'slug' => 'blue',
				'name' => 'Blue',
			)
		);
		$red_term  = new \WP_Term(
			(object) array(
				'slug' => 'red',
				'name' => 'Red',
			)
		);
		Functions\when( 'get_terms' )->justReturn( array( $blue_term, $red_term ) );

		$repository = $this->createMock( VariationRepository::class );
		$repository->method( 'getExistingCombinationSignatures' )
			->willReturn(
				array(
					wp_json_encode( array( 'attribute_pa_color' => 'blue' ) ) => true,
				)
			);

		$generator = $this->createMock( VariationGenerator::class );
		$generator->method( 'generateCombinations' )
			->willReturn(
				array(
					array( 'attributes' => array( 'attribute_pa_color' => 'red' ) ),
				)
			);

		$service = new ImportAttributeReadiness(
			new AttributeMatrix(),
			$repository,
			$generator
		);

		$payload = $service->getForProduct( 18 );

		$this->assertTrue( $payload['is_variable'] );
		$this->assertSame( 'Test Variable Shirt', $payload['product_name'] );
		$this->assertCount( 1, $payload['attributes'] );
		$this->assertSame( 'attribute_pa_color', $payload['attributes'][0]['csv_column'] );
		$this->assertSame( 2, $payload['total_possible_combinations'] );
		$this->assertFalse( $payload['all_combinations_exist'] );
		$this->assertSame( 1, $payload['remaining_combination_count'] );

		$index = $service->getSlugIndex( $payload );
		$this->assertSame( array( 'blue', 'red' ), $index['attribute_pa_color'] );
	}

	/**
	 * Missing product returns error payload.
	 *
	 * @return void
	 */
	public function test_get_for_product_returns_error_when_post_missing(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$service = new ImportAttributeReadiness(
			new AttributeMatrix(),
			$this->createMock( VariationRepository::class )
		);

		$payload = $service->getForProduct( 99 );
		$this->assertSame( 'Product not found.', $payload['message'] );
		$this->assertFalse( $payload['is_variable'] );
	}
}
