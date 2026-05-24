<?php
/**
 * Price formula evaluator.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Services;

class PriceCalculator {
	public function apply( string $formula, float $price ): float {
		$trimmed = trim( $formula );

		if ( '' === $trimmed ) {
			return $price;
		}

		if ( preg_match( '/^[+-]?\d+(?:\.\d+)?%$/', $trimmed ) ) {
			$percent = (float) rtrim( $trimmed, '%' );
			return $price + ( $price * ( $percent / 100 ) );
		}

		if ( preg_match( '/^[+-]?\d+(?:\.\d+)?$/', $trimmed ) ) {
			return (float) $trimmed;
		}

		if ( ! str_starts_with( $trimmed, '=' ) ) {
			throw new InvalidFormulaException( 'Unsupported formula.' );
		}

		$body = substr( $trimmed, 1 );
		if ( preg_match( '/^ROUND\(\s*PRICE\s*([\+\-\*\/])\s*([0-9]+(?:\.[0-9]+)?)\s*,\s*(\d+)\s*\)$/i', $body, $matches ) ) {
			$computed = $this->evaluateOperation( $price, $matches[1], (float) $matches[2] );
			return round( $computed, (int) $matches[3] );
		}

		if ( preg_match( '/^PRICE\s*([\+\-\*\/])\s*([0-9]+(?:\.[0-9]+)?)$/i', $body, $matches ) ) {
			return $this->evaluateOperation( $price, $matches[1], (float) $matches[2] );
		}

		if ( preg_match( '/[A-Za-z_]|["\';`]/', $body ) ) {
			throw new InvalidFormulaException( 'Unsafe formula.' );
		}

		throw new InvalidFormulaException( 'Unsupported formula.' );
	}

	private function evaluateOperation( float $left, string $operator, float $right ): float {
		return match ( $operator ) {
			'+' => $left + $right,
			'-' => $left - $right,
			'*' => $left * $right,
			'/' => 0.0 === $right ? 0.0 : $left / $right,
			default => throw new InvalidFormulaException( 'Malformed formula.' ),
		};
	}
}

