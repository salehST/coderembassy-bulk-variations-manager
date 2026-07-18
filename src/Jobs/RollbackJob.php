<?php
/**
 * Rollback worker.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Jobs;

use BulkVariations\Engine\BulkEditor;

class RollbackJob {
	public function __construct( private BulkEditor $editor ) {
	}

	/**
	 * @param array<int, array<string, mixed>> $deltas
	 * @return array<string, mixed>
	 */
	public function run( int $job_id, array $deltas ): array {
		$rows = array();
		$processed_items = 0;
		foreach ( $deltas as $delta ) {
			$variation_id = (int) ( $delta['object_id'] ?? 0 );
			$field        = (string) ( $delta['field'] ?? '' );
			if ( $variation_id <= 0 || '' === $field ) {
				continue;
			}
			$editor_field = $this->toEditorField( $field );
			if ( ! isset( $rows[ $variation_id ] ) ) {
				$rows[ $variation_id ] = array( 'variation_id' => $variation_id );
			}
			$rows[ $variation_id ][ $editor_field ] = $delta['new_value'] ?? '';
			++$processed_items;
		}
		$result = $this->editor->processChunk( $job_id, array_values( $rows ) );
		if ( empty( $result['errors'] ) ) {
			$result['processed'] = $processed_items;
		}
		return $result;
	}

	private function toEditorField( string $field ): string {
		$map = array(
			'_sku'                   => 'sku',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_stock'                 => 'stock_quantity',
			'_stock_status'          => 'stock_status',
			'_manage_stock'          => 'manage_stock',
			'_virtual'               => 'virtual',
			'_downloadable'          => 'downloadable',
			'_downloadable_files'    => 'downloadable_files',
			'_download_limit'        => 'download_limit',
			'_download_expiry'       => 'download_expiry',
			'post_excerpt'           => 'description',
			'_sale_price_dates_from' => 'sale_from',
			'_sale_price_dates_to'   => 'sale_to',
			'_price'                 => '_price',
			'post_status'            => 'status',
			'product_shipping_class' => 'shipping_class_id',
		);

		return $map[ $field ] ?? $field;
	}
}
