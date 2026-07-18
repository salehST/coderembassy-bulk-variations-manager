<?php
/**
 * REST schema catalog.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\REST;

class Schema {
	/**
	 * @return array<string, mixed>
	 */
	private static function objectSchema( array $properties ): array {
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function job(): array {
		return self::objectSchema(
			array(
				'id'         => array( 'type' => 'integer' ),
				'type'       => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string' ),
				'progress'   => array( 'type' => 'integer' ),
				'total_items'=> array( 'type' => 'integer' ),
				'source'     => array( 'type' => 'string' ),
				'created_at' => array( 'type' => 'string' ),
			)
		);
	}

	public static function job_change(): array {
		return self::objectSchema(
			array(
				'object_id' => array( 'type' => 'integer' ),
				'field'     => array( 'type' => 'string' ),
				'old_value' => array( 'type' => 'string' ),
				'new_value' => array( 'type' => 'string' ),
			)
		);
	}

	public static function variation(): array {
		return self::objectSchema(
			array(
				'id'            => array( 'type' => 'integer' ),
				'sku'           => array( 'type' => 'string' ),
				'regular_price' => array( 'type' => 'string' ),
				'stock_status'  => array( 'type' => 'string' ),
				'attributes'    => array( 'type' => 'array' ),
			)
		);
	}

	public static function template(): array {
		return self::objectSchema( array( 'id' => array( 'type' => 'integer' ) ) );
	}

	public static function ai_suggestion(): array {
		return self::objectSchema( array( 'text' => array( 'type' => 'string' ) ) );
	}

	public static function rule(): array {
		return self::objectSchema( array( 'id' => array( 'type' => 'integer' ) ) );
	}

	public static function schedule(): array {
		return self::objectSchema( array( 'id' => array( 'type' => 'integer' ) ) );
	}

	public static function heatmap_cell(): array {
		return self::objectSchema( array( 'value' => array( 'type' => 'number' ) ) );
	}

	public static function activity_event(): array {
		return self::objectSchema( array( 'event' => array( 'type' => 'string' ) ) );
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function baseArgs(): array {
		return array(
			'page'     => array( 'type' => 'integer' ),
			'per_page' => array( 'type' => 'integer' ),
		);
	}

	public static function jobs_list_args(): array {
		return self::baseArgs();
	}

	public static function job_create_args(): array {
		return array( 'type' => array( 'type' => 'string' ) );
	}

	public static function id_arg(): array {
		return array( 'id' => array( 'type' => 'integer' ) );
	}

	public static function variations_list_args(): array {
		return array( 'product_id' => array( 'type' => 'integer' ) );
	}

	public static function variations_generate_args(): array {
		return array( 'product_id' => array( 'type' => 'integer' ) );
	}

	public static function ai_suggest_args(): array {
		return array( 'prompt' => array( 'type' => 'string' ) );
	}

	public static function template_create_args(): array {
		return array( 'name' => array( 'type' => 'string' ) );
	}
}

