<?php
/**
 * Variation creator.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Engine;

use CoderEmbassy\BulkVariationsManager\Services\HistoryLogger;

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
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch creation writes known postmeta rows directly.
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

		$this->clearProductCaches( $product_id, $created_ids );

		return $created_ids;
	}

	/**
	 * Meta is written with direct queries, so WooCommerce has to be told the
	 * parent and its new variations changed or the storefront serves stale
	 * prices until the next unrelated product save.
	 *
	 * @param array<int, int> $variation_ids Newly created variation IDs.
	 */
	private function clearProductCaches( int $product_id, array $variation_ids ): void {
		if ( empty( $variation_ids ) ) {
			return;
		}

		$ids = array_merge( $variation_ids, $product_id > 0 ? array( $product_id ) : array() );

		foreach ( $ids as $id ) {
			if ( function_exists( 'clean_post_cache' ) ) {
				clean_post_cache( $id );
			}
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $id );
			}
		}

		if ( $product_id > 0 && function_exists( 'wc_update_product_lookup_tables' ) ) {
			wc_update_product_lookup_tables( $product_id );
		}
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
