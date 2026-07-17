<?php
/**
 * BulkEditor unit tests.
 *
 * @package BulkVariations\Tests\Unit\Engine
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Engine;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\BulkEditor;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\Services\HistoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Engine\BulkEditor
 */
class BulkEditorTest extends TestCase {

	/**
	 * @var list<array{sql: string, args: array<int, mixed>}>
	 */
	private array $queries = array();

	/**
	 * @var list<array<int, array<string, mixed>>|null|bool|int>
	 */
	private array $result_queue = array();

	/**
	 * @var bool
	 */
	private bool $query_should_fail = false;

	/**
	 * Set up Brain Monkey and $wpdb stub.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->queries           = array();
		$this->result_queue      = array();
		$this->query_should_fail = false;

		global $wpdb;
		$query_log    = &$this->queries;
		$result_queue = &$this->result_queue;
		$query_fail   = &$this->query_should_fail;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class( $query_log, $result_queue, $query_fail ) {
			public string $posts = 'wp_posts';

			public string $postmeta = 'wp_postmeta';

			public string $prefix = 'wp_';

			/**
			 * @param list<array{sql: string, args: array<int, mixed>}>              $log        Query log.
			 * @param list<array<int, array<string, mixed>>|null|bool|int>          $queue      Staged results.
			 * @param bool                                                            $query_fail Force query() failure.
			 */
			public function __construct(
				/** @var list<array{sql: string, args: array<int, mixed>}> $log */
				private array &$log,
				private array &$queue,
				private bool &$query_fail
			) {
			}

			/**
			 * @return list<array{sql: string, args: array<int, mixed>}>
			 */
			public function getLoggedQueries(): array {
				return $this->log;
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
			 * @param string $sql SQL.
			 * @return int|false
			 */
			public function query( string $sql ) {
				$this->log[] = array(
					'sql'  => $sql,
					'args' => array(),
				);
				if ( $this->query_fail ) {
					return false;
				}
				if ( empty( $this->queue ) ) {
					return 1;
				}
				$next = array_shift( $this->queue );
				if ( false === $next ) {
					return false;
				}
				return is_int( $next ) ? $next : 1;
			}

			/**
			 * @param string $sql SQL.
			 * @return array<int, mixed>
			 */
			public function get_col( string $sql ): array {
				$this->log[] = array(
					'sql'  => $sql,
					'args' => array(),
				);
				if ( empty( $this->queue ) ) {
					return array();
				}
				$next = array_shift( $this->queue );
				return is_array( $next ) ? $next : array();
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
		};

		Functions\when( 'sanitize_key' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias(
			static function ( string $type = 'mysql' ) {
				if ( 'timestamp' === $type ) {
					return strtotime( '2026-05-21 12:00:00' );
				}
				return '2026-05-21 12:00:00';
			}
		);
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
	 * Missing _sku meta should be inserted, not CASE-updated.
	 *
	 * @return void
	 */
	public function test_missing_sku_uses_insert_not_update(): void {
		$this->result_queue[] = array();
		$this->result_queue[] = array(
			array(
				'ID'          => 20,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			6,
			array(
				array(
					'variation_id' => 20,
					'sku'          => 'SA0550',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertTrue( $this->query_contains( 'INSERT INTO wp_postmeta' ) );
		$this->assertTrue( $this->query_contains( '_sku' ) );
		$this->assertFalse( $this->query_contains( 'UPDATE wp_postmeta' ) );
	}

	/**
	 * Downloadable files must be passed to update_post_meta as an array so WordPress serializes once.
	 *
	 * @return void
	 */
	public function test_downloadable_files_payload_is_prepared_as_meta_array(): void {
		$editor = $this->make_editor();
		$method = new \ReflectionMethod( $editor, 'prepareDownloadableFilesForMeta' );

		$files = array(
			'abc123' => array(
				'name' => 'Poster file',
				'file' => 'https://example.test/poster.jpg',
			),
		);

		$this->assertSame( $files, $method->invoke( $editor, serialize( $files ) ) );
	}

	/**
	 * Existing _regular_price meta should use CASE UPDATE.
	 *
	 * @return void
	 */
	public function test_existing_regular_price_uses_case_update(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 30,
				'meta_key'   => '_regular_price',
				'meta_value' => '10.00',
			),
			array(
				'post_id'    => 30,
				'meta_key'   => '_price',
				'meta_value' => '10.00',
			),
		);
		$this->result_queue[] = array(
			array(
				'ID'          => 30,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			7,
			array(
				array(
					'variation_id'  => 30,
					'regular_price' => '12.50',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertTrue( $this->query_contains( 'UPDATE wp_postmeta' ) );
		$this->assertTrue( $this->query_targets_meta_key( '_regular_price' ) );
		$this->assertTrue( $this->query_targets_meta_key( '_price' ) );
		$this->assertFalse( $this->query_contains( 'INSERT INTO wp_postmeta' ) );
	}

	/**
	 * Status field updates wp_posts.post_status.
	 *
	 * @return void
	 */
	public function test_status_field_updates_post_status(): void {
		$this->result_queue[] = array();
		$this->result_queue[] = array(
			array(
				'ID'          => 40,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			8,
			array(
				array(
					'variation_id' => 40,
					'status'       => 'private',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertTrue( $this->query_contains( 'UPDATE wp_posts' ) );
		$this->assertTrue( $this->query_contains( 'post_status' ) );
	}

	/**
	 * Reverting SKU to empty deletes the postmeta row.
	 *
	 * @return void
	 */
	public function test_rollback_empty_sku_deletes_postmeta(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 20,
				'meta_key'   => '_sku',
				'meta_value' => 'SA0550',
			),
		);
		$this->result_queue[] = array(
			array(
				'ID'          => 20,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			9,
			array(
				array(
					'variation_id' => 20,
					'sku'          => '',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertTrue( $this->query_contains( 'DELETE FROM wp_postmeta' ) );
		$this->assertTrue( $this->query_contains( '_sku' ) );
	}

	/**
	 * Rows with no effective meta changes should not increment processed.
	 *
	 * @return void
	 */
	public function test_process_chunk_skips_row_without_effective_changes(): void {
		$this->queue_meta_snapshot(
			88,
			array(
				'_regular_price' => '550',
				'_price'         => '550',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			99,
			array(
				array(
					'variation_id'  => 88,
					'regular_price' => '550',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 0, $result['processed'] );
	}

	/**
	 * Regular price updates also sync WooCommerce _price when no sale is active.
	 *
	 * @return void
	 */
	public function test_regular_price_update_syncs_display_price(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 30,
				'meta_key'   => '_regular_price',
				'meta_value' => '10.00',
			),
			array(
				'post_id'    => 30,
				'meta_key'   => '_price',
				'meta_value' => '10.00',
			),
		);
		$this->result_queue[] = array(
			array(
				'ID'          => 30,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			11,
			array(
				array(
					'variation_id'  => 30,
					'regular_price' => '12.50',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( '_price' ) );
		$this->assertTrue( $this->query_contains( '12.50' ) );
	}

	/**
	 * Active sale price remains the display price when only regular price changes.
	 *
	 * @return void
	 */
	public function test_active_sale_price_wins_display_price(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 31,
				'meta_key'   => '_regular_price',
				'meta_value' => '20.00',
			),
			array(
				'post_id'    => 31,
				'meta_key'   => '_sale_price',
				'meta_value' => '15.00',
			),
			array(
				'post_id'    => 31,
				'meta_key'   => '_price',
				'meta_value' => '15.00',
			),
		);
		$this->result_queue[] = array(
			array(
				'ID'          => 31,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			12,
			array(
				array(
					'variation_id'  => 31,
					'regular_price' => '25.00',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_targets_meta_key( '_regular_price' ) );
		$this->assertFalse( $this->query_has_meta_value( '_price', '25.00' ) );
	}

	/**
	 * Sale price updates sync _price to the sale value.
	 *
	 * @return void
	 */
	public function test_sale_price_update_syncs_display_price(): void {
		$this->result_queue[] = array(
			array(
				'post_id'    => 32,
				'meta_key'   => '_regular_price',
				'meta_value' => '20.00',
			),
			array(
				'post_id'    => 32,
				'meta_key'   => '_sale_price',
				'meta_value' => '15.00',
			),
			array(
				'post_id'    => 32,
				'meta_key'   => '_price',
				'meta_value' => '15.00',
			),
		);
		$this->result_queue[] = array(
			array(
				'ID'          => 32,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			13,
			array(
				array(
					'variation_id' => 32,
					'sale_price'   => '12.00',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( '_price' ) );
		$this->assertTrue( $this->query_contains( '12.00' ) );
	}

	/**
	 * Missing _sale_price meta should be inserted.
	 *
	 * @return void
	 */
	public function test_missing_sale_price_uses_insert(): void {
		$this->queue_meta_snapshot(
			60,
			array(
				'_regular_price' => '500',
				'_price'         => '500',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			20,
			array(
				array(
					'variation_id' => 60,
					'sale_price'   => '475',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( 'INSERT INTO wp_postmeta' ) );
		$this->assertTrue( $this->query_has_meta_value( '_sale_price', '475' ) );
		$this->assertTrue( $this->query_has_meta_value( '_price', '475' ) );
	}

	/**
	 * Sale from UI dates persist as unix timestamps.
	 *
	 * @return void
	 */
	public function test_sale_from_stores_unix_timestamp(): void {
		$expected = (string) strtotime( '2026-06-01 00:00:00' );
		$this->queue_meta_snapshot(
			61,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			21,
			array(
				array(
					'variation_id' => 61,
					'sale_from'    => '2026-06-01',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_sale_price_dates_from', $expected ) );
	}

	/**
	 * Sale price without schedule dates keeps sale as active _price.
	 *
	 * @return void
	 */
	public function test_sale_price_without_dates_syncs_active_price(): void {
		$this->queue_meta_snapshot(
			62,
			array(
				'_regular_price' => '500',
				'_price'         => '500',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			22,
			array(
				array(
					'variation_id' => 62,
					'sale_price'   => '475',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_price', '475' ) );
	}

	/**
	 * Future sale_from keeps regular price as active _price.
	 *
	 * @return void
	 */
	public function test_future_sale_from_keeps_regular_active_price(): void {
		$this->queue_meta_snapshot(
			63,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			23,
			array(
				array(
					'variation_id' => 63,
					'sale_from'    => '2026-05-22',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_price', '500' ) );
		$this->assertFalse( $this->query_has_meta_value( '_price', '475' ) );
	}

	/**
	 * Expired sale_to keeps regular price as active _price while sale remains stored.
	 *
	 * @return void
	 */
	public function test_expired_sale_to_keeps_regular_active_price(): void {
		$this->queue_meta_snapshot(
			64,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			24,
			array(
				array(
					'variation_id' => 64,
					'sale_to'      => '2026-05-20',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_price', '500' ) );
		$this->assertFalse( $this->query_has_meta_value( '_price', '475' ) );
	}

	/**
	 * Active sale window uses sale price for _price.
	 *
	 * @return void
	 */
	public function test_active_sale_window_syncs_sale_active_price(): void {
		$this->queue_meta_snapshot(
			65,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '500',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			25,
			array(
				array(
					'variation_id' => 65,
					'sale_from'    => '2026-05-01',
					'sale_to'      => '2026-05-31',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_price', '475' ) );
	}

	/**
	 * Clearing sale price restores regular as active _price.
	 *
	 * @return void
	 */
	public function test_clearing_sale_price_restores_regular_active_price(): void {
		$this->queue_meta_snapshot(
			66,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			26,
			array(
				array(
					'variation_id' => 66,
					'sale_price'   => '',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( 'DELETE FROM wp_postmeta' ) );
		$this->assertTrue( $this->query_contains( '_sale_price' ) );
		$this->assertTrue( $this->query_has_meta_value( '_price', '500' ) );
	}

	/**
	 * Rollback-style restore of sale fields returns prior active price.
	 *
	 * @return void
	 */
	public function test_rollback_sale_fields_restore_values(): void {
		$from_ts = (string) strtotime( '2026-05-01 00:00:00' );
		$to_ts   = (string) strtotime( '2026-05-31 23:59:59' );
		$this->queue_meta_snapshot(
			67,
			array(
				'_regular_price'          => '500',
				'_sale_price'             => '475',
				'_sale_price_dates_from'  => $from_ts,
				'_sale_price_dates_to'    => $to_ts,
				'_price'                  => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			27,
			array(
				array(
					'variation_id' => 67,
					'sale_price'   => '',
					'sale_from'    => '',
					'sale_to'      => '',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( 'DELETE FROM wp_postmeta' ) );
		$this->assertTrue( $this->query_has_meta_value( '_price', '500' ) );
	}

	/**
	 * Raw sale date meta aliases should be accepted and inserted.
	 *
	 * @return void
	 */
	public function test_sale_date_meta_alias_insert_is_processed(): void {
		$new_to = (string) strtotime( '2026-02-26 23:59:59' );
		$this->queue_meta_snapshot(
			19,
			array(
				'_regular_price' => '500',
				'_sale_price'    => '475',
				'_price'         => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			28,
			array(
				array(
					'variation_id'          => 19,
					'_sale_price_dates_to'  => $new_to,
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( 'INSERT INTO wp_postmeta' ) );
		$this->assertTrue( $this->query_has_meta_value( '_sale_price_dates_to', $new_to ) );
	}

	/**
	 * Rollback clearing sale_to alias should delete existing date meta.
	 *
	 * @return void
	 */
	public function test_rollback_clearing_sale_to_alias_deletes_meta(): void {
		$this->queue_meta_snapshot(
			68,
			array(
				'_regular_price'         => '500',
				'_sale_price'            => '475',
				'_sale_price_dates_to'   => (string) strtotime( '2026-07-27 23:59:59' ),
				'_price'                 => '500',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			29,
			array(
				array(
					'variation_id'         => 68,
					'_sale_price_dates_to' => '',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_contains( 'DELETE FROM wp_postmeta' ) );
		$this->assertTrue( $this->query_contains( '_sale_price_dates_to' ) );
	}

	/**
	 * Rollback restoring sale_to alias should update previous timestamp.
	 *
	 * @return void
	 */
	public function test_rollback_restores_previous_sale_to_timestamp(): void {
		$old_to = (string) strtotime( '2026-02-26 23:59:59' );
		$this->queue_meta_snapshot(
			69,
			array(
				'_sale_price_dates_to' => (string) strtotime( '2026-07-27 23:59:59' ),
				'_regular_price'       => '500',
				'_sale_price'          => '475',
				'_price'               => '500',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			30,
			array(
				array(
					'variation_id'         => 69,
					'_sale_price_dates_to' => $old_to,
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_targets_meta_key( '_sale_price_dates_to' ) );
		$this->assertTrue( $this->query_has_meta_value( '_sale_price_dates_to', $old_to ) );
	}

	/**
	 * Explicit rollback _price should win when sale date alias is in same row.
	 *
	 * @return void
	 */
	public function test_explicit_price_in_rollback_row_skips_derived_price(): void {
		$explicit_price = '321';
		$this->queue_meta_snapshot(
			70,
			array(
				'_regular_price'       => '500',
				'_sale_price'          => '475',
				'_sale_price_dates_to' => (string) strtotime( '2026-07-27 23:59:59' ),
				'_price'               => '475',
			)
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			31,
			array(
				array(
					'variation_id'         => 70,
					'_sale_price_dates_to' => (string) strtotime( '2026-02-26 23:59:59' ),
					'_price'               => $explicit_price,
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->query_has_meta_value( '_price', $explicit_price ) );
		$this->assertFalse( $this->query_has_meta_value( '_price', '500' ) );
	}

	/**
	 * Failed SQL returns errors and zero processed rows.
	 *
	 * @return void
	 */
	public function test_failed_query_returns_errors(): void {
		$this->query_should_fail = true;
		$this->result_queue[]    = array();
		$this->result_queue[]    = array(
			array(
				'ID'          => 50,
				'post_status' => 'publish',
			),
		);

		$editor = $this->make_editor();
		$result = $editor->processChunk(
			10,
			array(
				array(
					'variation_id' => 50,
					'sku'          => 'FAIL-SKU',
				),
			)
		);

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( 0, $result['processed'] );
	}

	/**
	 * @param string $meta_key Meta key written in a postmeta query.
	 * @param string $value    Expected bound value.
	 * @return bool
	 */
	private function query_has_meta_value( string $meta_key, string $value ): bool {
		foreach ( $this->queries as $query ) {
			if ( empty( $query['args'] ) || ! str_contains( $query['sql'], 'postmeta' ) ) {
				continue;
			}
			$flat = array();
			array_walk_recursive(
				$query['args'],
				static function ( $arg ) use ( &$flat ): void {
					$flat[] = (string) $arg;
				}
			);
			$key_index = array_search( $meta_key, $flat, true );
			if ( false === $key_index ) {
				continue;
			}
			if ( in_array( $value, $flat, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $meta_key Exact postmeta key in prepare args.
	 * @return bool
	 */
	private function query_targets_meta_key( string $meta_key ): bool {
		foreach ( $this->queries as $query ) {
			if ( empty( $query['args'] ) ) {
				continue;
			}
			$flat = array();
			array_walk_recursive(
				$query['args'],
				static function ( $value ) use ( &$flat ): void {
					$flat[] = (string) $value;
				}
			);
			if ( in_array( $meta_key, $flat, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $needle SQL fragment or bound value.
	 * @return bool
	 */
	private function query_contains( string $needle ): bool {
		global $wpdb;
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'getLoggedQueries' ) ) {
			$this->queries = $wpdb->getLoggedQueries();
		}

		foreach ( $this->queries as $query ) {
			$haystack = $query['sql'];
			if ( ! empty( $query['args'] ) ) {
				$flat = array();
				array_walk_recursive(
					$query['args'],
					static function ( $value ) use ( &$flat ): void {
						$flat[] = (string) $value;
					}
				);
				$haystack .= ' ' . implode( ' ', $flat );
			}
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int                  $variation_id Variation ID.
	 * @param array<string, string> $meta         meta_key => meta_value.
	 * @return void
	 */
	private function queue_meta_snapshot( int $variation_id, array $meta ): void {
		$rows = array();
		foreach ( $meta as $meta_key => $meta_value ) {
			$rows[] = array(
				'post_id'    => $variation_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			);
		}
		$this->result_queue[] = $rows;
		$this->result_queue[] = array(
			array(
				'ID'          => $variation_id,
				'post_status' => 'publish',
			),
		);
	}

	/**
	 * @return BulkEditor
	 */
	private function make_editor(): BulkEditor {
		$repo = $this->createMock( JobRepositoryInterface::class );
		$repo->method( 'get' )->willReturn(
			array(
				'id'     => 1,
				'type'   => 'bulk_edit',
				'status' => 'running',
			)
		);

		return new BulkEditor(
			$repo,
			new VariationRepository(),
			new HistoryLogger()
		);
	}
}
