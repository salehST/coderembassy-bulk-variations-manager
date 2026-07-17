<?php
/**
 * resolve_job_changes behavior via RestController.
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
use BulkVariations\Jobs\JobManager;
use BulkVariations\Repository\TemplateRepository;
use BulkVariations\REST\RestController;
use BulkVariations\Rollback\RollbackService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \BulkVariations\REST\RestController
 */
class ResolveJobChangesTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Pending-review jobs fall back to meta.preview_changes.
	 *
	 * @return void
	 */
	public function test_resolve_job_changes_uses_preview_meta_when_applied_empty(): void {
		$preview = array(
			array(
				'variation_id' => 5,
				'object_id'    => 5,
				'field'        => 'regular_price',
				'old_value'    => '10',
				'new_value'    => '12',
			),
		);

		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'getChanges' )->with( 9 )->willReturn( array() );

		$controller = new RestController(
			$jobs,
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackService::class ),
			$this->createMock( VariationGenerator::class ),
			$this->createMock( VariationRepository::class ),
			$this->createMock( CsvImporter::class ),
			$this->createMock( ImportAttributeReadiness::class ),
			$this->createMock( ImportCsvTemplate::class ),
			$this->createMock( TemplateRepository::class )
		);

		$method = new ReflectionMethod( RestController::class, 'resolve_job_changes' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$controller,
			array(
				'id'   => 9,
				'meta' => array( 'preview_changes' => $preview ),
			)
		);

		$this->assertSame( $preview, $result );
	}

	/**
	 * Applied jobs prefer bv_job_changes rows.
	 *
	 * @return void
	 */
	public function test_resolve_job_changes_prefers_applied_rows(): void {
		$applied = array(
			array(
				'object_id'  => 3,
				'field'      => 'sku',
				'old_value'  => 'A',
				'new_value'  => 'B',
				'applied_at' => '2026-05-20 10:00:00',
			),
		);

		$jobs = $this->createMock( JobRepositoryInterface::class );
		$jobs->method( 'getChanges' )->with( 3 )->willReturn( $applied );

		$controller = new RestController(
			$jobs,
			$this->createMock( JobManager::class ),
			$this->createMock( RollbackService::class ),
			$this->createMock( VariationGenerator::class ),
			$this->createMock( VariationRepository::class ),
			$this->createMock( CsvImporter::class ),
			$this->createMock( ImportAttributeReadiness::class ),
			$this->createMock( ImportCsvTemplate::class ),
			$this->createMock( TemplateRepository::class )
		);

		$method = new ReflectionMethod( RestController::class, 'resolve_job_changes' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$controller,
			array(
				'id'   => 3,
				'meta' => array(
					'preview_changes' => array(
						array(
							'variation_id' => 3,
							'field'        => 'sku',
							'old_value'    => 'X',
							'new_value'    => 'Y',
						),
					),
				),
			)
		);

		$this->assertSame( $applied, $result );
	}
}
