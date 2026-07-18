<?php
/**
 * Generate variations worker.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Jobs;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\VariationGenerator;

class GenerateJob {
	public function __construct(
		private JobRepositoryInterface $jobs,
		private VariationGenerator $generator
	) {
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	public function run( int $job_id, array $rows ): array {
		unset( $job_id );
		$processed = 0;
		foreach ( $rows as $row ) {
			$product_id = (int) ( $row['product_id'] ?? 0 );
			$attributes = is_array( $row['attributes'] ?? null ) ? $row['attributes'] : array();
			if ( $product_id <= 0 || empty( $attributes ) ) {
				continue;
			}
			$this->generator->generateCombinations( $product_id, $attributes );
			++$processed;
		}
		return array( 'processed' => $processed, 'errors' => array() );
	}
}

