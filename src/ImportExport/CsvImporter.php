<?php
/**
 * CSV import orchestrator.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\ImportExport;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Jobs\JobManager;
use RuntimeException;

class CsvImporter {
	public function __construct(
		private JobRepositoryInterface $jobs,
		private JobManager $manager,
		private ImportValidator $validator,
		private ImportAttributeReadiness $readiness
	) {
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function previewImport( string $path, int $product_id, array $options = array() ): array {
		$this->assertCsvPath( $path, (bool) ( $options['skip_extension_check'] ?? false ) );

		$payload  = $this->readiness->getForProduct( $product_id );
		$slug_map = $this->readiness->getSlugIndex( $payload );
		$rows     = $this->streamRows( $path );
		$skus     = array_values(
			array_filter(
				array_map( static fn( array $row ): string => (string) ( $row['sku'] ?? '' ), $rows )
			)
		);
		$existing_skus = $this->validator->lookupExistingSkus( $skus );

		$valid_rows = array();
		$errors     = array();
		$seen       = array();
		foreach ( $rows as $line => $row ) {
			$result = $this->validator->validateRow( $row, $existing_skus, $seen, $slug_map );
			$sku    = (string) ( $result['fixed_row']['sku'] ?? '' );
			if ( '' !== $sku ) {
				$seen[ $sku ] = true;
			}
			if ( true === ( $result['is_valid'] ?? false ) ) {
				$valid_rows[] = $result['fixed_row'];
				continue;
			}
			$errors[] = array(
				'line'   => $line + 2,
				'issues' => $result['issues'] ?? array(),
			);
		}

		return array(
			'total_rows'   => count( $rows ),
			'valid_count'  => count( $valid_rows ),
			'invalid_count'=> count( $errors ),
			'valid_rows'   => $valid_rows,
			'errors'       => $errors,
		);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public function import( string $path, int $product_id, array $options = array() ): int {
		$this->assertCsvPath( $path, false );

		$preview = $this->previewImport(
			$path,
			$product_id,
			array(
				'skip_extension_check' => true,
			)
		);

		$chunk_size = max( 1, (int) ( $options['chunk_size'] ?? 50 ) );
		$chunks     = array_chunk( $preview['valid_rows'], $chunk_size );

		$job_id = $this->jobs->create(
			array(
				'type'        => 'import',
				'status'      => 'queued',
				'total_items' => count( $preview['valid_rows'] ),
				'meta'        => array(
					'product_id' => $product_id,
				),
			)
		);

		$this->jobs->updateStatus( $job_id, 'queued' );
		$this->manager->dispatch( $job_id, $chunks );

		return $job_id;
	}

	private function assertCsvPath( string $path, bool $skip_extension ): void {
		if ( ! file_exists( $path ) ) {
			throw new RuntimeException( 'CSV file does not exist.' );
		}
		if ( ! $skip_extension && '.csv' !== strtolower( substr( $path, -4 ) ) ) {
			throw new RuntimeException( 'CSV imports require a .csv extension.' );
		}
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function streamRows( string $path ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fp = fopen( $path, 'rb' );
		if ( false === $fp ) {
			throw new RuntimeException( 'Unable to open CSV file.' );
		}

		$headers = fgetcsv( $fp );
		if ( false === $headers || ! is_array( $headers ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $fp );
			return array();
		}
		$headers = array_map( static fn( string $value ): string => trim( $value ), $headers );

		$rows = array();
		while ( false !== ( $line = fgetcsv( $fp ) ) ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$row = array();
			foreach ( $headers as $index => $header ) {
				$value = $line[ $index ] ?? '';
				if ( '' === trim( (string) $value ) ) {
					continue;
				}
				$row[ $header ] = trim( (string) $value );
			}
			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $fp );
		return $rows;
	}
}

