/**
 * REST wrapper - nonce, error envelope, list pagination headers.
 */
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store';

const getNonce = () => window.CoderEmbassyBvmAdmin?.nonce || '';

const getRestUrl = () =>
	window.CoderEmbassyBvmAdmin?.rest_url || '/wp-json/coderembassy-bvm/v1/';

const extractJsonCandidate = ( text ) => {
	const source = text.trim();
	const firstArray = source.indexOf( '[' );
	const firstObject = source.indexOf( '{' );
	const starts = [ firstArray, firstObject ].filter(
		( index ) => index >= 0
	);

	if ( starts.length === 0 ) {
		return source;
	}

	const start = Math.min( ...starts );
	const opener = source[ start ];
	const closer = opener === '[' ? ']' : '}';
	let depth = 0;
	let inString = false;
	let escaped = false;

	for ( let index = start; index < source.length; index += 1 ) {
		const char = source[ index ];

		if ( inString ) {
			if ( escaped ) {
				escaped = false;
			} else if ( char === '\\' ) {
				escaped = true;
			} else if ( char === '"' ) {
				inString = false;
			}
			continue;
		}

		if ( char === '"' ) {
			inString = true;
			continue;
		}

		if ( char === opener ) {
			depth += 1;
		} else if ( char === closer ) {
			depth -= 1;
			if ( depth === 0 ) {
				return source.slice( start, index + 1 );
			}
		}
	}

	return source.slice( start );
};

const isWordPressCriticalHtml = ( text, response ) => {
	const contentType = response?.headers?.get?.( 'content-type' ) || '';

	if ( contentType.includes( 'text/html' ) ) {
		return true;
	}

	const trimmed = text.trim();

	if ( trimmed.startsWith( '<' ) ) {
		return true;
	}

	return /critical error on this website/i.test( trimmed );
};

const parseJsonText = ( text ) => {
	if ( ! text.trim() ) {
		return null;
	}

	try {
		return JSON.parse( text );
	} catch ( error ) {
		void error;
	}

	return JSON.parse( extractJsonCandidate( text ) );
};

const buildUrl = ( path, data = {}, method = 'GET' ) => {
	const url = new URL( path.replace( /^\/+/, '' ), getRestUrl() );

	if ( method.toUpperCase() === 'GET' ) {
		Object.entries( data || {} ).forEach( ( [ key, value ] ) => {
			if ( value !== undefined && value !== null && value !== '' ) {
				url.searchParams.set( key, String( value ) );
			}
		} );
	}

	return url.toString();
};

const request = async ( options ) => {
	const method = ( options.method || 'GET' ).toUpperCase();
	const headers = {
		Accept: 'application/json',
		...( options.headers || {} ),
	};
	const nonce = getNonce();

	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	const fetchOptions = {
		method,
		headers,
		credentials: 'same-origin',
	};

	if ( method !== 'GET' && options.data !== undefined ) {
		headers[ 'Content-Type' ] = 'application/json';
		fetchOptions.body = JSON.stringify( options.data );
	}

	const response = await window.fetch(
		buildUrl( options.path, options.data, method ),
		fetchOptions
	);
	const text = await response.text();

	if ( isWordPressCriticalHtml( text, response ) ) {
		// eslint-disable-next-line no-console
		console.error(
			'CoderEmbassy Bulk Variations Manager REST returned HTML:',
			text.slice( 0, 300 )
		);
		const error = new Error(
			__(
				'CSV preview failed because the server returned a WordPress critical error. Check the PHP error log.',
				'coderembassy-bulk-variations-manager'
			)
		);
		error.status = response.status || 500;
		error.code = 'coderembassy_bvm_wordpress_critical_error';
		error.isWordPressCriticalError = true;
		throw error;
	}

	let body = null;

	try {
		body = parseJsonText( text );
	} catch ( error ) {
		error.status = response.status || 500;
		error.responseText = text.slice( 0, 500 );
		throw error;
	}

	if ( ! response.ok ) {
		const error = new Error( body?.message || response.statusText );
		error.status = response.status;
		error.data = body;
		error.responseText = text.slice( 0, 500 );
		throw error;
	}

	return { body, response };
};

const normalizeError = ( error ) => {
	const status = error?.data?.status || error?.status || 500;
	let message =
		error?.message ||
		error?.data?.message ||
		__( 'Something went wrong.', 'coderembassy-bulk-variations-manager' );

	if (
		error?.responseText &&
		message.includes( 'Unexpected' ) &&
		! error?.isWordPressCriticalError
	) {
		message = `${ message }: ${ error.responseText }`;
	}

	if ( status >= 500 ) {
		dispatch( STORE_NAME ).pushToast( {
			type: 'error',
			message,
		} );
	}

	const normalized = {
		code: error?.code || 'coderembassy_bvm_error',
		message,
		status,
		detail:
			error?.data?.detail || error?.data || error?.responseText || null,
	};

	const wrapped = new Error( message );
	wrapped.status = status;
	wrapped.bv = normalized;
	throw wrapped;
};

/**
 * @param {Object} options Request options.
 * @return {Promise<*>} Parsed response body.
 */
export async function bvRequest( options ) {
	try {
		const { body } = await request( options );
		return body;
	} catch ( error ) {
		return normalizeError( error );
	}
}

/**
 * Parse list responses with X-WP-Total headers.
 *
 * @param {Object} options Request options.
 * @return {Promise<{ data: *, total: number, totalPages: number }>} List payload and pagination totals.
 */
export async function bvListRequest( options ) {
	try {
		const { body, response } = await request( options );
		return {
			data: body,
			total: parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ),
			totalPages: parseInt(
				response.headers.get( 'X-WP-TotalPages' ) || '0',
				10
			),
		};
	} catch ( error ) {
		return normalizeError( error );
	}
}
