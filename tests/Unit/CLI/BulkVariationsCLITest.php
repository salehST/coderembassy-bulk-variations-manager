<?php
/**
 * BulkVariationsCLI unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\CLI
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\CLI;

use CoderEmbassy\BulkVariationsManager\CLI\BulkVariationsCLI;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\CLI\BulkVariationsCLI
 */
class BulkVariationsCLITest extends TestCase {

	/**
	 * is_dry_run() detects --dry-run flag.
	 *
	 * @return void
	 */
	public function test_is_dry_run_detects_flag(): void {
		$this->assertTrue( BulkVariationsCLI::is_dry_run( array( 'dry-run' => true ) ) );
		$this->assertFalse( BulkVariationsCLI::is_dry_run( array() ) );
	}

	/**
	 * format_job_row() normalises job array for CLI tables.
	 *
	 * @return void
	 */
	public function test_format_job_row_normalises_types(): void {
		$row = BulkVariationsCLI::format_job_row(
			array(
				'id'          => '7',
				'type'        => 'bulk_edit',
				'status'      => 'running',
				'progress'    => '50',
				'processed'   => '10',
				'total_items' => '20',
				'source'      => 'manual',
				'created_at'  => '2026-05-19 00:00:00',
			)
		);

		$this->assertSame( 7, $row['id'] );
		$this->assertSame( 'bulk_edit', $row['type'] );
		$this->assertSame( 50, $row['progress'] );
		$this->assertSame( 20, $row['total'] );
	}
}
