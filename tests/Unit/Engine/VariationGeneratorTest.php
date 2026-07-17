<?php
/**
 * VariationGenerator tests.
 *
 * @package BulkVariations\Tests\Unit\Engine
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Engine;

use Brain\Monkey;
use BulkVariations\Engine\AttributeMatrix;
use BulkVariations\Engine\TooManyCombinationsException;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Engine\VariationGenerator
 */
class VariationGeneratorTest extends TestCase {

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
			public string $posts = 'wp_posts';
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
			 * @param string $text LIKE fragment.
			 * @return string
			 */
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * @param string|null $sql SQL.
			 * @param string      $mode Mode.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( ?string $sql, string $mode = ARRAY_A ): array {
				unset( $sql, $mode );
				return array();
			}
		};
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
	 * Cartesian count is correct for 3x3x2.
	 *
	 * @return void
	 */
	public function test_cartesian_count_three_by_three_by_two(): void {
		$generator = new VariationGenerator( new VariationRepository(), new AttributeMatrix() );
		$result    = $generator->generateCombinations(
			100,
			array(
				'pa_color' => array( 'red', 'blue', 'green' ),
				'pa_size'  => array( 's', 'm', 'l' ),
				'pa_pack'  => array( 'single', 'double' ),
			)
		);

		$this->assertCount( 18, $result );
	}

	/**
	 * Existing variation signatures are deduped.
	 *
	 * @return void
	 */
	public function test_existing_signatures_are_deduped(): void {
		global $wpdb;
		$wpdb = new class() {
			public string $posts = 'wp_posts';
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
			 * @param string $text LIKE fragment.
			 * @return string
			 */
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * @param string|null $sql SQL.
			 * @param string      $mode Mode.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( ?string $sql, string $mode = ARRAY_A ): array {
				unset( $sql, $mode );
				return array(
					array(
						'variation_id' => 501,
						'meta_key'     => 'attribute_pa_color',
						'meta_value'   => 'red',
					),
					array(
						'variation_id' => 501,
						'meta_key'     => 'attribute_pa_size',
						'meta_value'   => 's',
					),
				);
			}
		};
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $wpdb;

		$generator = new VariationGenerator( new VariationRepository(), new AttributeMatrix() );
		$result    = $generator->generateCombinations(
			100,
			array(
				'pa_color' => array( 'red', 'blue' ),
				'pa_size'  => array( 's', 'm' ),
			)
		);

		$this->assertCount( 3, $result );
		foreach ( $result as $row ) {
			$this->assertNotSame(
				array( 'attribute_pa_color' => 'red', 'attribute_pa_size' => 's' ),
				$row['attributes']
			);
		}
	}

	/**
	 * Over-cap requests throw safety exception.
	 *
	 * @return void
	 */
	public function test_over_cap_combinations_throw(): void {
		$this->expectException( TooManyCombinationsException::class );

		$generator = new VariationGenerator( new VariationRepository(), new AttributeMatrix() );
		$generator->generateCombinations(
			100,
			array(
				'pa_a' => array_map( 'strval', range( 1, 50 ) ),
				'pa_b' => array_map( 'strval', range( 1, 50 ) ),
				'pa_c' => array_map( 'strval', range( 1, 5 ) ),
			)
		);
	}
}
