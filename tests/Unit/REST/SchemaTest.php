<?php
/**
 * Schema unit tests.
 *
 * @package BulkVariations\Tests\Unit\REST
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\REST;

use BulkVariations\REST\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\REST\Schema
 */
class SchemaTest extends TestCase {

	/**
	 * Each resource schema is a valid object type with properties.
	 *
	 * @dataProvider resource_schema_provider
	 *
	 * @param string $method Schema method name.
	 * @return void
	 */
	public function test_resource_schema_has_object_type_and_properties( string $method ): void {
		$schema = Schema::$method();

		$this->assertIsArray( $schema );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertIsArray( $schema['properties'] );
		$this->assertNotEmpty( $schema['properties'] );
	}

	/**
	 * Provides resource schema method names.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function resource_schema_provider(): array {
		return array(
			'job'             => array( 'job' ),
			'job_change'      => array( 'job_change' ),
			'variation'       => array( 'variation' ),
			'template'        => array( 'template' ),
			'ai_suggestion'   => array( 'ai_suggestion' ),
			'rule'            => array( 'rule' ),
			'schedule'        => array( 'schedule' ),
			'heatmap_cell'    => array( 'heatmap_cell' ),
			'activity_event'  => array( 'activity_event' ),
		);
	}

	/**
	 * Job schema includes key properties.
	 *
	 * @return void
	 */
	public function test_job_schema_has_expected_properties(): void {
		$props = Schema::job()['properties'];

		$this->assertArrayHasKey( 'id', $props );
		$this->assertArrayHasKey( 'type', $props );
		$this->assertArrayHasKey( 'status', $props );
		$this->assertArrayHasKey( 'progress', $props );
		$this->assertArrayHasKey( 'total_items', $props );
		$this->assertArrayHasKey( 'source', $props );
		$this->assertArrayHasKey( 'created_at', $props );
	}

	/**
	 * Variation schema includes key properties.
	 *
	 * @return void
	 */
	public function test_variation_schema_has_expected_properties(): void {
		$props = Schema::variation()['properties'];

		$this->assertArrayHasKey( 'id', $props );
		$this->assertArrayHasKey( 'sku', $props );
		$this->assertArrayHasKey( 'regular_price', $props );
		$this->assertArrayHasKey( 'stock_status', $props );
		$this->assertArrayHasKey( 'attributes', $props );
	}

	/**
	 * Arg schemas have sanitize_callback or type for each entry.
	 *
	 * @dataProvider arg_schema_provider
	 *
	 * @param string $method Arg schema method name.
	 * @return void
	 */
	public function test_arg_schemas_have_type( string $method ): void {
		$args = Schema::$method();

		$this->assertIsArray( $args );
		foreach ( $args as $key => $definition ) {
			$this->assertIsString( $key );
			$this->assertArrayHasKey( 'type', $definition, "Arg '{$key}' in {$method}() missing 'type'." );
		}
	}

	/**
	 * Provides arg schema method names.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function arg_schema_provider(): array {
		return array(
			'jobs_list_args'          => array( 'jobs_list_args' ),
			'job_create_args'         => array( 'job_create_args' ),
			'id_arg'                  => array( 'id_arg' ),
			'variations_list_args'    => array( 'variations_list_args' ),
			'variations_generate_args' => array( 'variations_generate_args' ),
			'ai_suggest_args'         => array( 'ai_suggest_args' ),
			'template_create_args'    => array( 'template_create_args' ),
		);
	}
}
