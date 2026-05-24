<?php
/**
 * Import worker.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Jobs;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\BulkEditor;
use BulkVariations\Engine\VariationWriter;
use BulkVariations\ImportExport\ImportValidator;

class ImportJob {
	public function __construct(
		private JobRepositoryInterface $jobs,
		private ImportValidator $validator,
		private VariationWriter $writer,
		private BulkEditor $editor
	) {
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	public function run( int $job_id, array $rows ): array {
		$job = $this->jobs->get( $job_id );
		$meta = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();

		$create_rows = array();
		$update_rows = array();
		foreach ( $rows as $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? 0 );
			if ( $variation_id > 0 ) {
				$update_rows[] = $this->mapRowToBulkUpdate( $row, $variation_id );
				continue;
			}
			$create_rows[] = $row;
		}

		$processed = 0;
		$errors    = array();

		if ( ! empty( $create_rows ) ) {
			$product_id = (int) ( $create_rows[0]['product_id'] ?? ( $meta['product_id'] ?? 0 ) );
			$created    = $this->writer->createBatch( $product_id, $create_rows, $job_id );
			$processed += count( $created );
		}

		if ( ! empty( $update_rows ) ) {
			$result    = $this->editor->processChunk( $job_id, $update_rows );
			$processed += (int) ( $result['processed'] ?? 0 );
			$errors     = array_merge( $errors, $result['errors'] ?? array() );
		}

		unset( $this->validator );

		return array(
			'processed' => $processed,
			'errors'    => $errors,
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function mapRowToBulkUpdate( array $row, int $variation_id ): array {
		$out = array( 'variation_id' => $variation_id );
		$map = array(
			'sku',
			'regular_price',
			'sale_price',
			'sale_from',
			'sale_to',
			'stock_quantity',
			'stock_status',
			'status',
		);
		foreach ( $map as $field ) {
			if ( ! array_key_exists( $field, $row ) || '' === (string) $row[ $field ] ) {
				continue;
			}
			$out[ $field ] = $row[ $field ];
		}
		if ( array_key_exists( 'stock_quantity', $out ) ) {
			$out['manage_stock'] = 'yes';
		}
		return $out;
	}
}

