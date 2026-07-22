<?php
/**
 * VariationWriter tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Engine
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Engine;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Engine\VariationWriter;
use CoderEmbassy\BulkVariationsManager\Services\HistoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Engine\VariationWriter
 */
class VariationWriterTest extends TestCase {

	/**
	 * @var list<array{sql: string, args: array<int, mixed>}>
	 */
	private array $queries = array();

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->queries = array();

		global $wpdb;
		$query_log = &$this->queries;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class( $query_log ) {
			public string $prefix = 'wp_';

			public string $postmeta = 'wp_postmeta';

			/**
			 * @param list<array{sql: string, args: array<int, mixed>}> $log Query log.
			 */
			public function __construct( private array &$log ) {
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
			 * @return int
			 */
			public function query( string $sql ): int {
				$this->log[] = array(
					'sql'  => $sql,
					'args' => array(),
				);
				return count( $this->log );
			}
		};

		Functions\when( 'wp_insert_post' )->justReturn( 901 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-05-22 12:00:00' );
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
	 * createBatch inserts variation posts and batched postmeta rows.
	 *
	 * @return void
	 */
	public function test_create_batch_inserts_variation_and_meta(): void {
		$writer = new VariationWriter();
		$ids    = $writer->createBatch(
			100,
			array(
				array(
					'attributes'    => array(
						'attribute_pa_color' => 'red',
					),
					'sku'           => 'SKU-RED',
					'regular_price' => '19.99',
				),
			)
		);

		$this->assertSame( array( 901 ), $ids );
		$this->assertNotEmpty( $this->queries );

		$insert_found = false;
		foreach ( $this->queries as $query ) {
			if ( str_contains( $query['sql'], 'INSERT INTO' ) && str_contains( $query['sql'], 'wp_postmeta' ) ) {
				$insert_found = true;
				break;
			}
		}

		$this->assertTrue( $insert_found );
	}

	/**
	 * createBatch records job history when a HistoryLogger is provided.
	 *
	 * @return void
	 */
	public function test_create_batch_records_history_when_logger_provided(): void {
		$logger = new HistoryLogger();
		$writer = new VariationWriter( $logger );
		$writer->createBatch(
			100,
			array(
				array(
					'attribute_pa_color' => 'blue',
					'sku'                => 'SKU-NEW',
					'regular_price'      => '29.99',
				),
			),
			42
		);

		$this->assertSame( 0, $logger->pendingCount() );
	}
}
