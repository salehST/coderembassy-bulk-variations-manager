<?php
/**
 * Option-based lock helper.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Services;

class ConcurrencyLock {
	private const PREFIX = 'bv_lock_';

	public function acquire( string $key ): bool {
		$option = self::PREFIX . sanitize_key( $key );
		return (bool) add_option( $option, 1 );
	}

	public function release( string $key ): bool {
		$option = self::PREFIX . sanitize_key( $key );
		return (bool) delete_option( $option );
	}

	public function isHeldBy( string $key ): bool {
		$option = self::PREFIX . sanitize_key( $key );
		return false !== get_option( $option, false );
	}
}

