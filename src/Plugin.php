<?php
/**
 * Plugin runtime bootstrap.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BulkVariations\Admin\AdminPage;
use BulkVariations\CLI\BulkVariationsCLI;
use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\AttributeMatrix;
use BulkVariations\Engine\BulkEditor;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\Engine\VariationWriter;
use BulkVariations\ImportExport\CsvImporter;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use BulkVariations\ImportExport\ImportCsvTemplate;
use BulkVariations\ImportExport\ImportValidator;
use BulkVariations\Jobs\BulkUpdateJob;
use BulkVariations\Jobs\GenerateJob;
use BulkVariations\Jobs\ImportJob;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Jobs\RollbackJob;
use BulkVariations\Jobs\StagedExecutionJob;
use BulkVariations\Jobs\StagedRevertJob;
use BulkVariations\Repository\JobRepository;
use BulkVariations\Repository\RollbackRepository;
use BulkVariations\Repository\TemplateRepository;
use BulkVariations\REST\RestController;
use BulkVariations\Rollback\RollbackService;
use BulkVariations\Services\ActivityRecorder;
use BulkVariations\Services\ConcurrencyLock;
use BulkVariations\Services\HistoryLogger;
use BulkVariations\Updater\MigrationRunner;

class Plugin {
	private static ?self $instance = null;

	/**
	 * @var array<class-string, object>
	 */
	private array $services = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function service( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}

	/**
	 * @return array<class-string, object>
	 */
	public function services(): array {
		return $this->services;
	}

	public function boot(): void {
		MigrationRunner::run();

		$jobs      = new JobRepository();
		$history   = new HistoryLogger();
		$repo      = new VariationRepository();
		$editor    = new BulkEditor( $jobs, $repo, $history );
		$writer    = new VariationWriter( $history );
		$generator = new VariationGenerator( $repo, new AttributeMatrix(), $writer );
		$readiness = new ImportAttributeReadiness( new AttributeMatrix(), $repo, $generator );
		$rollback_repository = new RollbackRepository( $jobs );

		$manager = new JobManager(
			$jobs,
			new ConcurrencyLock(),
			new BulkUpdateJob( $editor ),
			new GenerateJob( $jobs, $generator ),
			new ImportJob( $jobs, new ImportValidator(), $writer, $editor ),
			new RollbackJob( $editor ),
			new StagedExecutionJob(),
			new StagedRevertJob(),
			new ActivityRecorder(),
			$editor,
			$rollback_repository
		);
		$rollback = new RollbackService( $jobs, $rollback_repository, $manager, new RollbackJob( $editor ) );
		$csv      = new CsvImporter( $jobs, $manager, new ImportValidator(), $readiness );

		$rest = new RestController(
			$jobs,
			$manager,
			$rollback,
			$generator,
			$repo,
			$csv,
			$readiness,
			new ImportCsvTemplate(),
			new TemplateRepository()
		);

		$admin = new AdminPage();
		$this->services = array(
			JobRepositoryInterface::class => $jobs,
			JobRepository::class => $jobs,
			HistoryLogger::class => $history,
			VariationRepository::class => $repo,
			BulkEditor::class => $editor,
			VariationWriter::class => $writer,
			VariationGenerator::class => $generator,
			ImportAttributeReadiness::class => $readiness,
			JobManager::class => $manager,
			RollbackRepository::class => $rollback_repository,
			RollbackService::class => $rollback,
			CsvImporter::class => $csv,
			RestController::class => $rest,
			AdminPage::class => $admin,
		);

		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
		add_action( JobManager::ACTION_HOOK, array( $manager, 'processChunk' ), 10, 2 );
		add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_action( 'admin_head', array( $admin, 'suppress_third_party_notices' ), 0 );
		add_filter( 'admin_body_class', array( $admin, 'append_admin_body_class' ) );

		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'bulk-variations', BulkVariationsCLI::class );
		}

		do_action( 'coderembassy_bvm_booted', $this );
	}
}
