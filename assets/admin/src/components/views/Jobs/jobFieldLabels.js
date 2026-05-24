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
	status: __( 'Status', 'coderembassy-bulk-variations-manager' ),
};

const SALE_DATE_META_KEYS = new Set( [
	'_sale_price_dates_from',
	'_sale_price_dates_to',
] );

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
