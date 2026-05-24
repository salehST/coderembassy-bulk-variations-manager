<?php
/**
 * PriceCalculator tests.
 *
 * @package BulkVariations\Tests\Unit\Services
 */

declare(strict_types=1);

namespace BulkVariations\Tests\Unit\Services;

use BulkVariations\Services\InvalidFormulaException;
use BulkVariations\Services\PriceCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkVariations\Services\PriceCalculator
 */
class PriceCalculatorTest extends TestCase {

	/**
	 * Multiplication formula works.
	 *
	 * @return void
	 */
	public function test_apply_price_multiply_formula(): void {
		$calculator = new PriceCalculator();
		$this->assertEqualsWithDelta( 115.0, $calculator->apply( '=PRICE*1.15', 100.0 ), 0.00001 );
	}

	/**
	 * Addition formula works.
	 *
	 * @return void
	 */
	public function test_apply_price_add_formula(): void {
		$calculator = new PriceCalculator();
		$this->assertSame( 105.0, $calculator->apply( '=PRICE+5', 100.0 ) );
	}

	/**
	 * ROUND wrapper works.
	 *
	 * @return void
	 */
	public function test_apply_round_formula(): void {
		$calculator = new PriceCalculator();
		$this->assertSame( 90.0, $calculator->apply( '=ROUND(PRICE*0.9,2)', 100.0 ) );
	}

	/**
	 * Plain number works.
	 *
	 * @return void
	 */
	public function test_apply_plain_number_formula(): void {
		$calculator = new PriceCalculator();
		$this->assertSame( 19.99, $calculator->apply( '19.99', 100.0 ) );
	}

	/**
	 * Percentage string works.
	 *
	 * @return void
	 */
	public function test_apply_percentage_formula(): void {
		$calculator = new PriceCalculator();
		$this->assertSame( 115.0, $calculator->apply( '+15%', 100.0 ) );
	}

	/**
	 * Unsafe formulas are rejected.
	 *
	 * @return void
	 */
	public function test_apply_rejects_unsafe_formula(): void {
		$this->expectException( InvalidFormulaException::class );
		$calculator = new PriceCalculator();
		$calculator->apply( '=system("rm -rf /")', 100.0 );
	}
}

