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
		foreach ( $deltas as $delta ) {
			$variation_id = (int) ( $delta['object_id'] ?? 0 );
			$field        = (string) ( $delta['field'] ?? '' );
			if ( $variation_id <= 0 || '' === $field ) {
				continue;
			}
			$editor_field = $this->toEditorField( $field );
			$rows[]       = array(
				'variation_id' => $variation_id,
				$editor_field  => $delta['new_value'] ?? '',
			);
		}
		return $this->editor->processChunk( $job_id, $rows );
	}

	private function toEditorField( string $field ): string {
		$map = array(
			'_sku'                   => 'sku',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_stock'                 => 'stock_quantity',
			'_stock_status'          => 'stock_status',
			'_sale_price_dates_from' => 'sale_from',
			'_sale_price_dates_to'   => 'sale_to',
			'_price'                 => '_price',
		);

		return $map[ $field ] ?? $field;
	}
}

