<?php
/**
 * Variation creator.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Engine;

use BulkVariations\Services\HistoryLogger;

class VariationWriter {
	public function __construct( private ?HistoryLogger $history_logger = null ) {
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Row payloads.
	 * @return array<int, int>
	 */
	public function createBatch( int $product_id, array $rows, ?int $job_id = null ): array {
		global $wpdb;
		$created_ids = array();

		foreach ( $rows as $row ) {
			$variation_id = (int) wp_insert_post(
				array(
					'post_parent' => $product_id,
					'post_type'   => 'product_variation',
					'post_status' => 'publish',
					'post_title'  => '',
				)
			);
			if ( $variation_id <= 0 ) {
				continue;
			}

			$created_ids[] = $variation_id;
			$meta_pairs    = $this->extractMetaPairs( $row );
			foreach ( $meta_pairs as $meta_key => $meta_value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
						$variation_id,
						$meta_key,
						$meta_value
					)
				);

				if ( null !== $this->history_logger && null !== $job_id ) {
					$this->history_logger->recordChange( $job_id, 'variation', $variation_id, $meta_key, null, (string) $meta_value );
				}
			}
		}

		if ( null !== $this->history_logger ) {
			$this->history_logger->flush();
		}

		return $created_ids;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, string>
	 */
	private function extractMetaPairs( array $row ): array {
		$out = array();
		$attributes = $row['attributes'] ?? array();
		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $key => $value ) {
				$out[ (string) $key ] = (string) $value;
			}
		}

		foreach ( $row as $key => $value ) {
			if ( str_starts_with( (string) $key, 'attribute_' ) ) {
				$out[ (string) $key ] = (string) $value;
			}
		}

		if ( isset( $row['sku'] ) ) {
			$out['_sku'] = (string) $row['sku'];
		}
		if ( isset( $row['regular_price'] ) ) {
			$out['_regular_price'] = (string) $row['regular_price'];
			$out['_price']         = (string) $row['regular_price'];
		}
		if ( isset( $row['sale_price'] ) ) {
			$out['_sale_price'] = (string) $row['sale_price'];
			$out['_price']      = (string) $row['sale_price'];
		}

		return $out;
	}
}

