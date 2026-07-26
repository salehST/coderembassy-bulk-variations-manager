<?php
/**
 * Plugin runtime bootstrap.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CoderEmbassy\BulkVariationsManager\Admin\AdminPage;
use CoderEmbassy\BulkVariationsManager\CLI\BulkVariationsCLI;
use CoderEmbassy\BulkVariationsManager\Contracts\JobRepositoryInterface;
use CoderEmbassy\BulkVariationsManager\Engine\AttributeMatrix;
use CoderEmbassy\BulkVariationsManager\Engine\BulkEditor;
use CoderEmbassy\BulkVariationsManager\Engine\VariationGenerator;
use CoderEmbassy\BulkVariationsManager\Engine\VariationRepository;
use CoderEmbassy\BulkVariationsManager\Engine\VariationWriter;
use CoderEmbassy\BulkVariationsManager\ImportExport\CsvImporter;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportAttributeReadiness;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportCsvTemplate;
use CoderEmbassy\BulkVariationsManager\ImportExport\ImportValidator;
use CoderEmbassy\BulkVariationsManager\Jobs\BulkUpdateJob;
use CoderEmbassy\BulkVariationsManager\Jobs\GenerateJob;
use CoderEmbassy\BulkVariationsManager\Jobs\ImportJob;
use CoderEmbassy\BulkVariationsManager\Jobs\JobManager;
use CoderEmbassy\BulkVariationsManager\Jobs\RollbackJob;
use CoderEmbassy\BulkVariationsManager\Jobs\StagedExecutionJob;
use CoderEmbassy\BulkVariationsManager\Jobs\StagedRevertJob;
use CoderEmbassy\BulkVariationsManager\Repository\JobRepository;
use CoderEmbassy\BulkVariationsManager\Repository\RollbackRepository;
use CoderEmbassy\BulkVariationsManager\Repository\TemplateRepository;
use CoderEmbassy\BulkVariationsManager\REST\RestController;
use CoderEmbassy\BulkVariationsManager\Rollback\RollbackService;
use CoderEmbassy\BulkVariationsManager\Services\ActivityRecorder;
use CoderEmbassy\BulkVariationsManager\Services\ConcurrencyLock;
use CoderEmbassy\BulkVariationsManager\Services\HistoryLogger;
use CoderEmbassy\BulkVariationsManager\Updater\MigrationRunner;

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
