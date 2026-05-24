/**
 * Price editor helpers.
 */
import { applyPriceFormula, isPriceFormula } from './formulaParser';

export function normalizePriceInput( value ) {
	const input = String( value ?? '' ).trim();
	if ( input === '' ) {
		return '';
	}
	const normalized = input.replace( /,/g, '' ).replace( /[^0-9.-]/g, '' );
	return normalized || input;
}

export function commitPriceFromEditor( draftValue, currentValue ) {
	const input = String( draftValue ?? '' ).trim();
	if ( input === '' ) {
		return '';
	}
	if ( isPriceFormula( input ) ) {
		const next = applyPriceFormula( input, Number( currentValue || 0 ) );
		if ( Number.isNaN( next ) ) {
			return currentValue;
		}
		return String( next );
	}
	return normalizePriceInput( input );
}
