/**
 * Client mirror of PHP PriceCalculator — safe PRICE formulas.
 */

/**
 * @param {string} formula Formula input.
 * @param {number} current Current numeric value.
 * @return {number}
 */
export function applyPriceFormula( formula, current ) {
	const input = String( formula || '' ).trim();
	if ( ! input ) {
		throw new Error( 'Formula cannot be empty.' );
	}

	if ( /^[+-]?\d+(?:\.\d+)?%$/.test( input ) ) {
		const percent = parseFloat( input.replace( '%', '' ) );
		return current + current * ( percent / 100 );
	}

	if ( ! Number.isNaN( Number( input ) ) && input !== '' ) {
		return Number( input );
	}

	if ( ! input.toUpperCase().startsWith( '=' ) ) {
		throw new Error( 'Unsupported formula syntax.' );
	}

	const expr = input.slice( 1 ).trim();
	const priceOp = expr.match( /^PRICE\s*([*+])\s*(-?\d+(?:\.\d+)?)$/i );
	if ( priceOp ) {
		const operand = parseFloat( priceOp[ 2 ] );
		return priceOp[ 1 ] === '*' ? current * operand : current + operand;
	}

	const roundOp = expr.match(
		/^ROUND\(\s*PRICE\s*([*+])\s*(-?\d+(?:\.\d+)?)\s*,\s*(\d+)\s*\)$/i
	);
	if ( roundOp ) {
		const operand = parseFloat( roundOp[ 2 ] );
		const precision = parseInt( roundOp[ 3 ], 10 );
		const value =
			roundOp[ 1 ] === '*' ? current * operand : current + operand;
		const factor = 10 ** precision;
		return Math.round( value * factor ) / factor;
	}

	throw new Error( 'Unsupported formula syntax.' );
}

/**
 * @param {string} formula Formula input.
 * @return {boolean}
 */
export function isPriceFormula( formula ) {
	const input = String( formula || '' ).trim();
	return (
		/^[+-]?\d+(?:\.\d+)?%$/.test( input ) ||
		input.toUpperCase().includes( 'PRICE' ) ||
		input.startsWith( '=' )
	);
}
