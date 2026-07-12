<?php
/**
 * CsvImporter unit tests.
 *
 * @package BulkVariations\Tests\Unit\ImportExport
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\ImportExport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\ImportExport\CsvImporter;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use BulkVariations\ImportExport\ImportValidator;
use BulkVariations\Jobs\JobManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\ImportExport\CsvImporter
 */

// phpcs:disable WordPress.DB.SlowDBQuery
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

class CsvImporterTest extends TestCase {

	/**
	 * Path to sample fixture.
	 *
	 * @var string
	 */
	private string $fixture;

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$wpdb = new class() {
			public string $postmeta = 'wp_postmeta';

			/**
			 * @param string $sql  SQL.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string|null $sql  SQL.
			 * @param string      $mode Mode.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( ?string $sql, string $mode = ARRAY_A ): array {
				unset( $sql, $mode );
				$rows = array();
				for ( $i = 1; $i <= 11; $i++ ) {
					$rows[] = array(
						'post_id'    => 1000 + $i,
						'meta_value' => sprintf( 'SKU-%03d', $i ),
					);
				}
				return $rows;
			}
		};

		$this->fixture = dirname( __DIR__, 2 ) . '/fixtures/sample-variations.csv';

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $data ) => json_encode( $data ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return ImportAttributeReadiness
	 */
	private function create_attribute_readiness_mock(): ImportAttributeReadiness {
		$readiness = $this->createMock( ImportAttributeReadiness::class );
		$readiness->method( 'getForProduct' )->willReturn(
			array(
				'is_variable' => false,
				'attributes'  => array(),
			)
		);
		$readiness->method( 'getSlugIndex' )->willReturn( array() );

		return $readiness;
	}

	/**
	 * Preview finds 10 valid and 3 invalid rows in fixture.
	 *
	 * @return void
	 */
	public function test_preview_import_counts_valid_and_invalid_rows(): void {
		$validator = new ImportValidator();

		$jobs    = $this->createMock( JobRepositoryInterface::class );
		$manager = $this->createMock( JobManager::class );

		$importer = new CsvImporter( $jobs, $manager, $validator, $this->create_attribute_readiness_mock() );
		$result   = $importer->previewImport( $this->fixture, 100 );

		$this->assertSame( 10, $result['valid_count'] );
		$this->assertSame( 3, $result['invalid_count'] );
		$this->assertCount( 3, $result['errors'] );
	}

	/**
	 * Preview accepts create rows (no variation_id) when product_id and attribute_* are present.
	 *
	 * @return void
	 */
	public function test_preview_import_accepts_create_row_with_attributes(): void {
		$path = sys_get_temp_dir() . '/bv-create-row-' . uniqid( '', true ) . '.csv';
		$csv  = "product_id,sku,attribute_pa_color,attribute_pa_size,regular_price,stock_quantity\n"
			. "18,CSV-CREATE-SMOKE-01,blue,xl,499,7\n";
		file_put_contents( $path, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$importer = new CsvImporter(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			new ImportValidator(),
			$this->create_attribute_readiness_mock()
		);
		$result = $importer->previewImport( $path, 18, array() );

		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		$this->assertSame( 1, $result['valid_count'] );
		$this->assertSame( 0, $result['invalid_count'] );
		$this->assertArrayHasKey( 'attribute_pa_color', $result['valid_rows'][0] );
		$this->assertSame( 'blue', $result['valid_rows'][0]['attribute_pa_color'] );
		$this->assertArrayNotHasKey( 'variation_id', $result['valid_rows'][0] );
	}

	/**
	 * Import dispatches JobManager with chunked valid rows.
	 *
	 * @return void
	 */
	public function test_import_creates_job_and_dispatches_chunks(): void {
		$validator = new ImportValidator();

		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'create' )->willReturn( 77 );
		$jobs->method( 'updateStatus' )->willReturn( true );

		$dispatched = array();
		$manager    = $this->createMock( JobManager::class );
		$manager->method( 'dispatch' )->willReturnCallback(
			static function ( int $job_id, array $chunks ) use ( &$dispatched ): void {
				$dispatched = array( $job_id, $chunks );
			}
		);

		$importer = new CsvImporter( $jobs, $manager, $validator, $this->create_attribute_readiness_mock() );
		$job_id   = $importer->import( $this->fixture, 100, array( 'chunk_size' => 5 ) );

		$this->assertSame( 77, $job_id );
		$this->assertSame( 77, $dispatched[0] );
		$this->assertIsArray( $dispatched[1] );
		$this->assertGreaterThan( 1, count( $dispatched[1] ) );
	}

	/**
	 * Non-CSV extension is rejected.
	 *
	 * @return void
	 */
	public function test_preview_import_rejects_non_csv_extension_without_skip_option(): void {
		$path = sys_get_temp_dir() . '/bv-test-noext-' . uniqid( '', true );
		file_put_contents( $path, "product_id,sku\n1,SKU-A\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$importer = new CsvImporter(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			new ImportValidator(),
			$this->create_attribute_readiness_mock()
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( '.csv extension' );
		$importer->previewImport( $path, 1, array() );

		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Non-CSV extension is rejected.
	 *
	 * @return void
	 */
	public function test_import_rejects_non_csv_extension(): void {
		$path = sys_get_temp_dir() . '/bv-test.txt';
		file_put_contents( $path, "sku\nx\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$importer = new CsvImporter(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			new ImportValidator(),
			$this->create_attribute_readiness_mock()
		);

		$this->expectException( \RuntimeException::class );
		$importer->import( $path, 1, array() );

		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * CsvImporter source uses fopen/fgetcsv streaming.
	 *
	 * @return void
	 */
	public function test_importer_uses_streaming_functions(): void {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/ImportExport/CsvImporter.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'fopen', $source );
		$this->assertStringContainsString( 'fgetcsv', $source );
		$this->assertStringNotContainsString( 'file_get_contents', $source );
	}
}
