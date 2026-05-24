/**
 * Hash routing helpers for the admin SPA.
 */

export const DEFAULT_ROUTE = 'dashboard';

const normalizeHashPath = ( value ) => {
	const raw = String( value || '' ).replace( /^#/, '' );
	const withSlash = raw.startsWith( '/' ) ? raw : `/${ raw }`;
	return withSlash === '/' ? `/${ DEFAULT_ROUTE }` : withSlash;
};

export function parseHash() {
	const rawPath = normalizeHashPath( window.location.hash );
	const [ pathPart ] = rawPath.split( '?' );
	const path = pathPart.replace( /^\/+/, '' ) || DEFAULT_ROUTE;
	const segments = path.split( '/' ).filter( Boolean );
	const base = segments[ 0 ] || DEFAULT_ROUTE;

	return {
		raw: rawPath,
		path,
		base,
		segments,
	};
}

export function navigateTo( route ) {
	const next = String( route || DEFAULT_ROUTE ).replace( /^\/+/, '' );
	window.location.hash = `#/${ next }`;
}
