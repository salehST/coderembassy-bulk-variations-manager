<?php
/**
 * Free-tier feature flags.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Licensing;

class FeatureFlags {
	public function isProEnabled(): bool {
		return false;
	}
}

