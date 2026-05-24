<?php
/**
 * HistoryLogger unit tests.
 *
 * @package BulkVariations\Tests\Unit\Services
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Services\HistoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * HistoryLogger tests.
 *
 * @covers \BulkVariations\Services\HistoryLogger
 */
class HistoryLoggerTest extends TestCase {

	/**
	 * WordPress DB stub.
	 *
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
		$this->wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-05-18 12:00:00' );
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
	 * Flush() with empty buffer returns 0.
	 *
	 * @return void
	 */
	public function test_flush_empty_buffer_returns_zero(): void {
		$logger = new HistoryLogger();
		$this->assertSame( 0, $logger->flush() );
	}

	/**
	 * RecordChange() increments pending count until flush.
	 *
	 * @return void
	 */
	public function test_record_change_buffers_rows(): void {
		$logger = new HistoryLogger();
		$logger->recordChange( 1, 'variation', 100, '_price', '10', '12' );
		$logger->recordChange( 1, 'variation', 101, '_price', '5', '6' );

		$this->assertSame( 2, $logger->pendingCount() );
	}

	/**
	 * Flush() runs batched INSERT and clears buffer.
	 *
	 * @return void
	 */
	public function test_flush_inserts_buffered_rows(): void {
		$flag = new \stdClass();
		$flag->v = false;

		$this->wpdb = new class( $flag ) {
			public string $prefix = 'wp_';

			/**
			 * @param \stdClass $flag Query-called flag object.
			 */
			public function __construct( private \stdClass $flag ) {
			}

			/**
			 * @param string $sql  SQL.
			 * @param mixed  $args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string $sql SQL.
			 * @return int
			 */
			public function query( string $sql ): int {
				$this->flag->v = str_contains( $sql, 'INSERT INTO' ) && str_contains( $sql, 'wp_bv_job_changes' );
				return 2;
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		$logger = new HistoryLogger();
		$logger->recordChange( 3, 'variation', 200, '_sku', 'OLD', 'NEW' );
		$logger->recordChange( 3, 'product', 10, '_price', null, '19.99' );

		$inserted = $logger->flush();

		$this->assertTrue( $flag->v );
		$this->assertSame( 2, $inserted );
		$this->assertSame( 0, $logger->pendingCount() );
	}
}
