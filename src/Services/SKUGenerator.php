<?php
/**
 * SKU generation and duplicate checks.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Services;

class SKUGenerator {
	/**
	 * @param array<string, string> $attributes Attribute map without attribute_ prefix.
	 * @param array<string, mixed>  $context    Replacement context.
	 */
	public function generate( string $template, array $attributes, array $context = array() ): string {
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}
		foreach ( $attributes as $key => $value ) {
			$replacements[ '{attr_' . $key . '}' ] = (string) $value;
		}
		return strtr( $template, $replacements );
	}

	/**
	 * @param array<int, string> $skus Candidate SKUs.
	 * @return array<int, string>
	 */
	public function validateUnique( array $skus ): array {
		if ( empty( $skus ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value IN ($placeholders)",
				...array_values( $skus )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return array_values( array_map( 'strval', $rows ) );
	}
}

