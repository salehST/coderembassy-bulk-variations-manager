<?php
/**
 * Bulk variation updater.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Engine;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Services\HistoryLogger;

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

		foreach ( $rows as $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? 0 );
			if ( $variation_id <= 0 ) {
				$errors[] = 'Missing variation_id.';
				continue;
			}

			$current_meta   = $meta_snapshot[ $variation_id ] ?? array();
			$current_status = $status_snapshot[ $variation_id ] ?? 'publish';
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
					$variation_id,
					(string) $row['status'],
					(string) $current_status
				);
				$ok             = $status_changed;
			}

			if ( ! $ok ) {
				$errors[] = 'Database update failed.';
				continue;
			}

			if ( 0 === $applied_ops && ! $status_changed ) {
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
			'_price'                 => '_price',
			'_regular_price'         => '_regular_price',
			'_sale_price'            => '_sale_price',
			'_stock'                 => '_stock',
			'_stock_status'          => '_stock_status',
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$variation_id,
					$key
				)
			);
		}

		if ( '' !== $old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s",
					$new,
					$variation_id,
					$key
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$variation_id,
				$key,
				$new
			)
		);
	}

	private function normalizePriceValue( string $value ): string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}

		$normalized = preg_replace( '/[^0-9.\-]/', '', str_replace( ',', '', $trimmed ) );
		return is_string( $normalized ) && '' !== $normalized ? $normalized : $trimmed;
	}

	private function updateStatus( int $variation_id, string $new_status, string $old_status ): bool {
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
			return ! is_wp_error( $result );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %d",
				$new_status,
				$variation_id
			)
		);
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
