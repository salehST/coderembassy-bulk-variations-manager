/**
 * REST endpoint helpers for coderembassy-bvm/v1.
 */
import { bvListRequest, bvRequest } from './client';

const path = ( segment ) => segment.replace( /^\/+/, '' );

export const jobs = {
	list( query = {} ) {
		return bvListRequest( {
			path: path( 'jobs' ),
			method: 'GET',
			data: query,
		} );
	},
	get( id ) {
		return bvRequest( { path: path( `jobs/${ id }` ), method: 'GET' } );
	},
	create( body ) {
		return bvRequest( {
			path: path( 'jobs' ),
			method: 'POST',
			data: body,
		} );
	},
	apply( id ) {
		return bvRequest( {
			path: path( `jobs/${ id }/apply` ),
			method: 'POST',
		} );
	},
	cancel( id ) {
		return bvRequest( {
			path: path( `jobs/${ id }/cancel` ),
			method: 'POST',
		} );
	},
	discard( id ) {
		return bvRequest( {
			path: path( `jobs/${ id }/discard` ),
			method: 'POST',
		} );
	},
	rollback( id ) {
		return bvRequest( {
			path: path( `jobs/${ id }/rollback` ),
			method: 'POST',
		} );
	},
	rollbackPreview( id ) {
		return bvRequest( {
			path: path( `jobs/${ id }/rollback/preview` ),
			method: 'GET',
		} );
	},
};

export const variations = {
	list( query = {} ) {
		return bvListRequest( {
			path: path( 'variations' ),
			method: 'GET',
			data: query,
		} );
	},
};

export const products = {
	search( query = '' ) {
		return bvRequest( {
			path: path( 'products' ),
			method: 'GET',
			data: { search: query },
		} );
	},
	importAttributes( productId ) {
		return bvRequest( {
			path: path( `products/${ productId }/import-attributes` ),
			method: 'GET',
		} );
	},
	importCsvTemplate( productId ) {
		return bvRequest( {
			path: path( `products/${ productId }/import-csv-template` ),
			method: 'GET',
		} );
	},
};

export const imports = {
	preview( body ) {
		return bvRequest( {
			path: path( 'imports/preview' ),
			method: 'POST',
			data: body,
		} );
	},
};

export const settings = {
	get() {
		return bvRequest( { path: path( 'settings' ), method: 'GET' } );
	},
	update( body ) {
		return bvRequest( {
			path: path( 'settings' ),
			method: 'POST',
			data: body,
		} );
	},
};
