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
		$sql          = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value IN ($placeholders)",
			...$skus
		);
		$rows = $wpdb->get_col( $sql );
		return array_values( array_map( 'strval', $rows ) );
	}
}

