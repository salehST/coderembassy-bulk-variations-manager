/**
 * Human-readable labels for job diff / history fields.
 */
import { __ } from '@wordpress/i18n';

const FIELD_LABELS = {
	sale_price: __( 'Sale price', 'coderembassy-bulk-variations-manager' ),
	sale_from: __( 'Sale from', 'coderembassy-bulk-variations-manager' ),
	sale_to: __( 'Sale to', 'coderembassy-bulk-variations-manager' ),
	regular_price: __(
		'Regular price',
		'coderembassy-bulk-variations-manager'
	),
	sku: __( 'SKU', 'coderembassy-bulk-variations-manager' ),
	stock_quantity: __( 'Stock qty', 'coderembassy-bulk-variations-manager' ),
	stock_status: __( 'Stock status', 'coderembassy-bulk-variations-manager' ),
	manage_stock: __(
		'Stock management',
		'coderembassy-bulk-variations-manager'
	),
	virtual: __( 'Virtual', 'coderembassy-bulk-variations-manager' ),
	downloadable: __( 'Downloadable', 'coderembassy-bulk-variations-manager' ),
	downloadable_files: __(
		'Downloadable files',
		'coderembassy-bulk-variations-manager'
	),
	download_limit: __(
		'Download limit',
		'coderembassy-bulk-variations-manager'
	),
	download_expiry: __(
		'Download expiry',
		'coderembassy-bulk-variations-manager'
	),
	description: __( 'Description', 'coderembassy-bulk-variations-manager' ),
	image_id: __( 'Image', 'coderembassy-bulk-variations-manager' ),
	weight: __( 'Weight', 'coderembassy-bulk-variations-manager' ),
	length: __( 'Length', 'coderembassy-bulk-variations-manager' ),
	width: __( 'Width', 'coderembassy-bulk-variations-manager' ),
	height: __( 'Height', 'coderembassy-bulk-variations-manager' ),
	tax_class: __( 'Tax class', 'coderembassy-bulk-variations-manager' ),
	shipping_class_id: __(
		'Shipping class',
		'coderembassy-bulk-variations-manager'
	),
	product_id: __( 'Product ID', 'coderembassy-bulk-variations-manager' ),
	_sale_price: __( 'Sale price', 'coderembassy-bulk-variations-manager' ),
	_sale_price_dates_from: __(
		'Sale from',
		'coderembassy-bulk-variations-manager'
	),
	_sale_price_dates_to: __(
		'Sale to',
		'coderembassy-bulk-variations-manager'
	),
	_price: __( 'Active price', 'coderembassy-bulk-variations-manager' ),
	_regular_price: __(
		'Regular price',
		'coderembassy-bulk-variations-manager'
	),
	_sku: __( 'SKU', 'coderembassy-bulk-variations-manager' ),
	_stock: __( 'Stock qty', 'coderembassy-bulk-variations-manager' ),
	_stock_status: __( 'Stock status', 'coderembassy-bulk-variations-manager' ),
	_manage_stock: __(
		'Stock management',
		'coderembassy-bulk-variations-manager'
	),
	_virtual: __( 'Virtual', 'coderembassy-bulk-variations-manager' ),
	_downloadable: __( 'Downloadable', 'coderembassy-bulk-variations-manager' ),
	_downloadable_files: __(
		'Downloadable files',
		'coderembassy-bulk-variations-manager'
	),
	_download_limit: __(
		'Download limit',
		'coderembassy-bulk-variations-manager'
	),
	_download_expiry: __(
		'Download expiry',
		'coderembassy-bulk-variations-manager'
	),
	post_excerpt: __( 'Description', 'coderembassy-bulk-variations-manager' ),
	_thumbnail_id: __( 'Image', 'coderembassy-bulk-variations-manager' ),
	_weight: __( 'Weight', 'coderembassy-bulk-variations-manager' ),
	_length: __( 'Length', 'coderembassy-bulk-variations-manager' ),
	_width: __( 'Width', 'coderembassy-bulk-variations-manager' ),
	_height: __( 'Height', 'coderembassy-bulk-variations-manager' ),
	_tax_class: __( 'Tax class', 'coderembassy-bulk-variations-manager' ),
	product_shipping_class: __(
		'Shipping class',
		'coderembassy-bulk-variations-manager'
	),
	status: __( 'Status', 'coderembassy-bulk-variations-manager' ),
	post_status: __( 'Status', 'coderembassy-bulk-variations-manager' ),
};

const SALE_DATE_META_KEYS = new Set( [
	'_sale_price_dates_from',
	'_sale_price_dates_to',
] );

const STOCK_STATUS_VALUES = {
	instock: __( 'In stock', 'coderembassy-bulk-variations-manager' ),
	outofstock: __( 'Out of stock', 'coderembassy-bulk-variations-manager' ),
	onbackorder: __( 'On backorder', 'coderembassy-bulk-variations-manager' ),
};

const PRODUCT_STATUS_VALUES = {
	publish: __( 'Published', 'coderembassy-bulk-variations-manager' ),
	private: __( 'Private', 'coderembassy-bulk-variations-manager' ),
	draft: __( 'Draft', 'coderembassy-bulk-variations-manager' ),
	pending: __( 'Pending review', 'coderembassy-bulk-variations-manager' ),
	trash: __( 'Trash', 'coderembassy-bulk-variations-manager' ),
};

const YES_NO_VALUES = {
	yes: __( 'Enabled', 'coderembassy-bulk-variations-manager' ),
	no: __( 'Disabled', 'coderembassy-bulk-variations-manager' ),
};

function formatDownloadableFilesValue( text ) {
	try {
		const files = JSON.parse( text );
		if ( Array.isArray( files ) ) {
			return files
				.map( ( file ) => {
					const name = String( file?.name || '' ).trim();
					const url = String( file?.file || file?.url || '' ).trim();
					return [ name, url ].filter( Boolean ).join( ' - ' );
				} )
				.filter( Boolean )
				.join( '; ' );
		}
	} catch {}

	const pairs = [
		...text.matchAll( /s:\d+:"(name|file)";s:\d+:"([^"]*)"/g ),
	];
	if ( pairs.length ) {
		const files = [];
		let current = {};
		pairs.forEach( ( match ) => {
			current[ match[ 1 ] ] = match[ 2 ];
			if ( current.name !== undefined && current.file !== undefined ) {
				files.push( current );
				current = {};
			}
		} );
		return files
			.map( ( file ) =>
				[ file.name || '', file.file || '' ]
					.filter( Boolean )
					.join( ' - ' )
			)
			.filter( Boolean )
			.join( '; ' );
	}

	return text;
}

/**
 * @param {string} field Raw field key from history.
 * @return {string} Human-readable field label.
 */
export function formatDiffFieldLabel( field ) {
	const key = String( field || '' ).trim();
	return FIELD_LABELS[ key ] || key;
}

/**
 * @param {string}                       field Field key.
 * @param {string|number|null|undefined} value Stored value.
 * @return {string} Display value for diff cells.
 */
export function formatDiffCellValue( field, value ) {
	const text = String( value ?? '' ).trim();
	if ( text === '' ) {
		return '';
	}

	if ( field === 'stock_status' || field === '_stock_status' ) {
		return STOCK_STATUS_VALUES[ text ] || text;
	}

	if ( field === 'status' || field === 'post_status' ) {
		return PRODUCT_STATUS_VALUES[ text ] || text;
	}

	if ( field === 'manage_stock' || field === '_manage_stock' ) {
		return YES_NO_VALUES[ text ] || text;
	}

	if (
		field === 'virtual' ||
		field === '_virtual' ||
		field === 'downloadable' ||
		field === '_downloadable'
	) {
		return YES_NO_VALUES[ text ] || text;
	}

	if ( field === 'downloadable_files' || field === '_downloadable_files' ) {
		return formatDownloadableFilesValue( text );
	}

	if ( SALE_DATE_META_KEYS.has( field ) && /^\d{9,}$/.test( text ) ) {
		const ts = parseInt( text, 10 );
		if ( ! Number.isNaN( ts ) && ts > 0 ) {
			const date = new Date( ts * 1000 );
			const y = date.getFullYear();
			const m = String( date.getMonth() + 1 ).padStart( 2, '0' );
			const d = String( date.getDate() ).padStart( 2, '0' );
			return `${ y }-${ m }-${ d }`;
		}
	}

	return text;
}
