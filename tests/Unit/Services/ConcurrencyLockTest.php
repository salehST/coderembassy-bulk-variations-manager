<?php
/**
 * ConcurrencyLock unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Services
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Services\ConcurrencyLock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Services\ConcurrencyLock
 */
class ConcurrencyLockTest extends TestCase {

	/**
	 * In-memory options store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->options = array();

		Functions\when( 'sanitize_key' )->returnArg( 1 );

		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				return $this->options[ $key ] ?? $default;
			}
		);

		Functions\when( 'add_option' )->alias(
			function ( string $key, $value ) {
				if ( array_key_exists( $key, $this->options ) ) {
					return false;
				}
				$this->options[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( string $key ) {
				unset( $this->options[ $key ] );
				return true;
			}
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
	 * Acquire and release round-trip.
	 *
	 * @return void
	 */
	public function test_acquire_and_release(): void {
		$lock = new ConcurrencyLock();

		$this->assertTrue( $lock->acquire( 'product_42' ) );
		$this->assertTrue( $lock->isHeldBy( 'product_42' ) );
		$this->assertFalse( $lock->acquire( 'product_42' ) );

		$this->assertTrue( $lock->release( 'product_42' ) );
		$this->assertFalse( $lock->isHeldBy( 'product_42' ) );
	}
}
