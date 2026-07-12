<?php
/**
 * CSV template builder.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\ImportExport;

class ImportCsvTemplate {
	/**
	 * @param array<string, mixed> $readiness
	 * @return array<int, string>
	 */
	public function getHeaders( array $readiness ): array {
		$headers = array( 'product_id', 'variation_id', 'sku' );
		foreach ( $readiness['attributes'] ?? array() as $attribute ) {
			$column = (string) ( $attribute['csv_column'] ?? '' );
			if ( '' !== $column ) {
				$headers[] = $column;
			}
		}

		return array_merge(
			$headers,
			array(
				'regular_price',
				'sale_price',
				'sale_from',
				'sale_to',
				'stock_quantity',
				'stock_status',
				'status',
			)
		);
	}

	/**
	 * @param array<string, mixed> $readiness
	 * @return array<int, array<string, string>>
	 */
	public function getSampleRows( array $readiness ): array {
		$product_id = (string) ( $readiness['product_id'] ?? '' );
		$first      = array(
			'product_id'    => $product_id,
			'variation_id'  => '',
			'sku'           => 'your-existing-sku',
			'regular_price' => '19.99',
		);

		$create = array(
			'product_id'    => $product_id,
			'variation_id'  => '',
			'sku'           => 'new-variation-sku',
			'regular_price' => '29.99',
		);

		$existing = $readiness['existing_combinations'][0] ?? array();
		foreach ( $readiness['attributes'] ?? array() as $attribute ) {
			$column = (string) ( $attribute['csv_column'] ?? '' );
			if ( '' === $column ) {
				continue;
			}
			$first[ $column ] = (string) ( $existing[ $column ] ?? '' );
			$create[ $column ] = (string) ( $this->pickAlternativeOption( $attribute, $first[ $column ] ) );
		}

		return array( $first, $create );
	}

	/**
	 * @param array<string, mixed> $readiness
	 */
	public function build( array $readiness ): string {
		$headers = $this->getHeaders( $readiness );
		$rows    = $this->getSampleRows( $readiness );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fp = fopen( 'php://temp', 'r+' );
		if ( false === $fp ) {
			return '';
		}
		fputcsv( $fp, $headers );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $header ) {
				$line[] = (string) ( $row[ $header ] ?? '' );
			}
			fputcsv( $fp, $line );
		}
		rewind( $fp );
		$csv = stream_get_contents( $fp );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $fp );
		return is_string( $csv ) ? $csv : '';
	}

	/**
	 * @param array<string, mixed> $readiness
	 */
	public function getFilename( array $readiness ): string {
		return 'import-template-product-' . (int) ( $readiness['product_id'] ?? 0 ) . '.csv';
	}

	/**
	 * @param array<string, mixed> $attribute
	 */
	private function pickAlternativeOption( array $attribute, string $existing ): string {
		$options = $attribute['options'] ?? array();
		foreach ( $options as $option ) {
			$slug = (string) ( $option['slug'] ?? '' );
			if ( '' !== $slug && $slug !== $existing ) {
				return $slug;
			}
		}
		return (string) ( $options[0]['slug'] ?? '' );
	}
}

