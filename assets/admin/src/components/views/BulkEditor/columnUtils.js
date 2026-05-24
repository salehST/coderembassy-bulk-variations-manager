/**
 * Helpers for parsing variations list API payloads.
 */

/**
 * @param {*} response REST list response.
 * @return {{ variations: Array<Object>, attributeColumns: Array<Object> }}
 */
export function parseVariationsListResponse( response ) {
	let payload = null;

	if (
		response &&
		typeof response === 'object' &&
		! Array.isArray( response )
	) {
		if (
			response.data &&
			typeof response.data === 'object' &&
			! Array.isArray( response.data )
		) {
			payload = response.data;
		} else {
			payload = response;
		}
	}

	if ( payload && Array.isArray( payload.variations ) ) {
		const attributeColumns = Array.isArray( payload.attribute_columns )
			? payload.attribute_columns.map( ( column ) => {
					const field = String( column?.field || column?.id || '' );
					return {
						...column,
						id: field,
						field,
						label: String( column?.label || field ),
						options: Array.isArray( column?.options )
							? column.options
							: [],
					};
			  } )
			: [];

		return {
			variations: payload.variations,
			attributeColumns,
		};
	}

	if ( Array.isArray( response?.data ) ) {
		return {
			variations: response.data,
			attributeColumns: [],
		};
	}

	if ( Array.isArray( response ) ) {
		return {
			variations: response,
			attributeColumns: [],
		};
	}

	return {
		variations: [],
		attributeColumns: [],
	};
}

/**
 * @param {Array<Object>} batches Parsed batch payloads.
 * @return {{ variations: Array<Object>, attributeColumns: Array<Object> }}
 */
export function mergeVariationListBatches( batches ) {
	const variations = [];
	const columnMap = new Map();

	batches.forEach( ( batch ) => {
		variations.push( ...( batch?.variations || [] ) );
		( batch?.attributeColumns || [] ).forEach( ( column ) => {
			const id = String( column?.id || column?.field || '' );
			if ( id ) {
				columnMap.set( id, column );
			}
		} );
	} );

	return {
		variations,
		attributeColumns: Array.from( columnMap.values() ),
	};
}
