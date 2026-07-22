<?php
/**
 * WP-CLI Utils stubs for tests.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace WP_CLI;

if ( ! class_exists( __NAMESPACE__ . '\\Utils', false ) ) {
	class Utils {
		/**
		 * @param array<int, array<string, mixed>> $items
		 * @param array<int, string>               $fields
		 * @return void
		 */
		public static function format_items( string $format, array $items, array $fields ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		}
	}
}

