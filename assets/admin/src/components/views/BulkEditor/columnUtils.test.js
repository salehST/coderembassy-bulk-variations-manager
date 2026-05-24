import {
	mergeVariationListBatches,
	parseVariationsListResponse,
} from './columnUtils';

describe( 'parseVariationsListResponse', () => {
	it( 'reads wrapped variations and attribute_columns', () => {
		const parsed = parseVariationsListResponse( {
			variations: [ { variation_id: 1, attribute_pa_color: 'blue' } ],
			attribute_columns: [
				{
					id: 'attribute_pa_color',
					field: 'attribute_pa_color',
					label: 'Color',
				},
			],
		} );

		expect( parsed.variations ).toHaveLength( 1 );
		expect( parsed.attributeColumns[ 0 ].label ).toBe( 'Color' );
	} );

	it( 'reads list payloads nested under data', () => {
		const parsed = parseVariationsListResponse( {
			data: {
				variations: [ { variation_id: 3 } ],
				attribute_columns: [
					{ id: 'attribute_pa_size', label: 'Size' },
				],
			},
		} );

		expect( parsed.variations[ 0 ].variation_id ).toBe( 3 );
		expect( parsed.attributeColumns[ 0 ].id ).toBe( 'attribute_pa_size' );
	} );

	it( 'supports legacy array responses', () => {
		const parsed = parseVariationsListResponse( [ { variation_id: 2 } ] );
		expect( parsed.variations ).toHaveLength( 1 );
		expect( parsed.attributeColumns ).toEqual( [] );
	} );
} );

describe( 'mergeVariationListBatches', () => {
	it( 'merges attribute columns by id', () => {
		const merged = mergeVariationListBatches( [
			{
				variations: [ { variation_id: 1 } ],
				attributeColumns: [
					{ id: 'attribute_pa_color', label: 'Color' },
				],
			},
			{
				variations: [ { variation_id: 2 } ],
				attributeColumns: [
					{ id: 'attribute_pa_size', label: 'Size' },
				],
			},
		] );

		expect( merged.variations ).toHaveLength( 2 );
		expect( merged.attributeColumns ).toHaveLength( 2 );
	} );
} );
