<?php
/**
 * Variation data access.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Engine;

class VariationRepository {
	/**
	 * @param array<int, int>    $variation_ids Variation IDs.
	 * @param array<int, string> $meta_keys     Meta keys.
	 * @return array<int, array<string, string>>
	 */
	public function getMetaSnapshot( array $variation_ids, array $meta_keys = array() ): array {
		if ( empty( $variation_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
		$args         = $variation_ids;
		$where        = "post_id IN ($placeholders)";
		if ( ! empty( $meta_keys ) ) {
			$meta_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$where            .= " AND meta_key IN ($meta_placeholders)";
			$args              = array_merge( $args, array_values( $meta_keys ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE {$where} ORDER BY post_id ASC, meta_key ASC, meta_id DESC", ...$args ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$out  = array();
		foreach ( $rows as $row ) {
			$post_id = (int) ( $row['post_id'] ?? 0 );
			$key     = (string) ( $row['meta_key'] ?? '' );
			$value   = (string) ( $row['meta_value'] ?? '' );
			if ( '' === $key || isset( $out[ $post_id ][ $key ] ) ) {
				continue;
			}
			$out[ $post_id ][ $key ] = $value;
		}
		return $out;
	}

	/**
	 * @param int $product_id Parent product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function listVariations( int $product_id ): array {
		global $wpdb;

		$parent_thumb_id = function_exists( 'get_post_thumbnail_id' )
			? (int) get_post_thumbnail_id( $product_id )
			: 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_status, post_title FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'",
				$product_id
			),
			ARRAY_A
		);
		if ( empty( $posts ) ) {
			return array();
		}

		$ids      = array_map(
			static fn( array $row ): int => (int) ( $row['ID'] ?? ( $row['variation_id'] ?? 0 ) ),
			$posts
		);
		$ids      = array_values( array_filter( $ids ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$snapshot = $this->getMetaSnapshot( $ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lookup_table = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lookup_by_id = array();
		if ( is_string( $lookup_table ) && '' !== $lookup_table ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$lookup_rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT product_id, sku FROM {$lookup_table} WHERE product_id IN ($placeholders)", ...$ids ),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			foreach ( $lookup_rows as $lookup ) {
				$lookup_by_id[ (int) $lookup['product_id'] ] = (string) $lookup['sku'];
			}
		}

		$placeholders   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$like           = $wpdb->esc_like( 'attribute_' ) . '%';
		$args           = array_merge( $ids, array( $like ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$attribute_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key LIKE %s", ...$args ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$attributes = array();
		foreach ( $attribute_rows as $row ) {
			$attributes[ (int) $row['post_id'] ][ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}

		$out = array();
		foreach ( $posts as $post ) {
			$id   = (int) $post['ID'];
			$meta = $snapshot[ $id ] ?? array();
			$sku  = (string) ( $meta['_sku'] ?? '' );
			if ( '' === $sku && isset( $lookup_by_id[ $id ] ) ) {
				$sku = $lookup_by_id[ $id ];
			}
			$thumb_id  = (int) ( $meta['_thumbnail_id'] ?? 0 );
			if ( $thumb_id <= 0 ) {
				$thumb_id = $parent_thumb_id;
			}
			$image_url = '';
			if ( $thumb_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
				$image_url = (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
			}

			$row = array(
				'variation_id'   => $id,
				'product_id'     => $product_id,
				'post_status'    => (string) ( $post['post_status'] ?? 'publish' ),
				'status'         => (string) ( $post['post_status'] ?? 'publish' ),
				'title'          => (string) ( $post['post_title'] ?? '' ),
				'sku'            => $sku,
				'image_id'       => $thumb_id,
				'image_url'      => $image_url,
				'regular_price'  => (string) ( $meta['_regular_price'] ?? '' ),
				'sale_price'     => (string) ( $meta['_sale_price'] ?? '' ),
				'sale_from'      => $this->formatTimestampDate( (string) ( $meta['_sale_price_dates_from'] ?? '' ) ),
				'sale_to'        => $this->formatTimestampDate( (string) ( $meta['_sale_price_dates_to'] ?? '' ) ),
				'stock_quantity' => (string) ( $meta['_stock'] ?? '' ),
				'stock_status'   => (string) ( $meta['_stock_status'] ?? '' ),
			);
			foreach ( $attributes[ $id ] ?? array() as $meta_key => $meta_value ) {
				$row[ $meta_key ] = $meta_value;
			}
			$out[] = $row;
		}
		return $out;
	}

	private function formatTimestampDate( string $value ): string {
		if ( '' === $value || ! ctype_digit( $value ) ) {
			return '';
		}
		return gmdate( 'Y-m-d', (int) $value );
	}

	/**
	 * @param int $product_id Parent product ID.
	 * @return array<string, bool>
	 */
	public function getExistingCombinationSignatures( int $product_id ): array {
		global $wpdb;

		$like = $wpdb->esc_like( 'attribute_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS variation_id, pm.meta_key, pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_parent = %d
					AND p.post_type = 'product_variation'
					AND pm.meta_key LIKE %s
				ORDER BY p.ID ASC, pm.meta_key ASC",
				$product_id,
				$like
			),
			ARRAY_A
		);
		$out  = array();

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			$by_variation = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$variation_id = (int) ( $row['variation_id'] ?? $row['post_id'] ?? 0 );
				$meta_key     = (string) ( $row['meta_key'] ?? '' );
				$meta_value   = (string) ( $row['meta_value'] ?? '' );
				if ( $variation_id <= 0 || '' === $meta_key || '' === $meta_value ) {
					continue;
				}
				$by_variation[ $variation_id ][ $meta_key ] = $meta_value;
			}

			foreach ( $by_variation as $attrs ) {
				ksort( $attrs );
				$out[ wp_json_encode( $attrs ) ] = true;
			}

			return $out;
		}

		$variations = $this->listVariations( $product_id );
		foreach ( $variations as $variation ) {
			if ( ! is_array( $variation ) ) {
				continue;
			}
			$attrs = array();
			foreach ( $variation as $key => $value ) {
				if ( is_string( $key ) && str_starts_with( $key, 'attribute_' ) && '' !== (string) $value ) {
					$attrs[ $key ] = (string) $value;
				}
			}
			if ( ! empty( $attrs ) ) {
				ksort( $attrs );
				$out[ wp_json_encode( $attrs ) ] = true;
			}
		}

		return $out;
	}

	/**
	 * @param array<int, int> $variation_ids Variation IDs.
	 * @return array<int, string>
	 */
	public function getPostStatusSnapshot( array $variation_ids ): array {
		if ( empty( $variation_ids ) ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_status FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND ID IN ($placeholders)", ...$variation_ids ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$out     = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['ID'] ] = (string) $row['post_status'];
		}
		return $out;
	}
}
