<?php
/**
 * VariationRepository tests.
 *
 * @package BulkVariations\Tests\Unit\Engine
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Engine;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Engine\VariationRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Engine\VariationRepository
 */

// phpcs:disable WordPress.DB.SlowDBQuery
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

class VariationRepositoryTest extends TestCase {

	/**
	 * @var list<array{sql: string, args: array<int, mixed>}>
	 */
	private array $queries = array();

	/**
	 * @var list<array<int, array<string, mixed>>|null>
	 */
	private array $result_queue = array();

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->queries      = array();
		$this->result_queue = array();

		global $wpdb;
		$query_log    = &$this->queries;
		$result_queue = &$this->result_queue;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class( $query_log, $result_queue ) {
			public string $posts = 'wp_posts';

			public string $postmeta = 'wp_postmeta';

			public string $prefix = 'wp_';

			/**
			 * @param list<array{sql: string, args: array<int, mixed>}> $log   Query log.
			 * @param list<mixed>                                       $queue Staged DB results.
			 */
			public function __construct(
				/** @phpstan-ignore property.onlyWritten */
				private array &$log,
				private array &$queue
			) {
			}

			/**
			 * @param string $sql  SQL.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				$this->log[] = array(
					'sql'  => $sql,
					'args' => $args,
				);
				return $sql;
			}

			/**
			 * @param string $text Text to escape for LIKE.
			 * @return string
			 */
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * @param string $sql    SQL.
			 * @param mixed  $output Output mode.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( string $sql, $output = OBJECT ): array {
				unset( $output );
				$this->log[] = array(
					'sql'  => $sql,
					'args' => array(),
				);
				if ( empty( $this->queue ) ) {
					return array();
				}
				$next = array_shift( $this->queue );
				if ( ! is_array( $next ) ) {
					return array();
				}

				/** @var array<int, array<string, mixed>> $next */
				return $next;
			}

			/**
			 * @param string $sql SQL.
			 * @return string|null
			 */
			public function get_var( string $sql ): ?string {
				$this->log[] = array(
					'sql'  => $sql,
					'args' => array(),
				);
				if ( empty( $this->queue ) ) {
					return null;
				}
				$next = array_shift( $this->queue );
				if ( ! is_scalar( $next ) ) {
					return null;
				}

				return (string) $next;
			}
		};

		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
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
	 * getMetaSnapshot keeps the newest non-empty duplicate _sku row.
	 *
	 * @return void
	 */
	public function test_get_meta_snapshot_prefers_latest_meta_row(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 201,
				'meta_key'   => '_sku',
				'meta_value' => 'SKU-LATEST',
			),
			array(
				'post_id'    => 201,
				'meta_key'   => '_sku',
				'meta_value' => '',
			),
		);

		$repository = new VariationRepository();
		$snapshot   = $repository->getMetaSnapshot( array( 201 ), array( '_sku' ) );

		$this->assertSame( 'SKU-LATEST', $snapshot[201]['_sku'] );
		$this->assertArrayNotHasKey( '_regular_price', $snapshot[201] );
		$this->assertStringContainsString( 'ORDER BY post_id ASC, meta_key ASC, meta_id DESC', $this->queries[0]['sql'] );
	}

	/**
	 * listVariations maps _sku postmeta to the REST sku field.
	 *
	 * @return void
	 */
	public function test_list_variations_returns_sku_from_postmeta(): void {
		$this->result_queue = array(
			array(
				array(
					'ID'          => 201,
					'post_status' => 'publish',
					'post_title'  => 'Variation #201',
				),
			),
			array(
				array(
					'post_id'    => 201,
					'meta_key'   => '_sku',
					'meta_value' => 'HOODIE-RED',
				),
				array(
					'post_id'    => 201,
					'meta_key'   => '_regular_price',
					'meta_value' => '19.99',
				),
			),
			'wp_wc_product_meta_lookup',
			array(),
			array(),
		);

		$repository = new VariationRepository();
		$rows       = $repository->listVariations( 100 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 201, $rows[0]['variation_id'] );
		$this->assertSame( 100, $rows[0]['product_id'] );
		$this->assertSame( 'HOODIE-RED', $rows[0]['sku'] );
		$this->assertArrayHasKey( 'image_url', $rows[0] );
	}

	/**
	 * listVariations includes attribute_* fields on each row.
	 *
	 * @return void
	 */
	public function test_list_variations_includes_attribute_meta_fields(): void {
		$this->result_queue = array(
			array(
				array(
					'ID'          => 401,
					'post_status' => 'publish',
					'post_title'  => 'Variation #401',
				),
			),
			array(
				array(
					'post_id'    => 401,
					'meta_key'   => '_regular_price',
					'meta_value' => '10',
				),
			),
			'wp_wc_product_meta_lookup',
			array(),
			array(
				array(
					'post_id'    => 401,
					'meta_key'   => 'attribute_pa_color',
					'meta_value' => 'blue',
				),
				array(
					'post_id'    => 401,
					'meta_key'   => 'attribute_pa_size',
					'meta_value' => 'xl',
				),
			),
		);

		$repository = new VariationRepository();
		$rows       = $repository->listVariations( 100 );

		$this->assertSame( 'blue', $rows[0]['attribute_pa_color'] );
		$this->assertSame( 'xl', $rows[0]['attribute_pa_size'] );
	}

	/**
	 * getPostStatusSnapshot returns variation post_status values.
	 *
	 * @return void
	 */
	public function test_get_post_status_snapshot_returns_statuses(): void {
		$this->result_queue[] = array(
			array(
				'ID'          => 40,
				'post_status' => 'private',
			),
		);

		$repository = new VariationRepository();
		$snapshot   = $repository->getPostStatusSnapshot( array( 40 ) );

		$this->assertSame( 'private', $snapshot[40] );
		$this->assertStringContainsString( "post_type = 'product_variation'", $this->queries[0]['sql'] );
	}

	/**
	 * listVariations falls back to wc_product_meta_lookup when postmeta _sku is empty.
	 *
	 * @return void
	 */
	public function test_list_variations_falls_back_to_lookup_table_sku(): void {
		$this->result_queue = array(
			array(
				array(
					'ID'          => 301,
					'post_status' => 'publish',
					'post_title'  => 'Variation #301',
				),
			),
			array(
				array(
					'post_id'    => 301,
					'meta_key'   => '_sku',
					'meta_value' => '',
				),
			),
			'wp_wc_product_meta_lookup',
			array(
				array(
					'product_id' => 301,
					'sku'        => 'LOOKUP-SKU',
				),
			),
			array(),
		);

		$repository = new VariationRepository();
		$rows       = $repository->listVariations( 100 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'LOOKUP-SKU', $rows[0]['sku'] );
	}
}
