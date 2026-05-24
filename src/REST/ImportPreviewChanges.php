<?php
/**
 * Import preview diff builder.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\REST;

use BulkVariations\Engine\VariationRepository;

class ImportPreviewChanges {
	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	public static function build( array $rows, VariationRepository $repository ): array {
		$variation_ids = array_values(
			array_filter(
				array_map( static fn( array $row ): int => (int) ( $row['variation_id'] ?? 0 ), $rows )
			)
		);
		$meta_snapshot   = $repository->getMetaSnapshot( $variation_ids );
		$status_snapshot = $repository->getPostStatusSnapshot( $variation_ids );
		$out             = array();
		$create_index    = -1;

		$meta_map = array(
			'sku'            => '_sku',
			'regular_price'  => '_regular_price',
			'sale_price'     => '_sale_price',
			'sale_from'      => '_sale_price_dates_from',
			'sale_to'        => '_sale_price_dates_to',
			'stock_quantity' => '_stock',
			'stock_status'   => '_stock_status',
		);

		foreach ( $rows as $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? 0 );
			$object_id    = $variation_id > 0 ? $variation_id : $create_index--;
			$current_meta = $meta_snapshot[ $variation_id ] ?? array();
			$current_post = (string) ( $status_snapshot[ $variation_id ] ?? '' );

			foreach ( $row as $field => $new_value_raw ) {
				$field = (string) $field;
				if ( 'product_id' === $field || 'variation_id' === $field ) {
					continue;
				}
				$new_value = (string) $new_value_raw;
				if ( '' === $new_value && $variation_id <= 0 ) {
					continue;
				}

				if ( 'status' === $field ) {
					if ( $variation_id > 0 && $current_post === $new_value ) {
						continue;
					}
					$out[] = array(
						'variation_id' => $object_id,
						'object_id'    => $object_id,
						'field'        => 'status',
						'old_value'    => $variation_id > 0 ? $current_post : '',
						'new_value'    => $new_value,
					);
					continue;
				}

				$key = $meta_map[ $field ] ?? ( str_starts_with( $field, 'attribute_' ) ? $field : '' );
				if ( '' === $key ) {
					continue;
				}
				$old_value = (string) ( $current_meta[ $key ] ?? '' );
				if ( in_array( $field, array( 'regular_price', 'sale_price' ), true ) && '' !== $old_value && '' !== $new_value ) {
					if ( (float) $old_value === (float) $new_value ) {
						continue;
					}
				} elseif ( $variation_id > 0 && $old_value === $new_value ) {
					continue;
				}

				$out[] = array(
					'variation_id' => $object_id,
					'object_id'    => $object_id,
					'field'        => $field,
					'old_value'    => $variation_id > 0 ? $old_value : '',
					'new_value'    => $new_value,
				);
			}
		}

		return $out;
	}
}

