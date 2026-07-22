<?php
/**
 * Import worker.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Jobs;

use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Engine\BulkEditor;
use CoderEmbassy\BulkVariationsManager\Engine\VariationWriter;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportValidator;

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

		$create_rows_by_product = array();
		$update_rows = array();
		foreach ( $rows as $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? 0 );
			if ( $variation_id > 0 ) {
				$update_rows[] = $this->mapRowToBulkUpdate( $row, $variation_id );
				continue;
			}
			$product_id = (int) ( $row['product_id'] ?? ( $meta['product_id'] ?? 0 ) );
			if ( $product_id > 0 ) {
				$create_rows_by_product[ $product_id ][] = $row;
			}
		}

		$processed = 0;
		$errors    = array();

		foreach ( $create_rows_by_product as $product_id => $create_rows ) {
			$created    = $this->writer->createBatch( (int) $product_id, $create_rows, $job_id );
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
