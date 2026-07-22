<?php
/**
 * REST job payload mapping tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\REST
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\REST;

use CoderEmbassy\BulkVariationsManager\ImportExport\CsvImporter;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportAttributeReadiness;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportCsvTemplate;
use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Engine\VariationGenerator;
use CoderEmbassy\BulkVariationsManager\Engine\VariationRepository;
use CoderEmbassy\BulkVariationsManager\Jobs\JobManager;
use CoderEmbassy\BulkVariationsManager\Repository\TemplateRepository;
use CoderEmbassy\BulkVariationsManager\REST\RestController;
use CoderEmbassy\BulkVariationsManager\Rollback\RollbackService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\REST\RestController
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
			$this->createMock( TemplateRepository::class )
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
