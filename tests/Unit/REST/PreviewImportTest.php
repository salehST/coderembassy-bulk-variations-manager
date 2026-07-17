<?php
/**
 * RestController::preview_import unit tests.
 *
 * @package BulkVariations\Tests\Unit\REST
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\ImportExport\CsvImporter;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use BulkVariations\ImportExport\ImportCsvTemplate;
use BulkVariations\ImportExport\ImportValidator;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Repository\TemplateRepository;
use BulkVariations\REST\RestController;
use BulkVariations\Rollback\RollbackService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * RestController used to override preview temp path in tests.
 */
final class PreviewImportRestController extends RestController {

	/**
	 * When set, create_import_preview_temp_path returns this value.
	 *
	 * @var string|null
	 */
	public ?string $forced_temp_path = null;

	/**
	 * When false, write_import_preview_csv always fails.
	 *
	 * @var bool|null
	 */
	public ?bool $force_write_failure = null;

	/**
	 * {@inheritDoc}
	 */
	protected function create_import_preview_temp_path(): ?string {
		if ( null !== $this->forced_temp_path ) {
			return $this->forced_temp_path;
		}

		return parent::create_import_preview_temp_path();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function write_import_preview_csv( string $path, string $csv_content ): bool {
		if ( false === $this->force_write_failure ) {
			return false;
		}

		return parent::write_import_preview_csv( $path, $csv_content );
	}
}

/**
 * @covers \BulkVariations\REST\RestController::preview_import
 */
class PreviewImportTest extends TestCase {

	private const DEMO_CSV = <<<'CSV'
product_id,variation_id,sku,regular_price,sale_price,sale_from,sale_to,stock_quantity,stock_status,status
18,19,CSV-TEST-19,599,499,2026-06-01,2026-06-30,10,instock,publish
CSV;

	/**
	 * Set up Brain Monkey and WordPress REST stubs.
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
				return array();
			}
		};

		Functions\when( 'get_temp_dir' )->alias(
			static fn(): string => trailingslashit( sys_get_temp_dir() )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ): string => rtrim( $path, "/\\" ) . '/'
		);
		Functions\when( 'wp_generate_password' )->justReturn( 'unittest' );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->returnArg( 1 );
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
	 * @return PreviewImportRestController
	 */
	private function make_controller( ?CsvImporter $csv_importer = null ): PreviewImportRestController {
		return new PreviewImportRestController(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackService::class ),
			$this->createMock( VariationGenerator::class ),
			$this->createMock( VariationRepository::class ),
			$csv_importer ?? $this->createMock( CsvImporter::class ),
			$this->createMock( ImportAttributeReadiness::class ),
			$this->createMock( ImportCsvTemplate::class ),
			$this->createMock( TemplateRepository::class )
		);
	}

	/**
	 * @param array<string, mixed> $params Request params.
	 * @return WP_REST_Request
	 */
	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/bv/v1/imports/preview' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * Temp write failure returns JSON error envelope.
	 *
	 * @return void
	 */
	public function test_preview_import_returns_error_when_temp_file_write_fails(): void {
		$controller                    = $this->make_controller();
		$controller->forced_temp_path    = trailingslashit( sys_get_temp_dir() ) . 'bv-preview-write-fail.csv';
		$controller->force_write_failure = false;

		$result = $controller->preview_import(
			$this->make_request(
				array(
					'csv_content' => self::DEMO_CSV,
					'product_id'  => 18,
				)
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bv_import_temp_write', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	/**
	 * Importer exceptions become JSON errors.
	 *
	 * @return void
	 */
	public function test_preview_import_returns_error_when_csv_importer_throws(): void {
		$csv = $this->createMock( CsvImporter::class );
		$csv->method( 'previewImport' )->willThrowException( new RuntimeException( 'Parse failed.' ) );

		$result = $this->make_controller( $csv )->preview_import(
			$this->make_request(
				array(
					'csv_content' => self::DEMO_CSV,
					'product_id'  => 18,
				)
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bv_import_preview_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'Parse failed', $result->get_error_message() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Demo CSV content returns one valid preview row.
	 *
	 * @return void
	 */
	public function test_preview_import_accepts_demo_csv_content(): void {
		$readiness = $this->createMock( ImportAttributeReadiness::class );
		$readiness->method( 'getForProduct' )->willReturn(
			array(
				'is_variable' => true,
				'attributes'  => array(),
			)
		);
		$readiness->method( 'getSlugIndex' )->willReturn( array() );

		$importer = new CsvImporter(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			new ImportValidator(),
			$readiness
		);

		$result = $this->make_controller( $importer )->preview_import(
			$this->make_request(
				array(
					'csv_content' => self::DEMO_CSV,
					'product_id'  => 18,
				)
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 1, $data['total_rows'] );
		$this->assertSame( 1, $data['valid_count'] );
		$this->assertSame( 0, $data['invalid_count'] );
	}

	/**
	 * Trusted preview skips extension check for paths without a .csv suffix.
	 *
	 * @return void
	 */
	public function test_preview_import_skip_extension_allows_non_csv_suffix(): void {
		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bv-preview-noext-' . uniqid( '', true );
		file_put_contents( $path, self::DEMO_CSV ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$readiness = $this->createMock( ImportAttributeReadiness::class );
		$readiness->method( 'getForProduct' )->willReturn(
			array(
				'is_variable' => true,
				'attributes'  => array(),
			)
		);
		$readiness->method( 'getSlugIndex' )->willReturn( array() );

		$importer = new CsvImporter(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			new ImportValidator(),
			$readiness
		);

		$result = $importer->previewImport(
			$path,
			18,
			array(
				'skip_extension_check' => true,
				'skip_mime_check'      => true,
			)
		);

		$this->assertSame( 1, $result['valid_count'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
	}

	/**
	 * Temp preview path ends with .csv.
	 *
	 * @return void
	 */
	public function test_create_import_preview_temp_path_ends_with_csv(): void {
		$controller = $this->make_controller();
		$method     = new \ReflectionMethod( PreviewImportRestController::class, 'create_import_preview_temp_path' );
		$method->setAccessible( true );
		$path       = $method->invoke( $controller );

		$this->assertIsString( $path );
		$this->assertStringEndsWith( '.csv', $path );

		if ( is_string( $path ) && file_exists( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		}
	}
}
