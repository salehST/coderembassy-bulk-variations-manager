<?php
/**
 * REST error envelope.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\REST;

use WP_Error;

class ErrorResponse {
	/**
	 * @param array<string, mixed> $extra
	 */
	public static function make( string $code, string $message, int $status = 400, array $extra = array() ): WP_Error {
		$data = array_merge(
			array(
				'status' => $status,
				'detail' => $message,
			),
			$extra
		);
		return new WP_Error( $code, $message, $data );
	}
}

