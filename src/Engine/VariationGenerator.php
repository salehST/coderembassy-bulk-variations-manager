<?php
/**
 * Variation combination generator.
 *
 * @package CoderEmbassyBulkVariationsManager
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Engine;

class VariationGenerator {
	private VariationRepository $repository;

	private AttributeMatrix $matrix;

	private ?VariationWriter $writer;

	public function __construct(
		VariationRepository $repository,
		?AttributeMatrix $matrix = null,
		?VariationWriter $writer = null
	) {
		$this->repository = $repository;
		$this->matrix     = $matrix ?? new AttributeMatrix();
		$this->writer     = $writer;
	}

	/**
	 * @param array<string, array<int, string>> $attributes
	 * @return array<int, array<string, mixed>>
	 */
	public function generateCombinations( int $product_id, array $attributes, int $max = 5000 ): array {
		$normalized = $this->matrix->normalize( $attributes );
		$total      = 1;
		foreach ( $normalized as $values ) {
			$total *= max( 1, count( $values ) );
		}
		if ( $total > $max ) {
			throw new TooManyCombinationsException( 'Combination count exceeds safety limit.' );
		}

		$existing     = $this->repository->getExistingCombinationSignatures( $product_id );
		$combinations = $this->matrix->cartesian( $normalized );
		$out          = array();
		foreach ( $combinations as $combo ) {
			ksort( $combo );
			$signature = wp_json_encode( $combo );
			if ( isset( $existing[ $signature ] ) ) {
				continue;
			}
			$out[] = array(
				'product_id'  => $product_id,
				'attributes'  => $combo,
				'signature'   => $signature,
				'variation_id' => 0,
			);
		}
		return $out;
	}
}

