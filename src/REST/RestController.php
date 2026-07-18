<?php
/**
 * REST controller for the Free admin app.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\REST;

use BulkVariations\Contracts\JobRepositoryInterface;
use BulkVariations\Engine\VariationGenerator;
use BulkVariations\Engine\VariationRepository;
use BulkVariations\ImportExport\CsvImporter;
use BulkVariations\ImportExport\ImportAttributeReadiness;
use BulkVariations\ImportExport\ImportCsvTemplate;
use BulkVariations\Jobs\JobManager;
use BulkVariations\Repository\TemplateRepository;
use BulkVariations\Rollback\RollbackService;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestController {
	private const NAMESPACE = 'bv/v1';

	public function __construct(
		private JobRepositoryInterface $jobs,
		private JobManager $manager,
		private RollbackService $rollback,
		private VariationGenerator $generator,
		private VariationRepository $variations,
		private CsvImporter $csv_importer,
		private ImportAttributeReadiness $readiness,
		private ImportCsvTemplate $templates,
		private TemplateRepository $template_repository
	) {
		unset( $this->template_repository );
	}

	public function register_routes(): void {
		$permission = array( $this, 'permissions_check' );

		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/import-attributes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_import_attributes' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/import-csv-template',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_import_csv_template' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/variations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_variations' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/imports/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview_import' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_jobs' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_job' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => $permission,
			)
		);

		foreach ( array( 'apply', 'cancel', 'discard', 'rollback' ) as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/jobs/(?P<id>\d+)/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'job_' . $action ),
					'permission_callback' => $permission,
				)
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/rollback/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rollback_preview' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permissions_check( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'x_wp_nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'bv_rest_nonce', 'Invalid REST nonce.', array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'bv_forbidden', 'You are not allowed to manage variations.', array( 'status' => 403 ) );
		}

		return true;
	}

	public function search_products( WP_REST_Request $request ): WP_REST_Response {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$query  = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				's'              => $search,
				'posts_per_page' => 20,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type in this taxonomy.
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => array( 'variable' ),
					),
				),
			)
		);

		$items = array_map(
			fn( \WP_Post $post ): array => array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
				'label' => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
				'name'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
				'variation_count' => $this->count_variations_for_product( (int) $post->ID ),
			),
			$query->posts
		);

		return new WP_REST_Response( $items );
	}

	public function get_import_attributes( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->readiness->getForProduct( (int) $request['id'] ) );
	}

	public function get_import_csv_template( WP_REST_Request $request ): WP_REST_Response {
		$readiness = $this->readiness->getForProduct( (int) $request['id'] );
		return new WP_REST_Response(
			array(
				'filename' => $this->templates->getFilename( $readiness ),
				'headers'  => $this->templates->getHeaders( $readiness ),
				'csv'      => $this->templates->build( $readiness ),
			)
		);
	}

	public function list_variations( WP_REST_Request $request ): WP_REST_Response {
		$product_id = (int) $request->get_param( 'product_id' );
		$rows       = $product_id > 0 ? $this->variations->listVariations( $product_id ) : array();
		return new WP_REST_Response(
			array(
				'variations'        => $rows,
				'attribute_columns' => $product_id > 0 ? $this->readiness->getEditorAttributeColumns( $product_id, $rows ) : array(),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_import( WP_REST_Request $request ) {
		$csv_content = (string) $request->get_param( 'csv_content' );
		$product_id  = (int) $request->get_param( 'product_id' );

		$path = $this->create_import_preview_temp_path();
		if ( null === $path ) {
			return ErrorResponse::make( 'bv_import_temp_create', 'Unable to create temporary preview file path.', 500 );
		}
		if ( ! $this->write_import_preview_csv( $path, $csv_content ) ) {
			return ErrorResponse::make( 'bv_import_temp_write', 'Unable to write temporary preview file.', 500 );
		}

		try {
			$result = $this->csv_importer->previewImport(
				$path,
				$product_id,
				array(
					'skip_extension_check' => true,
					'skip_mime_check'      => true,
				)
			);
		} catch ( RuntimeException $error ) {
			$this->delete_temp_file( $path );
			return ErrorResponse::make( 'bv_import_preview_failed', $error->getMessage(), 400 );
		}

		$this->delete_temp_file( $path );
		return new WP_REST_Response( $result );
	}

	public function list_jobs( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'bv_jobs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; live count is required.
		$total    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		$sql      = $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $per_page, $offset );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; job rows must be current.
		$rows     = $wpdb->get_results( $sql, ARRAY_A );
		$items    = array_map( array( $this, 'normalize_job_row' ), is_array( $rows ) ? $rows : array() );
		$response = new WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $response;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_job( WP_REST_Request $request ) {
		$type       = sanitize_key( (string) ( $request->get_param( 'type' ) ?: 'bulk_edit' ) );
		$rows       = $request->get_param( 'rows' );
		$changes    = $request->get_param( 'changes' );
		$product_id = (int) $request->get_param( 'product_id' );
		$source     = sanitize_key( (string) ( $request->get_param( 'source' ) ?: 'admin' ) );
		$dry        = (bool) $request->get_param( 'dry' );
		if ( ! is_array( $rows ) ) {
			$rows = $request->get_param( 'items' );
		}
		if ( ! is_array( $rows ) && is_array( $changes ) ) {
			$rows = $this->rows_from_change_payload( $type, $changes );
		}
		if ( ! is_array( $rows ) ) {
			return ErrorResponse::make( 'bv_invalid_rows', 'No rows were provided for this job.', 400 );
		}

		$rows   = array_values( $rows );
		$chunks = array_chunk( $rows, 25 );
		$preview = 'import' === $type
			? ImportPreviewChanges::build( $rows, $this->variations )
			: EditorPreviewChanges::build( array_values( is_array( $changes ) ? $changes : array() ) );

		if ( empty( $preview ) && 'bulk_edit' === $type ) {
			$preview = $this->build_editor_preview_from_rows( $rows );
		}

		$preview = $this->enrich_preview_old_values( $preview );

		$meta = array(
			'source'          => $source,
			'preview_changes' => $preview,
			'review_status'   => $dry ? 'pending_review' : 'none',
		);
		if ( $product_id > 0 ) {
			$meta['product_id'] = $product_id;
		}

		$job_id = $this->jobs->create(
			array(
				'type'        => $type,
				'status'      => 'queued',
				'total_items' => count( $rows ),
				'meta'        => $meta,
			)
		);
		$this->manager->dispatch( $job_id, $chunks );

		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) ) {
			return ErrorResponse::make( 'bv_job_not_found', 'Job not found.', 404 );
		}

		return new WP_REST_Response( $this->format_job_for_api( $job, true ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_job( WP_REST_Request $request ) {
		$job = $this->jobs->get( (int) $request['id'] );
		if ( ! is_array( $job ) ) {
			return ErrorResponse::make( 'bv_job_not_found', 'Job not found.', 404 );
		}
		return new WP_REST_Response( $this->format_job_for_api( $job, true ) );
	}

	public function job_apply( WP_REST_Request $request ): WP_REST_Response {
		$job_id = (int) $request['id'];
		$ok     = $this->manager->resumeAfterApproval( $job_id );
		$job    = $this->jobs->get( $job_id );
		return new WP_REST_Response(
			array(
				'success' => $ok,
				'job'     => is_array( $job ) ? $this->format_job_for_api( $job, true ) : null,
			)
		);
	}

	public function job_cancel( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => $this->manager->cancel( (int) $request['id'] ) ) );
	}

	public function job_discard( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => $this->manager->discard( (int) $request['id'] ) ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function job_rollback( WP_REST_Request $request ) {
		$result = $this->rollback->rollback( (int) $request['id'] );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		$rollback_job = $this->jobs->get( (int) $result );
		$source_job   = $this->jobs->get( (int) $request['id'] );

		return new WP_REST_Response(
			array(
				'rollback_job_id' => (int) $result,
				'rollback_status' => is_array( $rollback_job ) ? (string) ( $rollback_job['status'] ?? '' ) : '',
				'rollback_job'    => is_array( $rollback_job ) ? $this->format_job_for_api( $rollback_job, true ) : null,
				'source_job'      => is_array( $source_job ) ? $this->format_job_for_api( $source_job, true ) : null,
			)
		);
	}

	public function rollback_preview( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->rollback->preview( (int) $request['id'] ) );
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->read_settings() );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		$theme = $this->sanitize_theme_setting( $request->get_param( 'theme' ) );
		update_user_meta( $user_id, 'bv_admin_theme', $theme );

		$default_product_id = $this->absint_setting( $request->get_param( 'default_product_id' ) );
		update_user_meta( $user_id, 'bv_default_product_id', $default_product_id );

		$jobs_per_page = $this->sanitize_jobs_per_page( $request->get_param( 'jobs_per_page' ) );
		update_user_meta( $user_id, 'bv_jobs_per_page', $jobs_per_page );

		$remove_data = (bool) $request->get_param( 'remove_data_on_uninstall' );
		update_option( 'bv_uninstall_remove_data', $remove_data, false );

		return new WP_REST_Response( $this->read_settings() );
	}

	protected function create_import_preview_temp_path(): ?string {
		$dir = trailingslashit( get_temp_dir() );
		if ( '' === $dir ) {
			return null;
		}
		return $dir . 'bv-import-preview-' . wp_generate_password( 12, false, false ) . '.csv';
	}

	protected function write_import_preview_csv( string $path, string $csv_content ): bool {
		return false !== file_put_contents( $path, $csv_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * @param array<string, mixed> $job
	 * @return array<int, array<string, mixed>>
	 */
	protected function resolve_job_changes( array $job ): array {
		$job_id  = (int) ( $job['id'] ?? 0 );
		$applied = $this->jobs->getChanges( $job_id );
		if ( ! empty( $applied ) ) {
			return $applied;
		}
		$meta    = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
		$preview = $meta['preview_changes'] ?? array();
		return is_array( $preview ) ? $preview : array();
	}

	private function delete_temp_file( string $path ): void {
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalize_job_row( array $row ): array {
		$meta = json_decode( (string) ( $row['meta'] ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$job = array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'type'          => (string) ( $row['type'] ?? '' ),
			'status'        => (string) ( $row['status'] ?? '' ),
			'progress'      => (int) ( $row['progress'] ?? 0 ),
			'total_items'   => (int) ( $row['total_items'] ?? 0 ),
			'processed'     => (int) ( $row['processed'] ?? 0 ),
			'created_by'    => (int) ( $row['created_by'] ?? 0 ),
			'created_at'    => (string) ( $row['created_at'] ?? '' ),
			'started_at'    => $row['started_at'] ?? null,
			'completed_at'  => $row['completed_at'] ?? null,
			'error_log'     => $row['error_log'] ?? null,
			'meta'          => $meta,
			'source'        => (string) ( $meta['source'] ?? '' ),
			'review_status' => (string) ( $meta['review_status'] ?? 'none' ),
		);

		return $this->format_job_for_api( $job, false );
	}

	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>
	 */
	private function format_job_for_api( array $job, bool $include_changes ): array {
		$meta   = is_array( $job['meta'] ?? null ) ? $job['meta'] : array();
		$status = (string) ( $job['status'] ?? '' );
		$review = (string) ( $job['review_status'] ?? ( $meta['review_status'] ?? 'none' ) );

		if ( in_array( $status, array( 'complete', 'completed' ), true ) ) {
			$status = 'completed';
			$review = 'applied';
		} elseif ( 'rolled_back' === $status ) {
			$review = 'none';
		} elseif ( 'cancelled' === $status && 'discarded' === $review ) {
			$review = 'discarded';
		}

		$is_pending_review = 'preview' === $status || ( 'pending_review' === $review && 'cancelled' !== $status );

		$formatted = array_merge(
			$job,
			array(
				'status'        => $status,
				'review_status' => $review,
				'source'        => (string) ( $job['source'] ?? ( $meta['source'] ?? '' ) ),
				'meta'          => $meta,
			)
		);

		if ( $is_pending_review ) {
			$formatted['completed_at'] = null;
			$formatted['started_at']   = null;
			$formatted['progress']     = 0;
			$formatted['processed']      = 0;
		}

		if ( $include_changes ) {
			$formatted['changes'] = $this->resolve_job_changes( $formatted );
		}

		return $formatted;
	}

	/**
	 * @param array<int, array<string, mixed>> $preview
	 * @return array<int, array<string, mixed>>
	 */
	private function enrich_preview_old_values( array $preview ): array {
		if ( empty( $preview ) ) {
			return $preview;
		}

		$ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( array $row ): int => (int) ( $row['variation_id'] ?? $row['object_id'] ?? 0 ),
						$preview
					)
				)
			)
		);
		if ( empty( $ids ) ) {
			return $preview;
		}

		$meta_snapshot   = $this->variations->getMetaSnapshot( $ids );
		$status_snapshot = $this->variations->getPostStatusSnapshot( $ids );
		$excerpt_snapshot = $this->variations->getPostExcerptSnapshot( $ids );
		$field_map       = array(
			'sku'           => '_sku',
			'regular_price' => '_regular_price',
			'sale_price'    => '_sale_price',
			'stock_quantity'=> '_stock',
			'stock_status'  => '_stock_status',
			'manage_stock'  => '_manage_stock',
			'virtual'       => '_virtual',
			'downloadable'  => '_downloadable',
			'downloadable_files' => '_downloadable_files',
			'download_limit' => '_download_limit',
			'download_expiry'=> '_download_expiry',
			'sale_from'     => '_sale_price_dates_from',
			'sale_to'       => '_sale_price_dates_to',
		);

		foreach ( $preview as $index => $row ) {
			$variation_id = (int) ( $row['variation_id'] ?? $row['object_id'] ?? 0 );
			$field        = (string) ( $row['field'] ?? '' );
			$old_value    = (string) ( $row['old_value'] ?? '' );
			if ( '' !== $old_value || $variation_id <= 0 || '' === $field ) {
				continue;
			}

			if ( 'status' === $field ) {
				$preview[ $index ]['old_value'] = (string) ( $status_snapshot[ $variation_id ] ?? '' );
				continue;
			}
			if ( 'description' === $field ) {
				$preview[ $index ]['old_value'] = (string) ( $excerpt_snapshot[ $variation_id ] ?? '' );
				continue;
			}

			$meta_key = $field_map[ $field ] ?? $field;
			$current  = (string) ( $meta_snapshot[ $variation_id ][ $meta_key ] ?? '' );
			if ( in_array( $field, array( 'sale_from', 'sale_to' ), true ) && '' !== $current && ctype_digit( $current ) ) {
				$current = gmdate( 'Y-m-d', (int) $current );
			}
			$preview[ $index ]['old_value'] = $current;
		}

		return $preview;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	private function build_editor_preview_from_rows( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$id = (int) ( $row['variation_id'] ?? 0 );
			foreach ( $row as $field => $value ) {
				if ( 'variation_id' === $field ) {
					continue;
				}
				$out[] = array(
					'variation_id' => $id,
					'object_id'    => $id,
					'field'        => (string) $field,
					'old_value'    => '',
					'new_value'    => (string) $value,
				);
			}
		}
		return $out;
	}

	/**
	 * @param string $type Job type.
	 * @param array<int, array<string, mixed>> $changes Raw change payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows_from_change_payload( string $type, array $changes ): array {
		if ( 'import' === $type ) {
			return array_values( $changes );
		}

		$grouped = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}

			$variation_id = (int) ( $change['variation_id'] ?? $change['object_id'] ?? 0 );
			$field        = isset( $change['field'] ) ? (string) $change['field'] : '';
			if ( $variation_id <= 0 || '' === $field ) {
				continue;
			}

			if ( ! isset( $grouped[ $variation_id ] ) ) {
				$grouped[ $variation_id ] = array(
					'variation_id' => $variation_id,
				);
			}

			$value = $change['new_value'] ?? '';
			if ( in_array( $field, array( 'regular_price', 'sale_price', '_price' ), true ) ) {
				$value = $this->normalize_price_payload_value( $value );
			}

			$grouped[ $variation_id ][ $field ] = $value;
		}

		return array_values( $grouped );
	}

	/**
	 * @param mixed $value Raw REST value.
	 */
	private function normalize_price_payload_value( mixed $value ): string {
		$raw = trim( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		$normalized = preg_replace( '/[^0-9.\-]/', '', str_replace( ',', '', $raw ) );
		return is_string( $normalized ) && '' !== $normalized ? $normalized : $raw;
	}

	private function count_variations_for_product( int $product_id ): int {
		if ( $product_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation' AND post_status NOT IN ('auto-draft')",
			$product_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; count must reflect current variations.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_settings(): array {
		$user_id = get_current_user_id();

		return array(
			'theme'                    => $this->sanitize_theme_setting( get_user_meta( $user_id, 'bv_admin_theme', true ) ),
			'default_product_id'       => $this->absint_setting( get_user_meta( $user_id, 'bv_default_product_id', true ) ),
			'jobs_per_page'            => $this->sanitize_jobs_per_page( get_user_meta( $user_id, 'bv_jobs_per_page', true ) ),
			'remove_data_on_uninstall' => (bool) get_option( 'bv_uninstall_remove_data', false ),
		);
	}

	/**
	 * @param mixed $value Raw setting.
	 */
	private function sanitize_theme_setting( mixed $value ): string {
		$theme = sanitize_key( (string) $value );
		return in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
	}

	/**
	 * @param mixed $value Raw setting.
	 */
	private function sanitize_jobs_per_page( mixed $value ): int {
		$count = $this->absint_setting( $value );
		if ( ! in_array( $count, array( 20, 50, 100 ), true ) ) {
			return 50;
		}
		return $count;
	}

	/**
	 * @param mixed $value Raw integer-ish setting.
	 */
	private function absint_setting( mixed $value ): int {
		return max( 0, (int) $value );
	}
}
