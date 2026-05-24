/**
 * Shared normalization/helpers for sale date fields.
 */

const ISO_DATE_RE = /^(\d{4})-(\d{1,2})-(\d{1,2})/;
const US_DATE_RE = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;

const pad = ( value ) => String( value ).padStart( 2, '0' );

const toIso = ( year, month, day ) =>
	`${ year }-${ pad( month ) }-${ pad( day ) }`;

export function normalizeSaleDateValue( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}

	if ( value instanceof Date ) {
		if ( Number.isNaN( value.getTime() ) ) {
			return '';
		}
		return toIso(
			value.getFullYear(),
			value.getMonth() + 1,
			value.getDate()
		);
	}

	const text = String( value ).trim();
	if ( ! text ) {
		return '';
	}

	const isoMatch = text.match( ISO_DATE_RE );
	if ( isoMatch ) {
		return toIso( isoMatch[ 1 ], isoMatch[ 2 ], isoMatch[ 3 ] );
	}

	const usMatch = text.match( US_DATE_RE );
	if ( usMatch ) {
		return toIso( usMatch[ 3 ], usMatch[ 1 ], usMatch[ 2 ] );
	}

	return text.slice( 0, 10 );
}

export function commitSaleDateFromEditor( value ) {
	const normalized = normalizeSaleDateValue( value );
	return normalized || null;
}

export function saleDateValuesEquivalent( left, right ) {
	return normalizeSaleDateValue( left ) === normalizeSaleDateValue( right );
}

export function getSaleDateScheduleHint( field, value ) {
	const normalized = normalizeSaleDateValue( value );
	if ( ! normalized ) {
		return null;
	}

	const current = new Date();
	current.setHours( 0, 0, 0, 0 );

	const date = new Date( `${ normalized }T00:00:00` );
	if ( Number.isNaN( date.getTime() ) ) {
		return null;
	}

	if ( field === 'sale_to' && date < current ) {
		return { type: 'expired' };
	}
	if ( field === 'sale_from' && date > current ) {
		return { type: 'future' };
	}

	return null;
}
