<?php
/**
 * Job repository contract.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Contracts;

interface JobRepositoryInterface {
	/**
	 * @param array<string, mixed> $payload Job data.
	 */
	public function create( array $payload ): int;

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $job_id ): ?array;

	/**
	 * @param array<string, mixed> $filters Query filters.
	 */
	public function count( array $filters = array() ): int;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getChanges( int $job_id ): array;

	/**
	 * @param array<string, mixed> $change Change row.
	 */
	public function addChange( int $job_id, array $change ): bool;

	/**
	 * @param array<string, mixed> $extra Extra update fields.
	 */
	public function updateStatus( int $job_id, string $status, array $extra = array() ): bool;

	public function setControl( int $job_id, string $control ): bool;
}

