/**
 * AG Grid column definitions for the bulk editor.
 */
import { __ } from '@wordpress/i18n';
import StatusEditor from './cellEditors/StatusEditor';
import ThumbRenderer from './cellRenderers/ThumbRenderer';
import SkuCellRenderer from './cellRenderers/SkuCellRenderer';
import SaleDateCellRenderer from './cellRenderers/SaleDateCellRenderer';
import ActionsRenderer from './cellRenderers/ActionsRenderer';
import { normalizeSaleDateValue } from '../../../utils/saleDateUtils';
import { commitPriceFromEditor, normalizePriceInput } from './priceEditorUtils';

export const COLUMN_CATALOG = [
	{
		id: 'variation_id',
		label: __( 'Variation ID', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'sku',
		label: __( 'SKU', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'image_url',
		label: __( 'Image', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'regular_price',
		label: __( 'Regular price', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'sale_price',
		label: __( 'Sale price', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'sale_from',
		label: __( 'Sale from', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'sale_to',
		label: __( 'Sale to', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'stock_quantity',
		label: __( 'Stock qty', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'stock_status',
		label: __( 'Stock status', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
	{
		id: 'status',
		label: __( 'Status', 'coderembassy-bulk-variations-manager' ),
		default: true,
	},
];

const DEFAULT_DATA_MIN_WIDTH = 110;

const BASE_COLUMNS_START = [
	{
		field: 'variation_id',
		headerName: __(
			'Variation ID',
			'coderembassy-bulk-variations-manager'
		),
		editable: false,
		pinned: 'left',
		checkboxSelection: true,
		headerCheckboxSelection: true,
	},
	{
		field: 'sku',
		headerName: __( 'SKU', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 140,
		valueGetter: ( params ) =>
			String( params.data?.sku ?? params.data?._sku ?? '' ).trim(),
		valueSetter: trackedValueSetter( 'sku' ),
		cellRenderer: SkuCellRenderer,
	},
	{
		field: 'image_url',
		headerName: __( 'Image', 'coderembassy-bulk-variations-manager' ),
		editable: false,
		cellRenderer: ThumbRenderer,
	},
];

const BASE_COLUMNS_END = [
	{
		field: 'regular_price',
		headerName: __(
			'Regular price',
			'coderembassy-bulk-variations-manager'
		),
		editable: true,
		minWidth: 120,
		cellEditor: 'agTextCellEditor',
		valueParser: createPriceValueParser( 'regular_price' ),
		valueSetter: trackedValueSetter( 'regular_price', normalizePriceInput ),
		valueFormatter: currencyFormatter,
	},
	{
		field: 'sale_price',
		headerName: __( 'Sale price', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 120,
		cellEditor: 'agTextCellEditor',
		valueParser: createPriceValueParser( 'sale_price' ),
		valueSetter: trackedValueSetter( 'sale_price', normalizePriceInput ),
		valueFormatter: currencyFormatter,
	},
	{
		field: 'sale_from',
		headerName: __( 'Sale from', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 120,
		cellDataType: 'dateString',
		cellEditor: 'agDateStringCellEditor',
		cellRenderer: SaleDateCellRenderer,
		valueParser: saleDateValueParser,
		valueSetter: trackedValueSetter( 'sale_from', normalizeSaleDateValue ),
	},
	{
		field: 'sale_to',
		headerName: __( 'Sale to', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 120,
		cellDataType: 'dateString',
		cellEditor: 'agDateStringCellEditor',
		cellRenderer: SaleDateCellRenderer,
		valueParser: saleDateValueParser,
		valueSetter: trackedValueSetter( 'sale_to', normalizeSaleDateValue ),
	},
	{
		field: 'stock_quantity',
		headerName: __( 'Stock qty', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 100,
		valueSetter: trackedValueSetter( 'stock_quantity' ),
	},
	{
		field: 'stock_status',
		headerName: __(
			'Stock status',
			'coderembassy-bulk-variations-manager'
		),
		editable: true,
		minWidth: 130,
		cellEditor: 'agSelectCellEditor',
		cellEditorParams: {
			values: [ 'instock', 'outofstock', 'onbackorder' ],
		},
		valueSetter: trackedValueSetter( 'stock_status' ),
		cellRenderer: pillRenderer( 'stock' ),
	},
	{
		field: 'status',
		headerName: __( 'Status', 'coderembassy-bulk-variations-manager' ),
		editable: true,
		minWidth: 110,
		cellEditor: StatusEditor,
		valueSetter: trackedValueSetter( 'status' ),
		cellRenderer: pillRenderer( 'status' ),
	},
	{
		field: '__actions',
		headerName: '',
		pinned: 'right',
		editable: false,
		sortable: false,
		filter: false,
		resizable: false,
		suppressMovable: true,
		cellRenderer: ActionsRenderer,
	},
];

/**
 * @param {Array<{ id?: string, field?: string, label?: string, default?: boolean }>} attributeColumns Dynamic attribute columns.
 * @return {Array<{ id: string, label: string, default: boolean }>}
 */
export function mergeColumnCatalog( attributeColumns = [] ) {
	const baseIds = new Set( COLUMN_CATALOG.map( ( column ) => column.id ) );
	const dynamic = ( attributeColumns || [] )
		.map( ( column ) => ( {
			id: String( column.id || column.field || '' ),
			label: String( column.label || column.id || column.field || '' ),
			default: column.default !== false,
		} ) )
		.filter( ( column ) => column.id && ! baseIds.has( column.id ) );

	return [ ...COLUMN_CATALOG, ...dynamic ];
}

/**
 * Default visible column ids including dynamic attributes after Image.
 *
 * @param {Array<{ id?: string, field?: string }>} attributeColumns Attribute columns.
 * @return {string[]}
 */
export function defaultVisibleColumnIds( attributeColumns = [] ) {
	const base = COLUMN_CATALOG.filter( ( col ) => col.default !== false ).map(
		( col ) => col.id
	);
	const imageIndex = base.indexOf( 'image_url' );
	const attrIds = ( attributeColumns || [] )
		.map( ( column ) => String( column.id || column.field || '' ) )
		.filter( Boolean );

	if ( imageIndex < 0 || ! attrIds.length ) {
		return [ ...base, ...attrIds ];
	}

	const merged = [ ...base ];
	attrIds.forEach( ( id, index ) => {
		if ( ! merged.includes( id ) ) {
			merged.splice( imageIndex + 1 + index, 0, id );
		}
	} );

	return merged;
}

/**
 * @param {Array<{ slug?: string, label?: string }>} options Attribute options.
 * @return {Map<string, string>}
 */
function buildSlugLabelMap( options ) {
	const map = new Map();
	( options || [] ).forEach( ( option ) => {
		const slug = String( option?.slug ?? '' ).trim();
		if ( ! slug ) {
			return;
		}
		map.set( slug, String( option?.label || slug ).trim() || slug );
		map.set(
			slug.toLowerCase(),
			String( option?.label || slug ).trim() || slug
		);
	} );
	return map;
}

/**
 * @param {string} raw Stored attribute slug/value.
 * @return {string}
 */
function humanizeAttributeValue( raw ) {
	const value = String( raw || '' ).trim();
	if ( ! value ) {
		return '';
	}
	if ( value.length <= 3 && value === value.toLowerCase() ) {
		return value.toUpperCase();
	}
	return value
		.split( /[-_\s]+/ )
		.filter( Boolean )
		.map(
			( part ) =>
				part.charAt( 0 ).toUpperCase() + part.slice( 1 ).toLowerCase()
		)
		.join( ' ' );
}

/**
 * @param {Object} column Attribute column metadata.
 * @return {Object} AG Grid column definition.
 */
function buildAttributeColumnDef( column ) {
	const field = String( column.field || column.id || '' );
	const slugToLabel = buildSlugLabelMap( column.options );

	return applyColumnLayout( {
		field,
		headerName: String( column.label || field ),
		editable: false,
		minWidth: 110,
		cellClass: 'bv-grid-attribute-cell',
		valueGetter: ( params ) => {
			const raw = String( params.data?.[ field ] ?? '' ).trim();
			if ( ! raw ) {
				return '';
			}
			return (
				slugToLabel.get( raw ) ||
				slugToLabel.get( raw.toLowerCase() ) ||
				humanizeAttributeValue( raw )
			);
		},
	} );
}

/**
 * Apply fixed widths for pinned/fixed columns; flex for scrollable data columns.
 *
 * @param {Object} column AG Grid column definition.
 * @return {Object}
 */
function applyColumnLayout( column ) {
	const field = column.field;

	if ( field === 'variation_id' ) {
		return {
			...column,
			width: 150,
			minWidth: 150,
			maxWidth: 170,
			suppressSizeToFit: true,
			lockPinned: true,
			suppressMovable: true,
		};
	}

	if ( field === '__actions' ) {
		return {
			...column,
			width: 52,
			minWidth: 52,
			maxWidth: 52,
			suppressSizeToFit: true,
			lockPinned: true,
			headerClass: 'bv-grid-actions-header',
			cellClass: 'bv-grid-actions-cell',
		};
	}

	if ( field === 'image_url' ) {
		return {
			...column,
			width: 86,
			minWidth: 86,
			maxWidth: 100,
			suppressSizeToFit: true,
			flex: 0,
		};
	}

	if ( field && field.startsWith( 'attribute_' ) ) {
		return {
			...column,
			flex: 0,
			minWidth: column.minWidth || 110,
			maxWidth: 260,
			suppressSizeToFit: true,
		};
	}

	return {
		...column,
		flex: 1,
		minWidth: column.minWidth || DEFAULT_DATA_MIN_WIDTH,
	};
}

function decodeCurrencySymbol( symbol ) {
	const value = String( symbol || '$' ).replace( /&nbsp;/g, ' ' );
	if ( typeof document === 'undefined' ) {
		return value;
	}
	const textarea = document.createElement( 'textarea' );
	textarea.innerHTML = value;
	return textarea.value;
}

function getCurrencyConfig() {
	const fallback = {
		symbol: '$',
		position: 'left',
		decimals: 2,
	};
	const admin =
		typeof window !== 'undefined' ? window.BulkVariationsAdmin : null;
	const currency = admin?.currency;

	if ( typeof currency === 'string' && currency ) {
		return {
			...fallback,
			symbol: decodeCurrencySymbol( currency ),
		};
	}

	if ( currency && typeof currency === 'object' ) {
		const decimals = parseInt( currency.decimals, 10 );
		return {
			...fallback,
			...currency,
			symbol: decodeCurrencySymbol( currency.symbol ),
			decimals: Number.isNaN( decimals ) ? fallback.decimals : decimals,
		};
	}

	return fallback;
}

/**
 * @param {import('ag-grid-community').ValueParserParams} params Parser params.
 * @return {string}
 */
function saleDateValueParser( params ) {
	return normalizeSaleDateValue( params.newValue );
}

function trackedValueSetter( field, normalize = defaultValueNormalizer ) {
	return ( params ) => {
		const oldValue = normalize( params.data?.[ field ] );
		const newValue = normalize( params.newValue );

		params.data[ field ] = newValue;

		if ( oldValue === newValue ) {
			return false;
		}

		params.context?.onCellEdit?.( {
			variation_id: params.data?.variation_id,
			field,
			oldValue,
			newValue,
		} );

		return true;
	};
}

function createPriceValueParser( field ) {
	return ( params ) => {
		const currentValue = normalizePriceInput( params.data?.[ field ] );
		return commitPriceFromEditor( params.newValue, currentValue );
	};
}

function defaultValueNormalizer( value ) {
	return String( value ?? '' ).trim();
}

function currencyFormatter( params ) {
	const raw = params.value;
	if ( raw === null || raw === undefined || raw === '' ) {
		return '';
	}
	const numeric = parseFloat( raw );
	if ( Number.isNaN( numeric ) ) {
		return String( raw );
	}

	const { symbol, position, decimals } = getCurrencyConfig();
	const amount = numeric.toFixed( decimals );

	if ( position === 'right' ) {
		return `${ amount }${ symbol }`;
	}
	if ( position === 'right_space' ) {
		return `${ amount } ${ symbol }`;
	}
	if ( position === 'left_space' ) {
		return `${ symbol } ${ amount }`;
	}
	return `${ symbol }${ amount }`;
}

function pillRenderer( type ) {
	return ( params ) => {
		const value = String( params.value || '' );
		const cls = `bv-pill bv-pill--${ type } bv-pill--${ value || 'empty' }`;
		return <span className={ cls }>{ value || '-' }</span>;
	};
}

/**
 * @param {Array<{ id?: string, field?: string, label?: string, options?: Array<{ slug?: string, label?: string }> }>} attributeColumns Parent attribute metadata.
 * @param {string[]} visibleColumnIds Visible column ids from picker.
 * @return {Array<object>}
 */
export function buildColumnDefs(
	attributeColumns = [],
	visibleColumnIds = []
) {
	const alwaysOn = [ 'variation_id', '__actions' ];
	const attributeDefs = ( attributeColumns || [] ).map(
		buildAttributeColumnDef
	);

	const columns = [
		...BASE_COLUMNS_START.map( applyColumnLayout ),
		...attributeDefs,
		...BASE_COLUMNS_END.map( applyColumnLayout ),
	];

	if ( ! visibleColumnIds.length ) {
		return columns;
	}

	return columns.filter( ( column ) => {
		if ( alwaysOn.includes( column.field ) ) {
			return true;
		}
		return visibleColumnIds.includes( column.field );
	} );
}
