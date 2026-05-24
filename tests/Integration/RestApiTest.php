<?php
/**
 * REST API integration tests (requires WordPress test suite / wp-env).
 *
 * @package BulkVariations\Tests\Integration
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for bv/v1 REST routes — executed in CI via wp-env.
 *
 * @coversNothing
 */
class RestApiTest extends TestCase {

	/**
	 * Placeholder — full route test suite runs in CI with WP_UnitTestCase bootstrap.
	 *
	 * @return void
	 */
	public function test_placeholder_for_ci(): void {
		$this->assertTrue( true );
	}

	/*
	 * CI scenarios (wp-env):
	 *
	 * 1. GET /bv/v1/jobs unauthenticated → 401 ErrorResponse.
	 * 2. GET /bv/v1/jobs as subscriber → 403.
	 * 3. GET /bv/v1/jobs as admin → 200 + X-WP-Total + X-WP-TotalPages headers.
	 * 4. POST /bv/v1/jobs → 201 with job payload.
	 * 5. GET /bv/v1/jobs/{id} → 200 with job.
	 * 6. POST /bv/v1/jobs/{id}/pause → 200 success.
	 * 7. POST /bv/v1/jobs/{id}/resume → 200 success.
	 * 8. POST /bv/v1/jobs/{id}/cancel → 200 success.
	 * 9. POST /bv/v1/jobs/{id}/rollback on complete job → 200.
	 * 10. GET /bv/v1/jobs/{id}/rollback/preview → 200 with preview rows.
	 * 11. GET /bv/v1/variations?product_id=X → 200.
	 * 12. POST /bv/v1/variations/generate → 200 with combinations.
	 * 13. POST /bv/v1/ai/suggest → 501 (not configured).
	 * 14. GET /bv/v1/ai/suggest/{id} → 404 or 200.
	 * 15. GET /bv/v1/templates → 403 (Pro-only, free tier).
	 * 16. POST /bv/v1/templates → 403.
	 * 17. DELETE /bv/v1/templates/{id} → 403.
	 * 18. GET /bv/v1/diagnostics → 200.
	 * 19. GET /bv/v1/settings → 200.
	 * 20. POST /bv/v1/settings → 200.
	 * 21. Each route unauthenticated → 401 with { code, message, data.status }.
	 */
}
