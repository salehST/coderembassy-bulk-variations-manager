/**
 * Column order tests without WordPress i18n (logic-only duplicate of layout rules).
 */

const BASE_START = [ 'variation_id', 'sku', 'image_url' ];
const BASE_END = [ 'regular_price', '__actions' ];

/**
 * @param {string[]} attributeFields Attribute field ids.
 * @return {string[]}
 */
function orderFields( attributeFields ) {
	return [ ...BASE_START, ...attributeFields, ...BASE_END ];
}

describe( 'bulk editor attribute column order', () => {
	it( 'places attributes after image and before editable price columns', () => {
		const fields = orderFields( [
			'attribute_pa_color',
			'attribute_pa_size',
		] );

		expect( fields.indexOf( 'image_url' ) ).toBeLessThan(
			fields.indexOf( 'attribute_pa_color' )
		);
		expect( fields.indexOf( 'attribute_pa_size' ) ).toBeLessThan(
			fields.indexOf( 'regular_price' )
		);
		expect( fields.indexOf( 'regular_price' ) ).toBeLessThan(
			fields.indexOf( '__actions' )
		);
	} );
} );

/**
 * @param {Array<{ slug?: string, label?: string }>} options Options.
 * @return {Map<string, string>}
 */
function buildSlugLabelMap( options ) {
	const map = new Map();
	( options || [] ).forEach( ( option ) => {
		const slug = String( option?.slug ?? '' ).trim();
		if ( slug ) {
			map.set( slug, String( option?.label || slug ).trim() || slug );
		}
	} );
	return map;
}

describe( 'attribute slug label formatting', () => {
	it( 'maps stored slugs to display labels', () => {
		const map = buildSlugLabelMap( [
			{ slug: 'blue', label: 'Blue' },
			{ slug: 'xl', label: 'XL' },
		] );

		expect( map.get( 'blue' ) ).toBe( 'Blue' );
		expect( map.get( 'xl' ) ).toBe( 'XL' );
		expect( map.get( 'missing' ) ).toBeUndefined();
	} );
} );
