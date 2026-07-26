<?php
/**
 * Variation data access.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Engine;

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
		$variation_ids = array_values( array_unique( array_filter( array_map( 'absint', $variation_ids ) ) ) );
		$placeholders  = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
		$where         = "post_id IN ($placeholders)";
		$args          = $variation_ids;
		if ( ! empty( $meta_keys ) ) {
			$meta_keys         = array_values( array_map( 'strval', $meta_keys ) );
			$key_placeholders  = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$where            .= " AND meta_key IN ($key_placeholders)";
			$args              = array_merge( $args, $meta_keys );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder fragments and their values are generated together above.
		$sql  = $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE {$where} ORDER BY post_id ASC, meta_key ASC, meta_id DESC", ...$args );
		// phpcs:enable
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; this bulk snapshot is request-scoped.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
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

		$posts_sql = $wpdb->prepare(
			"SELECT ID, post_status, post_title, post_excerpt FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'",
			$product_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; results are assembled for the current admin request.
		$posts = $wpdb->get_results( $posts_sql, ARRAY_A );
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

		$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema existence check.
		$lookup_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup_table ) );
		$lookup_by_id = array();
		if ( $lookup_exists === $lookup_table ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Identifier and value placeholders are supplied together to prepare().
			$lookup_sql = $wpdb->prepare( "SELECT product_id, sku FROM %i WHERE product_id IN ($id_placeholders)", $lookup_table, ...$ids );
			// phpcs:enable
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; lookup data is request-scoped.
			$lookup_rows = $wpdb->get_results( $lookup_sql, ARRAY_A );
			foreach ( $lookup_rows as $lookup ) {
				$lookup_by_id[ (int) $lookup['product_id'] ] = (string) $lookup['sku'];
			}
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$attribute_like  = $wpdb->esc_like( 'attribute_' ) . '%';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Value placeholders and their values are generated together above.
		$attribute_sql = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($id_placeholders) AND meta_key LIKE %s",
			...array_merge( $ids, array( $attribute_like ) )
		);
		// phpcs:enable
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; attributes are request-scoped.
		$attribute_rows = $wpdb->get_results( $attribute_sql, ARRAY_A );
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
				'description'    => (string) ( $post['post_excerpt'] ?? '' ),
				'sku'            => $sku,
				'image_id'       => $thumb_id,
				'image_url'      => $image_url,
				'regular_price'  => (string) ( $meta['_regular_price'] ?? '' ),
				'sale_price'     => (string) ( $meta['_sale_price'] ?? '' ),
				'sale_from'      => $this->formatTimestampDate( (string) ( $meta['_sale_price_dates_from'] ?? '' ) ),
				'sale_to'        => $this->formatTimestampDate( (string) ( $meta['_sale_price_dates_to'] ?? '' ) ),
				'stock_quantity' => (string) ( $meta['_stock'] ?? '' ),
				'stock_status'   => (string) ( $meta['_stock_status'] ?? '' ),
				'manage_stock'   => (string) ( $meta['_manage_stock'] ?? '' ),
				'virtual'        => (string) ( $meta['_virtual'] ?? '' ),
				'downloadable'   => (string) ( $meta['_downloadable'] ?? '' ),
				'downloadable_files' => $this->formatDownloadableFiles( (string) ( $meta['_downloadable_files'] ?? '' ) ),
				'download_limit' => (string) ( $meta['_download_limit'] ?? '' ),
				'download_expiry' => (string) ( $meta['_download_expiry'] ?? '' ),
				'weight'         => (string) ( $meta['_weight'] ?? '' ),
				'length'         => (string) ( $meta['_length'] ?? '' ),
				'width'          => (string) ( $meta['_width'] ?? '' ),
				'height'         => (string) ( $meta['_height'] ?? '' ),
				'tax_class'      => (string) ( $meta['_tax_class'] ?? '' ),
				'shipping_class_id' => $this->getShippingClassId( $id ),
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

	private function getShippingClassId( int $variation_id ): string {
		if ( ! function_exists( 'wp_get_post_terms' ) ) {
			return '';
		}

		$terms = wp_get_post_terms(
			$variation_id,
			'product_shipping_class',
			array( 'fields' => 'ids' )
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return (string) absint( $terms[0] );
	}

	private function formatDownloadableFiles( string $value ): string {
		if ( '' === $value || ! function_exists( 'maybe_unserialize' ) ) {
			return '';
		}

		$files = maybe_unserialize( $value );
		if ( ! is_array( $files ) ) {
			return '';
		}

		$out = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$name = (string) ( $file['name'] ?? '' );
			$url  = (string) ( $file['file'] ?? '' );
			if ( '' === $name && '' === $url ) {
				continue;
			}
			$out[] = array(
				'name' => $name,
				'file' => $url,
			);
		}

		return empty( $out ) ? '' : (string) wp_json_encode( $out );
	}

	/**
	 * @param int $product_id Parent product ID.
	 * @return array<string, bool>
	 */
	public function getExistingCombinationSignatures( int $product_id ): array {
		global $wpdb;

		$sql  = $wpdb->prepare(
			"SELECT p.ID AS variation_id, pm.meta_key, pm.meta_value
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_parent = %d
				AND p.post_type = 'product_variation'
				AND pm.meta_key LIKE %s
			ORDER BY p.ID ASC, pm.meta_key ASC",
			$product_id,
			$wpdb->esc_like( 'attribute_' ) . '%'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; signatures are calculated for the current request.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
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
		$fields = $this->getPostFieldSnapshot( $variation_ids, 'post_status' );
		$out    = array();
		foreach ( $fields as $variation_id => $value ) {
			$out[ $variation_id ] = $value;
		}
		return $out;
	}

	/**
	 * @param array<int, int> $variation_ids Variation IDs.
	 * @return array<int, string>
	 */
	public function getPostExcerptSnapshot( array $variation_ids ): array {
		return $this->getPostFieldSnapshot( $variation_ids, 'post_excerpt' );
	}

	/**
	 * @param array<int, int> $variation_ids Variation IDs.
	 * @return array<int, string>
	 */
	private function getPostFieldSnapshot( array $variation_ids, string $field ): array {
		if ( empty( $variation_ids ) ) {
			return array();
		}
		global $wpdb;
		$variation_ids = array_values( array_unique( array_filter( array_map( 'absint', $variation_ids ) ) ) );
		$field         = 'post_excerpt' === $field ? 'post_excerpt' : 'post_status';
		$placeholders  = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Value placeholders are generated locally; the allowlisted column uses an identifier placeholder.
		$sql = $wpdb->prepare(
			"SELECT ID, %i FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND ID IN ($placeholders)",
			$field,
			...$variation_ids
		);
		// phpcs:enable
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; post fields are request-scoped.
		$rows    = $wpdb->get_results( $sql, ARRAY_A );
		$out     = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['ID'] ] = (string) $row[ $field ];
		}
		return $out;
	}
}
