<?php
/**
 * REST job payload mapping tests.
 *
 * @package BulkVariations\Tests\Unit\REST
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\REST;

use BulkVariations\ImportExport\CsvImporter;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use BulkVariations\ImportExport\ImportCsvTemplate;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Licensing\FeatureFlags;
use BulkVariations\Repository\TemplateRepository;
use BulkVariations\REST\RestController;
use BulkVariations\Rollback\RollbackService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \BulkVariations\REST\RestController
 */
class RestControllerJobPayloadTest extends TestCase {

	/**
	 * @return RestController
	 */
	private function make_controller(): RestController {
		return new RestController(
			$this->createMock( JobRepositoryInterface::class ),
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackService::class ),
			$this->createMock( VariationGenerator::class ),
			$this->createMock( VariationRepository::class ),
			$this->createMock( CsvImporter::class ),
			$this->createMock( ImportAttributeReadiness::class ),
			$this->createMock( ImportCsvTemplate::class ),
			$this->createMock( TemplateRepository::class ),
			$this->createMock( FeatureFlags::class )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function rows_from_changes( array $changes ): array {
		$controller = $this->make_controller();
		$method     = new ReflectionMethod( RestController::class, 'rows_from_change_payload' );
		$method->setAccessible( true );

		/** @var array<int, array<string, mixed>> $rows */
		$rows = $method->invoke( $controller, 'bulk_edit', $changes );
		return $rows;
	}

	/**
	 * @return void
	 */
	public function test_rows_from_change_payload_groups_regular_price_update(): void {
		$rows = $this->rows_from_changes(
			array(
				array(
					'variation_id' => 19,
					'field'        => 'regular_price',
					'old_value'    => '501',
					'new_value'    => '550',
				),
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame(
			array(
				'variation_id'  => 19,
				'regular_price' => '550',
			),
			$rows[0]
		);
	}

	/**
	 * @return void
	 */
	public function test_rows_from_change_payload_strips_currency_formatting(): void {
		$rows = $this->rows_from_changes(
			array(
				array(
					'variation_id' => 19,
					'field'        => 'regular_price',
					'old_value'    => '$501.00',
					'new_value'    => '$550.00',
				),
			)
		);

		$this->assertSame( '550.00', $rows[0]['regular_price'] );
	}
}
