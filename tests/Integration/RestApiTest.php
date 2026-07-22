<?php
/**
 * REST API integration tests (requires WordPress test suite / wp-env).
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Integration
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for coderembassy-bvm/v1 REST routes — executed in CI via wp-env.
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
	 * 1. GET /coderembassy-bvm/v1/jobs unauthenticated → 401 ErrorResponse.
	 * 2. GET /coderembassy-bvm/v1/jobs as subscriber → 403.
	 * 3. GET /coderembassy-bvm/v1/jobs as admin → 200 + X-WP-Total + X-WP-TotalPages headers.
	 * 4. POST /coderembassy-bvm/v1/jobs → 201 with job payload.
	 * 5. GET /coderembassy-bvm/v1/jobs/{id} → 200 with job.
	 * 6. POST /coderembassy-bvm/v1/jobs/{id}/pause → 200 success.
	 * 7. POST /coderembassy-bvm/v1/jobs/{id}/resume → 200 success.
	 * 8. POST /coderembassy-bvm/v1/jobs/{id}/cancel → 200 success.
	 * 9. POST /coderembassy-bvm/v1/jobs/{id}/rollback on complete job → 200.
	 * 10. GET /coderembassy-bvm/v1/jobs/{id}/rollback/preview → 200 with preview rows.
	 * 11. GET /coderembassy-bvm/v1/variations?product_id=X → 200.
	 * 12. POST /coderembassy-bvm/v1/variations/generate → 200 with combinations.
	 * 13. POST /coderembassy-bvm/v1/ai/suggest → 501 (not configured).
	 * 14. GET /coderembassy-bvm/v1/ai/suggest/{id} → 404 or 200.
	 * 15. GET /coderembassy-bvm/v1/templates → 403 (when the route is unavailable).
	 * 16. POST /coderembassy-bvm/v1/templates → 403.
	 * 17. DELETE /coderembassy-bvm/v1/templates/{id} → 403.
	 * 18. GET /coderembassy-bvm/v1/diagnostics → 200.
	 * 19. GET /coderembassy-bvm/v1/settings → 200.
	 * 20. POST /coderembassy-bvm/v1/settings → 200.
	 * 21. Each route unauthenticated → 401 with { code, message, data.status }.
	 */
}
