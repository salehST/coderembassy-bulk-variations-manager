<?php
// phpcs:ignoreFile
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
/**
 * Minimal WP-CLI stubs for unit tests and PHPStan.
 *
 * @package BulkVariations
 */

if ( ! class_exists( 'WP_CLI_Command', false ) ) {
	/**
	 * WP-CLI command base (stub).
	 */
	class WP_CLI_Command { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
	}
}

if ( ! class_exists( 'WP_CLI', false ) ) {
	/**
	 * WP-CLI facade (stub).
	 */
	class WP_CLI { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @param string              $name     Command name.
		 * @param class-string|object $callable Handler.
		 * @return void
		 */
		public static function add_command( string $name, $callable ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		}

		/**
		 * @param string $message Success message.
		 * @return void
		 */
		public static function success( string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		}

		/**
		 * @param string $message Error message.
		 * @return never
		 */
		public static function error( string $message ) {
			throw new \RuntimeException( $message );
		}

		/**
		 * @param string $message Warning message.
		 * @return void
		 */
		public static function warning( string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		}
	}
}
