import {
	applyPendingEdit,
	buildPendingChange,
	normalizePendingFieldValue,
} from './pendingChangeUtils';

describe( 'pendingChangeUtils', () => {
	it( 'normalizes formatted currency into raw numeric strings', () => {
		expect( normalizePendingFieldValue( 'regular_price', '$550.00' ) ).toBe(
			'550.00'
		);
		expect( normalizePendingFieldValue( 'regular_price', '501' ) ).toBe(
			'501'
		);
	} );

	it( 'builds a pending change for a regular price edit', () => {
		expect(
			buildPendingChange( {
				variation_id: 19,
				field: 'regular_price',
				oldValue: '501',
				newValue: '550',
			} )
		).toEqual( {
			variation_id: 19,
			field: 'regular_price',
			old_value: '501',
			new_value: '550',
		} );
	} );

	it( 'upserts by variation and field', () => {
		const first = applyPendingEdit( [], {
			variation_id: 19,
			field: 'regular_price',
			oldValue: '501',
			newValue: '550',
		} );
		const second = applyPendingEdit( first, {
			variation_id: 19,
			field: 'regular_price',
			oldValue: '501',
			newValue: '575',
		} );

		expect( second ).toHaveLength( 1 );
		expect( second[ 0 ].new_value ).toBe( '575' );
	} );
} );
