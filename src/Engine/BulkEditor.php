<?php
/**
 * Bulk variation updater.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Engine;

use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Services\HistoryLogger;

class BulkEditor {
	public function __construct(
		private JobRepositoryInterface $jobs,
		private VariationRepository $repository,
		private HistoryLogger $history
	) {
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Updates.
	 * @return array<string, mixed>
	 */
	public function processChunk( int $job_id, array $rows ): array {
		$processed = 0;
		$errors    = array();
		$ids       = array_values(
			array_filter(
				array_map( static fn( array $row ): int => (int) ( $row['variation_id'] ?? 0 ), $rows )
			)
		);

		$meta_snapshot   = $this->repository->getMetaSnapshot( $ids );
		$status_snapshot = $this->repository->getPostStatusSnapshot( $ids );
		$excerpt_snapshot = $this->repository->getPostExcerptSnapshot( $ids );

		foreach ( $rows as $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? 0 );
			if ( $variation_id <= 0 ) {
				$errors[] = 'Missing variation_id.';
				continue;
			}

			$current_meta   = $meta_snapshot[ $variation_id ] ?? array();
			$current_status = $status_snapshot[ $variation_id ] ?? 'publish';
			$current_excerpt = $excerpt_snapshot[ $variation_id ] ?? '';
			$ops            = $this->buildMetaOperations( $row, $current_meta );
			$applied_ops    = 0;
			$ok             = true;

			foreach ( $ops as $operation ) {
				$changed = $this->applyMetaOperation( $variation_id, $operation );
				if ( ! $changed ) {
					$ok = false;
					break;
				}
				++$applied_ops;
				$this->history->recordChange(
					$job_id,
					'variation',
					$variation_id,
					$operation['key'],
					$operation['old_value'],
					$operation['new_value']
				);
			}

			$status_changed = false;
			if ( $ok && array_key_exists( 'status', $row ) ) {
				$status_changed = $this->updateStatus(
					$job_id,
					$variation_id,
					(string) $row['status'],
					(string) $current_status
				);
				$ok             = $status_changed;
			}

			$description_changed = false;
			if ( $ok && array_key_exists( 'description', $row ) ) {
				$description_changed = $this->updatePostField(
					$job_id,
					$variation_id,
					'post_excerpt',
					(string) $row['description'],
					(string) $current_excerpt
				);
				$ok                  = $description_changed;
			}

			$shipping_class_changed = false;
			if ( $ok && array_key_exists( 'shipping_class_id', $row ) ) {
				$shipping_class_changed = $this->updateShippingClass(
					$job_id,
					$variation_id,
					(string) $row['shipping_class_id']
				);
				$ok                     = $shipping_class_changed;
			}

			if ( ! $ok ) {
				$errors[] = 'Database update failed.';
				continue;
			}

			if ( 0 === $applied_ops && ! $status_changed && ! $description_changed && ! $shipping_class_changed ) {
				continue;
			}

			++$processed;
			$this->clearVariationCaches( $variation_id );
		}

		$this->history->flush();

		return array(
			'processed' => $processed,
			'errors'    => $errors,
		);
	}

	/**
	 * @param array<string, mixed>  $row
	 * @param array<string, string> $existing
	 * @return array<int, array<string, string>>
	 */
	private function buildMetaOperations( array $row, array $existing ): array {
		$ops = array();
		$map = $this->buildFieldMetaMap( $row );

		if ( array_key_exists( 'sale_from', $row ) ) {
			$map['sale_from'] = '_sale_price_dates_from';
		}
		if ( array_key_exists( 'sale_to', $row ) ) {
			$map['sale_to'] = '_sale_price_dates_to';
		}

		$explicit_price = array_key_exists( '_price', $row );

		foreach ( $map as $field => $meta_key ) {
			if ( ! array_key_exists( $field, $row ) ) {
				continue;
			}
			$raw = (string) $row[ $field ];
			if ( in_array( $field, array( 'regular_price', 'sale_price', '_price' ), true ) ) {
				$raw = $this->normalizePriceValue( $raw );
			}
			if ( 'sale_from' === $field ) {
				$raw = $this->normalizeDateValue( $raw, false );
			} elseif ( 'sale_to' === $field ) {
				$raw = $this->normalizeDateValue( $raw, true );
			} elseif ( 'downloadable_files' === $field ) {
				$raw = $this->normalizeDownloadableFiles( $raw );
			} elseif ( in_array( $field, array( 'download_limit', 'download_expiry' ), true ) ) {
				$raw = $this->normalizeWholeNumberValue( $raw );
			}
			$old = (string) ( $existing[ $meta_key ] ?? '' );
			if ( $old === $raw ) {
				continue;
			}
			$ops[] = array(
				'key'       => $meta_key,
				'old_value' => $old,
				'new_value' => $raw,
			);
		}

		if ( ! $explicit_price && $this->touchesPriceFields( $row ) ) {
			$old_price = (string) ( $existing['_price'] ?? '' );
			$new_price = $this->resolveDisplayPrice( $row, $existing );
			if ( $old_price !== $new_price ) {
				$ops[] = array(
					'key'       => '_price',
					'old_value' => $old_price,
					'new_value' => $new_price,
				);
			}
		}

		return $ops;
	}

	/**
	 * @param array<string, mixed>  $row
	 * @param array<string, string> $existing
	 */
	private function resolveDisplayPrice( array $row, array $existing ): string {
		$regular = array_key_exists( 'regular_price', $row ) ? $this->normalizePriceValue( (string) $row['regular_price'] ) : (string) ( $existing['_regular_price'] ?? '' );
		$sale    = array_key_exists( 'sale_price', $row ) ? $this->normalizePriceValue( (string) $row['sale_price'] ) : (string) ( $existing['_sale_price'] ?? '' );
		$from    = array_key_exists( 'sale_from', $row )
			? $this->normalizeDateValue( (string) $row['sale_from'], false )
			: (string) ( $existing['_sale_price_dates_from'] ?? '' );
		$to      = array_key_exists( 'sale_to', $row )
			? $this->normalizeDateValue( (string) $row['sale_to'], true )
			: (string) ( $existing['_sale_price_dates_to'] ?? '' );

		if ( array_key_exists( '_sale_price_dates_from', $row ) ) {
			$from = (string) $row['_sale_price_dates_from'];
		}
		if ( array_key_exists( '_sale_price_dates_to', $row ) ) {
			$to = (string) $row['_sale_price_dates_to'];
		}

		$now       = (int) current_time( 'timestamp' );
		$from_ok   = '' === $from || $now >= (int) $from;
		$to_ok     = '' === $to || $now <= (int) $to;
		$sale_live = '' !== $sale && $from_ok && $to_ok;

		return $sale_live ? $sale : $regular;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function touchesPriceFields( array $row ): bool {
		$keys = array(
			'regular_price',
			'sale_price',
			'sale_from',
			'sale_to',
			'_sale_price_dates_from',
			'_sale_price_dates_to',
		);
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				return true;
			}
		}
		return false;
	}

	private function normalizeDateValue( string $value, bool $end_of_day ): string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}
		if ( ctype_digit( $trimmed ) ) {
			return $trimmed;
		}
		$suffix = $end_of_day ? ' 23:59:59' : ' 00:00:00';
		$time   = strtotime( $trimmed . $suffix );
		if ( false === $time ) {
			return '';
		}
		return (string) $time;
	}

	private function normalizeWholeNumberValue( string $value ): string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}
		if ( ! is_numeric( $trimmed ) ) {
			return '';
		}
		return (string) max( 0, (int) $trimmed );
	}

	private function normalizeDownloadableFiles( string $value ): string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}
		if ( preg_match( '/^a:\d+:\{/', $trimmed ) ) {
			return $trimmed;
		}

		$decoded = json_decode( $trimmed, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$files = array();
		foreach ( $decoded as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$name = trim( (string) ( $file['name'] ?? '' ) );
			$url  = trim( (string) ( $file['file'] ?? $file['url'] ?? '' ) );
			if ( '' === $name && '' === $url ) {
				continue;
			}
			$key           = md5( $name . '|' . $url );
			$files[ $key ] = array(
				'name' => $name,
				'file' => $url,
			);
		}

		if ( empty( $files ) ) {
			return '';
		}

		return function_exists( 'maybe_serialize' ) ? maybe_serialize( $files ) : serialize( $files );
	}

	/**
	 * @param array<string, string> $operation
	 */
	/**
	 * @param array<string, mixed> $row
	 * @return array<string, string>
	 */
	private function buildFieldMetaMap( array $row ): array {
		$map = array(
			'sku'                    => '_sku',
			'regular_price'          => '_regular_price',
			'sale_price'             => '_sale_price',
			'stock_quantity'         => '_stock',
			'stock_status'           => '_stock_status',
			'manage_stock'           => '_manage_stock',
			'virtual'                => '_virtual',
			'downloadable'           => '_downloadable',
			'downloadable_files'      => '_downloadable_files',
			'download_limit'          => '_download_limit',
			'download_expiry'         => '_download_expiry',
			'image_id'               => '_thumbnail_id',
			'weight'                 => '_weight',
			'length'                 => '_length',
			'width'                  => '_width',
			'height'                 => '_height',
			'tax_class'              => '_tax_class',
			'_price'                 => '_price',
			'_regular_price'         => '_regular_price',
			'_sale_price'            => '_sale_price',
			'_stock'                 => '_stock',
			'_stock_status'          => '_stock_status',
			'_manage_stock'          => '_manage_stock',
			'_virtual'               => '_virtual',
			'_downloadable'          => '_downloadable',
			'_downloadable_files'     => '_downloadable_files',
			'_download_limit'         => '_download_limit',
			'_download_expiry'        => '_download_expiry',
			'_thumbnail_id'          => '_thumbnail_id',
			'_weight'                => '_weight',
			'_length'                => '_length',
			'_width'                 => '_width',
			'_height'                => '_height',
			'_tax_class'             => '_tax_class',
			'_sale_price_dates_from' => '_sale_price_dates_from',
			'_sale_price_dates_to'   => '_sale_price_dates_to',
		);

		foreach ( $row as $field => $value ) {
			unset( $value );
			$field = (string) $field;
			if ( str_starts_with( $field, 'attribute_' ) ) {
				$map[ $field ] = $field;
			}
		}

		return $map;
	}

	private function applyMetaOperation( int $variation_id, array $operation ): bool {
		global $wpdb;
		$key = $operation['key'];
		$new = $operation['new_value'];
		$old = $operation['old_value'];

		if ( function_exists( 'update_post_meta' ) && function_exists( 'delete_post_meta' ) ) {
			if ( '' === $new ) {
				if ( '' === $old ) {
					return true;
				}
				return (bool) delete_post_meta( $variation_id, $key );
			}

			if ( '_downloadable_files' === $key ) {
				$new_files = $this->prepareDownloadableFilesForMeta( $new );
				if ( empty( $new_files ) ) {
					return (bool) delete_post_meta( $variation_id, $key );
				}

				if ( function_exists( 'get_post_meta' ) && function_exists( 'maybe_serialize' ) ) {
					$current = get_post_meta( $variation_id, $key, true );
					if ( is_array( $current ) && maybe_serialize( $current ) === $new ) {
						return true;
					}
				}

				$result = update_post_meta( $variation_id, $key, $new_files );
				return false !== $result;
			}

			if ( function_exists( 'get_post_meta' ) ) {
				$current = (string) get_post_meta( $variation_id, $key, true );
				if ( $current === $new ) {
					return true;
				}
			}

			$result = update_post_meta( $variation_id, $key, $new );
			return false !== $result;
		}

		if ( '' === $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback for environments without the WordPress metadata API.
			return false !== $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$variation_id,
					$key
				)
			);
		}

		if ( '' !== $old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback for environments without the WordPress metadata API.
			return false !== $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s",
					$new,
					$variation_id,
					$key
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback for environments without the WordPress metadata API.
		return false !== $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$variation_id,
				$key,
				$new
			)
		);
	}

	/**
	 * @return array<string, array{name:string, file:string}>
	 */
	private function prepareDownloadableFilesForMeta( string $value ): array {
		$files = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $value ) : @unserialize( $value );
		if ( ! is_array( $files ) ) {
			return array();
		}

		$out = array();
		foreach ( $files as $key => $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$name = trim( (string) ( $file['name'] ?? '' ) );
			$url  = trim( (string) ( $file['file'] ?? $file['url'] ?? '' ) );
			if ( '' === $name && '' === $url ) {
				continue;
			}
			$this->approveDownloadableFileDirectory( $url );
			$key         = is_string( $key ) && '' !== $key ? $key : md5( $name . '|' . $url );
			$out[ $key ] = array(
				'name' => $name,
				'file' => $url,
			);
		}

		return $out;
	}

	private function approveDownloadableFileDirectory( string $url ): void {
		if (
			'' === $url
			|| ! function_exists( 'wc_get_container' )
			|| ! class_exists( \Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register::class )
			|| ! class_exists( \Automattic\WooCommerce\Internal\Utilities\URL::class )
		) {
			return;
		}

		try {
			$directories = wc_get_container()->get(
				\Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register::class
			);
			$parent_url  = ( new \Automattic\WooCommerce\Internal\Utilities\URL( $url ) )->get_parent_url();
			$existing    = $directories->get_by_url( $parent_url );

			if ( $existing && ! $existing->is_enabled() ) {
				$directories->update_approved_directory( $existing->get_id(), $parent_url, true );
				return;
			}

			$directories->add_approved_directory( $parent_url, true );
		} catch ( \Throwable ) {
			return;
		}
	}

	private function normalizePriceValue( string $value ): string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}

		$normalized = preg_replace( '/[^0-9.\-]/', '', str_replace( ',', '', $trimmed ) );
		return is_string( $normalized ) && '' !== $normalized ? $normalized : $trimmed;
	}

	private function updateStatus( int $job_id, int $variation_id, string $new_status, string $old_status ): bool {
		if ( '' === $new_status || $new_status === $old_status ) {
			return true;
		}
		if ( function_exists( 'wp_update_post' ) ) {
			$result = wp_update_post(
				array(
					'ID'          => $variation_id,
					'post_status' => $new_status,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return false;
			}
		} else {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback for environments without wp_update_post().
			if ( false === $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %d",
					$new_status,
					$variation_id
				)
			) ) {
				return false;
			}
		}

		$this->history->recordChange(
			$job_id,
			'variation',
			$variation_id,
			'post_status',
			$old_status,
			$new_status
		);

		return true;
	}

	private function updatePostField( int $job_id, int $variation_id, string $field, string $new_value, string $old_value ): bool {
		if ( 'post_excerpt' !== $field ) {
			return false;
		}

		if ( $new_value === $old_value ) {
			return true;
		}

		if ( function_exists( 'wp_update_post' ) ) {
			$result = wp_update_post(
				array(
					'ID'    => $variation_id,
					$field => $new_value,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return false;
			}
		} else {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback for environments without wp_update_post().
			if ( false === $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_excerpt = %s WHERE ID = %d",
					$new_value,
					$variation_id
				)
			) ) {
				return false;
			}
		}

		$this->history->recordChange(
			$job_id,
			'variation',
			$variation_id,
			$field,
			$old_value,
			$new_value
		);

		return true;
	}

	private function updateShippingClass( int $job_id, int $variation_id, string $new_value ): bool {
		if ( ! function_exists( 'wp_get_post_terms' ) || ! function_exists( 'wp_set_object_terms' ) ) {
			return false;
		}

		$old_terms = wp_get_post_terms(
			$variation_id,
			'product_shipping_class',
			array( 'fields' => 'ids' )
		);
		if ( is_wp_error( $old_terms ) ) {
			return false;
		}

		$old_value = empty( $old_terms ) ? '' : (string) absint( $old_terms[0] );
		$new_id    = absint( $new_value );
		$new_clean = $new_id > 0 ? (string) $new_id : '';

		if ( $old_value === $new_clean ) {
			return true;
		}

		$result = wp_set_object_terms(
			$variation_id,
			$new_id > 0 ? array( $new_id ) : array(),
			'product_shipping_class'
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		$this->history->recordChange(
			$job_id,
			'variation',
			$variation_id,
			'product_shipping_class',
			$old_value,
			$new_clean
		);

		return true;
	}

	private function clearVariationCaches( int $variation_id ): void {
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $variation_id );
		}

		$parent_id = function_exists( 'wp_get_post_parent_id' )
			? (int) wp_get_post_parent_id( $variation_id )
			: 0;

		if ( $parent_id > 0 && function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $parent_id );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $variation_id );
			if ( $parent_id > 0 ) {
				wc_delete_product_transients( $parent_id );
			}
		}

		if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
			wc_update_product_lookup_tables( $variation_id );
			if ( $parent_id > 0 ) {
				wc_update_product_lookup_tables( $parent_id );
			}
		}
	}
}
