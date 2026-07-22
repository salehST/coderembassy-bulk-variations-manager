<?php
/**
 * PHPStan bootstrap for WP shims.
 */

declare(strict_types=1);

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'CODEREMBASSY_BVM_VERSION' ) ) {
	define( 'CODEREMBASSY_BVM_VERSION', '0.1.0' );
}

if ( ! defined( 'CODEREMBASSY_BVM_PLUGIN_FILE' ) ) {
	define( 'CODEREMBASSY_BVM_PLUGIN_FILE', __DIR__ . '/coderembassy-bulk-variations-manager.php' );
}

if ( ! defined( 'CODEREMBASSY_BVM_PLUGIN_PATH' ) ) {
	define( 'CODEREMBASSY_BVM_PLUGIN_PATH', __DIR__ . '/' );
}

if ( ! defined( 'CODEREMBASSY_BVM_PLUGIN_URL' ) ) {
	define( 'CODEREMBASSY_BVM_PLUGIN_URL', 'http://example.test/wp-content/plugins/coderembassy-bulk-variations-manager/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( file_exists( __DIR__ . '/tests/stubs/wp-cli.php' ) ) {
	require_once __DIR__ . '/tests/stubs/wp-cli.php';
}

if ( file_exists( __DIR__ . '/tests/stubs/wp-cli-utils.php' ) ) {
	require_once __DIR__ . '/tests/stubs/wp-cli-utils.php';
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	/**
	 * @param string            $hook Action hook.
	 * @param array<int, mixed> $args Hook args.
	 * @param string            $group Action Scheduler group.
	 * @return int
	 */
	function as_enqueue_async_action( string $hook, array $args = array(), string $group = '' ): int {
		unset( $hook, $args, $group );
		return 1;
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	/**
	 * @param int               $timestamp Run timestamp.
	 * @param string            $hook      Action hook.
	 * @param array<int, mixed> $args      Hook args.
	 * @param string            $group     Action Scheduler group.
	 * @return int
	 */
	function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '' ): int {
		unset( $timestamp, $hook, $args, $group );
		return 1;
	}
}

