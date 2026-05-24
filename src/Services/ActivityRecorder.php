<?php
/**
 * Lightweight activity recorder.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Services;

class ActivityRecorder {
	/**
	 * @param array<string, mixed> $payload Event payload.
	 */
	public function record( string $event, array $payload = array() ): void {
		unset( $event, $payload );
	}
}

