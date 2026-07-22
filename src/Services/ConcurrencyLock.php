<?php
/**
 * Option-based lock helper.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Services;

class ConcurrencyLock {
	private const PREFIX = 'coderembassy_bvm_lock_';

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

