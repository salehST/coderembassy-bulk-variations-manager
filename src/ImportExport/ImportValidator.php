<?php
/**
 * CSV import row validator.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\ImportExport;

class ImportValidator {
	/**
	 * @param array<string, mixed>              $row
	 * @param array<string, int>                $existing_skus
	 * @param array<string, bool>               $seen_skus
	 * @param array<string, array<int, string>> $attribute_rules
	 * @return array<string, mixed>
	 */
	public function validateRow(
		array $row,
		array $existing_skus,
		array $seen_skus,
		array $attribute_rules = array()
	): array {
		$issues   = array();
		$warnings = array();
		$fixed    = array();

		foreach ( $row as $key => $value ) {
			$fixed[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}

		$sku = (string) ( $fixed['sku'] ?? '' );
		if ( '' !== $sku && ! preg_match( '/^[A-Za-z0-9._-]+$/', $sku ) ) {
			$issues[] = 'Invalid sku format.';
		}
		if ( '' !== $sku && isset( $seen_skus[ $sku ] ) ) {
			$issues[] = 'Duplicate sku in import batch.';
		}

		$this->validateDecimalFields( $fixed, $issues );
		$this->validateStockQuantity( $fixed, $issues );
		$this->validateChoiceFields( $fixed, $issues );

		$fixed = $this->normalizeSaleDates( $fixed, $warnings );

		$has_variation_id = ! empty( $fixed['variation_id'] );
		if ( ! $has_variation_id && '' !== $sku && isset( $existing_skus[ $sku ] ) ) {
			$fixed['variation_id'] = (int) $existing_skus[ $sku ];
			$has_variation_id      = true;
		}

		$is_create = ! $has_variation_id;
		if ( $is_create ) {
			if ( empty( $fixed['product_id'] ) ) {
				$issues[] = 'Create rows require product_id.';
			}
			$attr_keys = array_values(
				array_filter(
					array_keys( $fixed ),
					static fn( string $key ): bool => str_starts_with( $key, 'attribute_' )
				)
			);
			if ( empty( $attr_keys ) ) {
				$issues[] = 'Create rows require at least one attribute_ column.';
			}
		}

		foreach ( $attribute_rules as $column => $allowed ) {
			if ( ! isset( $fixed[ $column ] ) || '' === (string) $fixed[ $column ] ) {
				continue;
			}
			if ( ! in_array( (string) $fixed[ $column ], $allowed, true ) ) {
				$warnings[] = sprintf(
					'Value "%s" is not in known options for %s.',
					(string) $fixed[ $column ],
					$column
				);
			}
		}

		return array(
			'is_valid'  => empty( $issues ),
			'issues'    => $issues,
			'warnings'  => $warnings,
			'fixed_row' => $fixed,
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, string>   $issues
	 */
	private function validateDecimalFields( array $row, array &$issues ): void {
		foreach ( array( 'regular_price', 'sale_price' ) as $price_field ) {
			if ( ! array_key_exists( $price_field, $row ) || '' === (string) $row[ $price_field ] ) {
				continue;
			}

			if ( ! is_numeric( $row[ $price_field ] ) ) {
				$issues[] = $price_field . ' must be numeric.';
				continue;
			}

			if ( (float) $row[ $price_field ] < 0 ) {
				$issues[] = $price_field . ' cannot be negative.';
			}
		}
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, string>   $issues
	 */
	private function validateStockQuantity( array $row, array &$issues ): void {
		if ( ! array_key_exists( 'stock_quantity', $row ) || '' === (string) $row['stock_quantity'] ) {
			return;
		}

		if ( ! preg_match( '/^\d+$/', (string) $row['stock_quantity'] ) ) {
			$issues[] = 'stock_quantity must be a whole number.';
		}
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, string>   $issues
	 */
	private function validateChoiceFields( array $row, array &$issues ): void {
		$choices = array(
			'stock_status' => array( 'instock', 'outofstock', 'onbackorder' ),
			'status'       => array( 'publish', 'private', 'draft', 'pending' ),
		);

		foreach ( $choices as $field => $allowed ) {
			if ( ! array_key_exists( $field, $row ) || '' === (string) $row[ $field ] ) {
				continue;
			}

			if ( ! in_array( (string) $row[ $field ], $allowed, true ) ) {
				$issues[] = $field . ' must be one of: ' . implode( ', ', $allowed ) . '.';
			}
		}
	}

	/**
	 * @param array<int, string> $skus
	 * @return array<string, int>
	 */
	public function lookupExistingSkus( array $skus ): array {
		if ( empty( $skus ) ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value IN ($placeholders)",
			$skus
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['meta_value'] ] = (int) $row['post_id'];
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, string>   $warnings
	 * @return array<string, mixed>
	 */
	private function normalizeSaleDates( array $row, array &$warnings ): array {
		foreach ( array( 'sale_from', 'sale_to' ) as $key ) {
			if ( ! isset( $row[ $key ] ) || '' === (string) $row[ $key ] ) {
				continue;
			}
			$normalized = $this->normalizeDate( (string) $row[ $key ] );
			if ( null !== $normalized ) {
				$row[ $key ] = $normalized;
			}
		}

		if ( isset( $row['sale_from'], $row['sale_to'] ) && '' !== (string) $row['sale_from'] && '' !== (string) $row['sale_to'] ) {
			$from = strtotime( (string) $row['sale_from'] );
			$to   = strtotime( (string) $row['sale_to'] );
			if ( false !== $from && false !== $to && $from > $to ) {
				$warnings[] = 'sale_from is after sale_to.';
			}
		}

		return $row;
	}

	private function normalizeDate( string $value ): ?string {
		$value = trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $matches[3], (int) $matches[1], (int) $matches[2] );
		}
		return null;
	}
}
