<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * MigrationRunner unit tests.
 *
 * @package BulkVariations\Tests\Unit\Updater
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Updater;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Updater\MigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * MigrationRunner tests.
 *
 * @covers \BulkVariations\Updater\MigrationRunner
 */
class MigrationRunnerTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Version option bumps after migrate when a pending migration exists.
	 *
	 * @return void
	 */
	public function test_migrate_updates_db_version_option(): void {
		if ( ! defined( 'BV_VERSION' ) ) {
			define( 'BV_VERSION', '0.1.0' );
		}
		if ( ! defined( 'BV_PLUGIN_PATH' ) ) {
			define( 'BV_PLUGIN_PATH', dirname( __DIR__, 3 ) . '/' );
		}
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/stubs/' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'wp_';

		$stored = array( 'bv_db_version' => '0' );

		Functions\when( 'get_option' )->alias(
			function ( string $key, $default_value = false ) use ( &$stored ) {
				return $stored[ $key ] ?? $default_value;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( string $key, $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;
				return true;
			}
		);

		$runner = new MigrationRunner();
		$runner->migrate();

		$this->assertArrayHasKey( 'bv_db_version', $stored );
		$this->assertNotSame( '0', $stored['bv_db_version'] );
	}

	/**
	 * Reads the stored database version option.
	 *
	 * @return void
	 */
	public function test_get_db_version(): void {
		Functions\when( 'get_option' )->justReturn( '0001' );

		$this->assertSame( '0001', MigrationRunner::get_db_version() );
	}
}
