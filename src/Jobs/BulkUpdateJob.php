<?php
/**
 * Bulk update worker.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Jobs;

use BulkVariations\Engine\BulkEditor;

class BulkUpdateJob {
	public function __construct( private BulkEditor $editor ) {
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	public function run( int $job_id, array $rows ): array {
		return $this->editor->processChunk( $job_id, $rows );
	}
}

