<?php
/**
 * JobRepository unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Repository
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Repository;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Repository\JobRepository;
use PHPUnit\Framework\TestCase;

/**
 * JobRepository tests.
 *
 * @covers \CoderEmbassy\BulkVariationsManager\Repository\JobRepository
 */
class JobRepositoryTest extends TestCase {

	/**
	 * WordPress DB stub.
	 *
	 * @var object
	 */
	private object $wpdb;

	/**
	 * Set up Brain Monkey and $wpdb stub.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb            = new \stdClass();
		$this->wpdb->prefix    = 'wp_';
		$this->wpdb->insert_id = 99;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $data ) => json_encode( $data ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);
		Functions\when( 'current_time' )->justReturn( '2026-05-18 12:00:00' );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
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
	 * Create() inserts a job and returns insert ID.
	 *
	 * @return void
	 */
	public function test_create_returns_insert_id(): void {
		$capture    = new \stdClass();
		$capture->t = '';
		$capture->y = '';

		$this->wpdb = new class( $capture ) {
			public string $prefix = 'wp_';
			public int $insert_id = 99;

			/**
			 * @param \stdClass $capture Captured insert payload.
			 */
			public function __construct( private \stdClass $capture ) {
			}

			/**
			 * @param string               $table  Table.
			 * @param array<string, mixed> $data   Row.
			 * @param array<int, string>   $format Formats.
			 * @return int
			 */
			public function insert( string $table, array $data, array $format ): int {
				$this->capture->t = $table;
				$this->capture->y = (string) ( $data['type'] ?? '' );
				unset( $format );
				return 1;
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		$repo = new JobRepository();
		$id   = $repo->create(
			array(
				'type'   => 'bulk_edit',
				'source' => 'manual',
			)
		);

		$this->assertSame( 99, $id );
		$this->assertSame( 'wp_coderembassy_bvm_jobs', $capture->t );
		$this->assertSame( 'bulk_edit', $capture->y );
	}

	/**
	 * Get() hydrates meta JSON.
	 *
	 * @return void
	 */
	public function test_get_hydrates_meta(): void {
		$this->wpdb = new class() {
			public string $prefix = 'wp_';

			/**
			 * @param string               $sql  SQL.
			 * @param mixed                $args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string|null $sql   SQL.
			 * @param string      $output Output type.
			 * @return array<string, mixed>
			 */
			public function get_row( ?string $sql, string $output = ARRAY_A ): array {
				unset( $sql, $output );
				return array(
					'id'           => '5',
					'type'         => 'import',
					'meta'         => wp_json_encode(
						array(
							'source'        => 'rule',
							'review_status' => 'pending_review',
							'control'       => 'queued',
						)
					),
					'status'       => 'queued',
					'progress'     => '0',
					'total_items'  => '10',
					'processed'    => '0',
					'created_by'   => '7',
					'created_at'   => '2026-05-18 12:00:00',
					'started_at'   => null,
					'completed_at' => null,
					'error_log'    => null,
				);
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		$repo = new JobRepository();
		$job  = $repo->get( 5 );

		$this->assertNotNull( $job );
		$this->assertSame( 5, $job['id'] );
		$this->assertSame( 'rule', $job['source'] );
		$this->assertSame( 'pending_review', $job['review_status'] );
	}

	/**
	 * SetControl() rejects invalid control values.
	 *
	 * @return void
	 */
	public function test_set_control_rejects_invalid(): void {
		$repo = new JobRepository();
		$this->assertFalse( $repo->setControl( 1, 'invalid' ) );
	}

	/**
	 * AddChange() requires object_id and field.
	 *
	 * @return void
	 */
	public function test_add_change_validates_required_fields(): void {
		$repo = new JobRepository();
		$this->assertFalse(
			$repo->addChange(
				1,
				array(
					'object_type' => 'variation',
					'object_id'   => 0,
					'field'       => '_price',
				)
			)
		);
	}

	/**
	 * Count() returns integer from get_var.
	 *
	 * @return void
	 */
	public function test_count_returns_integer(): void {
		$this->wpdb = new class() {
			public string $prefix = 'wp_';

			/**
			 * @param string $sql  SQL.
			 * @param mixed  $args Args.
			 * @return string
			 */
			public function prepare( string $sql, mixed ...$args ): string {
				unset( $args );
				return $sql;
			}

			/**
			 * @param string|null $sql SQL.
			 * @return string
			 */
			public function get_var( ?string $sql = null ): string {
				unset( $sql );
				return '12';
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double.
		$GLOBALS['wpdb'] = $this->wpdb;

		$repo = new JobRepository();
		$this->assertSame( 12, $repo->count( array( 'status' => 'complete' ) ) );
	}
}
