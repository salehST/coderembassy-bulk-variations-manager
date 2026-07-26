<?php
/**
 * Editor preview mapper.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\REST;

class EditorPreviewChanges {
	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	public static function build( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$id       = (int) ( $row['variation_id'] ?? $row['object_id'] ?? 0 );
			$old      = (string) ( $row['old_value'] ?? $row['from'] ?? '' );
			$new      = (string) ( $row['new_value'] ?? $row['to'] ?? '' );
			$out[] = array(
				'variation_id' => $id,
				'object_id'    => $id,
				'product_id'   => (int) ( $row['product_id'] ?? 0 ),
				'product_title' => (string) ( $row['product_title'] ?? '' ),
				'field'        => (string) ( $row['field'] ?? '' ),
				'old_value'    => $old,
				'new_value'    => $new,
			);
		}
		return $out;
	}
}
