<?php
/**
 * Attribute matrix utilities.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Engine;

class AttributeMatrix {
	/**
	 * @param array<string, array<int, string>> $attributes
	 * @return array<string, array<int, string>>
	 */
	public function normalize( array $attributes ): array {
		$normalized = array();
		foreach ( $attributes as $taxonomy => $values ) {
			$key = str_starts_with( $taxonomy, 'attribute_' ) ? $taxonomy : 'attribute_' . $taxonomy;
			$normalized[ $key ] = array_values(
				array_filter(
					array_map( 'strval', $values ),
					static fn( string $value ): bool => '' !== $value
				)
			);
		}
		return $normalized;
	}

	/**
	 * @param array<string, array<int, string>> $attributes
	 * @return array<int, array<string, string>>
	 */
	public function cartesian( array $attributes ): array {
		$combinations = array( array() );
		foreach ( $attributes as $key => $values ) {
			$next = array();
			foreach ( $combinations as $row ) {
				foreach ( $values as $value ) {
					$copy         = $row;
					$copy[ $key ] = $value;
					$next[]       = $copy;
				}
			}
			$combinations = $next;
		}
		return $combinations;
	}
}

