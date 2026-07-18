<?php
/**
 * Product import readiness service.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\ImportExport;

use BulkVariations\Engine\AttributeMatrix;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;

class ImportAttributeReadiness {
	public function __construct(
		private AttributeMatrix $matrix,
		private VariationRepository $repository,
		private ?VariationGenerator $generator = null
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getForProduct( int $product_id ): array {
		$post = get_post( $product_id );
		if ( ! is_object( $post ) ) {
			return array(
				'product_id'   => $product_id,
				'is_variable'  => false,
				'message'      => 'Product not found.',
				'attributes'   => array(),
				'product_name' => '',
			);
		}

		$attributes_meta = get_post_meta( $product_id, '_product_attributes', true );
		$attributes      = array();
		$matrix_input    = array();

		if ( is_array( $attributes_meta ) ) {
			foreach ( $attributes_meta as $taxonomy => $config ) {
				if ( ! is_array( $config ) || empty( $config['is_variation'] ) ) {
					continue;
				}
				$taxonomy_name = (string) ( $config['name'] ?? $taxonomy );
				$csv_column    = 'attribute_' . sanitize_title( $taxonomy_name );
				$options       = array();
				$slugs         = array();
				if ( ! empty( $config['is_taxonomy'] ) ) {
					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy_name,
							'hide_empty' => false,
						)
					);
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$slug      = (string) ( $term->slug ?? '' );
							$options[] = array(
								'slug'  => $slug,
								'label' => (string) ( $term->name ?? $slug ),
							);
							if ( '' !== $slug ) {
								$slugs[] = $slug;
							}
						}
					}
				} else {
					$raw_options = (string) ( $config['value'] ?? '' );
					if ( '' !== $raw_options ) {
						foreach ( array_map( 'trim', explode( '|', $raw_options ) ) as $option ) {
							if ( '' === $option ) {
								continue;
							}
							$slug      = sanitize_title( $option );
							$options[] = array(
								'slug'  => $slug,
								'label' => $option,
							);
							$slugs[]   = $slug;
						}
					}
				}

				$attributes[]           = array(
					'field'      => $csv_column,
					'csv_column' => $csv_column,
					'label'      => $this->formatAttributeLabel( $csv_column ),
					'options'    => $options,
				);
				$matrix_input[ $taxonomy_name ] = $slugs;
			}
		}

		$existing_signatures = $this->repository->getExistingCombinationSignatures( $product_id );
		$total_possible      = 1;
		foreach ( $matrix_input as $slugs ) {
			$total_possible *= max( 1, count( $slugs ) );
		}
		if ( empty( $matrix_input ) ) {
			$total_possible = 0;
		}

		$remaining = max( 0, $total_possible - count( $existing_signatures ) );
		if ( null !== $this->generator ) {
			$remaining = count( $this->generator->generateCombinations( $product_id, $matrix_input ) );
		}

		return array(
			'product_id'                   => $product_id,
			'product_name'                 => (string) get_the_title( $product_id ),
			'is_variable'                  => (bool) has_term( 'variable', 'product_type', $product_id ),
			'attributes'                   => $attributes,
			'total_possible_combinations'  => $total_possible,
			'all_combinations_exist'       => 0 === $remaining,
			'remaining_combination_count'  => $remaining,
			'existing_combinations'        => $this->decodeSignatures( $existing_signatures ),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, array<int, string>>
	 */
	public function getSlugIndex( array $payload ): array {
		$index = array();
		foreach ( $payload['attributes'] ?? array() as $attribute ) {
			$key = (string) ( $attribute['csv_column'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$index[ $key ] = array_values(
				array_filter(
					array_map(
						static fn( array $option ): string => (string) ( $option['slug'] ?? '' ),
						is_array( $attribute['options'] ?? null ) ? $attribute['options'] : array()
					)
				)
			);
		}
		return $index;
	}

	/**
	 * @param array<int, array<string, mixed>> $editor_rows
	 * @return array<int, array<string, string>>
	 */
	public function getEditorAttributeColumns( int $product_id, array $editor_rows ): array {
		$payload = $this->getForProduct( $product_id );
		$columns = array();
		foreach ( $payload['attributes'] ?? array() as $attribute ) {
			$field = (string) ( $attribute['csv_column'] ?? '' );
			if ( '' === $field ) {
				continue;
			}
			$columns[ $field ] = array(
				'field'   => $field,
				'label'   => (string) ( $attribute['label'] ?? $this->formatAttributeLabel( $field ) ),
				'options' => is_array( $attribute['options'] ?? null ) ? $attribute['options'] : array(),
			);
		}

		foreach ( $editor_rows as $row ) {
			foreach ( $row as $key => $value ) {
				unset( $value );
				$key = (string) $key;
				if ( ! str_starts_with( $key, 'attribute_' ) || isset( $columns[ $key ] ) ) {
					continue;
				}
				$columns[ $key ] = array(
					'field'   => $key,
					'label'   => $this->formatAttributeLabel( $key ),
					'options' => array(),
				);
			}
		}

		return array_values( $columns );
	}

	/**
	 * @param array<string, bool> $signatures
	 * @return array<int, array<string, string>>
	 */
	private function decodeSignatures( array $signatures ): array {
		$out = array();
		foreach ( array_keys( $signatures ) as $signature ) {
			$decoded = json_decode( (string) $signature, true );
			if ( is_array( $decoded ) ) {
				$out[] = array_map( 'strval', $decoded );
			}
		}
		return $out;
	}

	private function formatAttributeLabel( string $field ): string {
		$raw = preg_replace( '/^attribute_(pa_)?/', '', $field );
		$raw = str_replace( array( '-', '_' ), ' ', (string) $raw );
		return ucwords( $raw );
	}
}

