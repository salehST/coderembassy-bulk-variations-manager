import {
	commitSaleDateFromEditor,
	normalizeSaleDateValue,
	saleDateValuesEquivalent,
} from './saleDateUtils';

describe( 'normalizeSaleDateValue', () => {
	it( 'returns empty string for nullish and blank', () => {
		expect( normalizeSaleDateValue( null ) ).toBe( '' );
		expect( normalizeSaleDateValue( undefined ) ).toBe( '' );
		expect( normalizeSaleDateValue( '' ) ).toBe( '' );
		expect( normalizeSaleDateValue( '   ' ) ).toBe( '' );
	} );

	it( 'keeps ISO YYYY-MM-DD', () => {
		expect( normalizeSaleDateValue( '2026-07-27' ) ).toBe( '2026-07-27' );
		expect( normalizeSaleDateValue( '2026-07-27T12:00:00' ) ).toBe(
			'2026-07-27'
		);
	} );

	it( 'converts US MM/DD/YYYY to YYYY-MM-DD', () => {
		expect( normalizeSaleDateValue( '07/27/2026' ) ).toBe( '2026-07-27' );
		expect( normalizeSaleDateValue( '7/7/2026' ) ).toBe( '2026-07-07' );
	} );

	it( 'normalizes Date objects', () => {
		expect( normalizeSaleDateValue( new Date( 2026, 6, 27 ) ) ).toBe(
			'2026-07-27'
		);
	} );
} );

describe( 'commitSaleDateFromEditor', () => {
	it( 'returns null when cleared', () => {
		expect( commitSaleDateFromEditor( '' ) ).toBeNull();
	} );

	it( 'commits latest selected ISO date from editor ref', () => {
		expect( commitSaleDateFromEditor( '2026-07-27' ) ).toBe( '2026-07-27' );
	} );
} );

describe( 'saleDateValuesEquivalent', () => {
	it( 'treats ISO and equivalent values as unchanged', () => {
		expect( saleDateValuesEquivalent( '2026-02-26', '2026-02-26' ) ).toBe(
			true
		);
		expect( saleDateValuesEquivalent( '2026-02-26', null ) ).toBe( false );
	} );
} );
