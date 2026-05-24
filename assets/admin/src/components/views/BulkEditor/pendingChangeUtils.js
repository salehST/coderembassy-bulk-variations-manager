/**
 * Helpers for staging bulk editor cell edits into REST job payloads.
 */
import { normalizePriceInput } from './priceEditorUtils';

const PRICE_FIELDS = new Set( [ 'regular_price', 'sale_price' ] );

/**
 * @param {string} field Column field id.
 * @param {*}      value Raw cell value.
 * @return {string}
 */
export function normalizePendingFieldValue( field, value ) {
	if ( PRICE_FIELDS.has( field ) ) {
		return normalizePriceInput( value );
	}
	return String( value ?? '' ).trim();
}

/**
 * @param {Object} edit Edit payload from the grid.
 * @return {Object|null}
 */
export function buildPendingChange( edit ) {
	const variationId = Number( edit?.variation_id || 0 );
	const field = String( edit?.field || '' );
	if ( variationId <= 0 || ! field || field.startsWith( 'attribute_' ) ) {
		return null;
	}

	const oldValue = normalizePendingFieldValue( field, edit.oldValue );
	const newValue = normalizePendingFieldValue( field, edit.newValue );

	if ( oldValue === newValue ) {
		return null;
	}

	return {
		variation_id: variationId,
		field,
		old_value: oldValue,
		new_value: newValue,
	};
}

/**
 * @param {Array<Object>} current Existing pending changes.
 * @param {Object}        change New or updated change row.
 * @return {Array<Object>}
 */
export function upsertPendingChange( current, change ) {
	const index = current.findIndex(
		( item ) =>
			item.variation_id === change.variation_id &&
			item.field === change.field
	);
	if ( index >= 0 ) {
		const copy = [ ...current ];
		copy[ index ] = {
			...copy[ index ],
			new_value: change.new_value,
		};
		return copy;
	}
	return [ ...current, change ];
}

/**
 * @param {Array<Object>} current Existing pending changes.
 * @param {Object}        edit    Raw edit payload.
 * @return {Array<Object>}
 */
export function applyPendingEdit( current, edit ) {
	const change = buildPendingChange( edit );
	if ( ! change ) {
		return current;
	}
	return upsertPendingChange( current, change );
}
