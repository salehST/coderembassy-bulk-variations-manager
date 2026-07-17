/**
 * Bulk Editor - AG Grid Community spreadsheet.
 */
import {
	Fragment,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { AgGridReact } from 'ag-grid-react';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import { Icon as WPIcon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	arrowDown,
	arrowUp,
	check,
	closeSmall,
	tag,
	trash,
} from '@wordpress/icons';
import { variations } from '../../../api/endpoints';
import { navigateTo } from '../../../navigation';
import ProductPicker from './ProductPicker';
import FilterBar from './FilterBar';
import ColumnPicker from './ColumnPicker';
import UndoRedo, { useUndoRedo } from './UndoRedo';
import ApplyBar from './ApplyBar';
import CardIntro from './CardIntro';
import Button from '../../shared/Button';
import {
	buildColumnDefs,
	COLUMN_CATALOG,
	defaultVisibleColumnIds,
} from './columns';
import {
	mergeVariationListBatches,
	parseVariationsListResponse,
} from './columnUtils';
import { applyPriceFormula } from './formulaParser';
import {
	normalizeSaleDateValue,
	saleDateValuesEquivalent,
} from '../../../utils/saleDateUtils';
import { normalizePriceInput } from './priceEditorUtils';
import { applyPendingEdit } from './pendingChangeUtils';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-quartz.css';

ModuleRegistry.registerModules( [ AllCommunityModule ] );

const SALE_DATE_FIELDS = new Set( [ 'sale_from', 'sale_to' ] );
const PRICE_FIELDS = new Set( [ 'regular_price', 'sale_price' ] );
const EDITABLE_FIELDS = [
	'sku',
	'regular_price',
	'sale_price',
	'sale_from',
	'sale_to',
	'stock_quantity',
	'stock_status',
	'status',
];

/**
 * @param {string} field Column field id.
 * @param {*}      oldValue Previous cell value.
 * @param {*}      newValue New cell value.
 * @return {boolean}
 */
const cellValuesEquivalent = ( field, oldValue, newValue ) => {
	if ( SALE_DATE_FIELDS.has( field ) ) {
		return saleDateValuesEquivalent( oldValue, newValue );
	}
	if ( PRICE_FIELDS.has( field ) ) {
		return (
			normalizePriceInput( oldValue ) === normalizePriceInput( newValue )
		);
	}
	return oldValue === newValue;
};

const normalizeRows = ( rows ) =>
	rows.map( ( row ) => {
		const normalized = {
			...row,
			variation_id: row.variation_id || row.id,
			sku: String( row.sku ?? row._sku ?? '' ).trim(),
			regular_price: row.regular_price ?? '',
			sale_price: row.sale_price ?? '',
			sale_from: normalizeSaleDateValue( row.sale_from ?? '' ),
			sale_to: normalizeSaleDateValue( row.sale_to ?? '' ),
			stock_quantity: row.stock_quantity ?? '',
			stock_status: row.stock_status || 'instock',
			status: row.status || 'publish',
			image_url: row.image_url || '',
			product_id: row.product_id,
		};

		Object.keys( row ).forEach( ( key ) => {
			if ( key.startsWith( 'attribute_' ) ) {
				normalized[ key ] = String( row[ key ] ?? '' ).trim();
			}
		} );

		return normalized;
	} );

const filterRows = ( rows, filters ) =>
	rows.filter( ( row ) => {
		if ( filters.sku ) {
			const needle = filters.sku.toLowerCase();
			if (
				! String( row.sku || '' )
					.toLowerCase()
					.includes( needle )
			) {
				return false;
			}
		}
		if ( filters.status && row.status !== filters.status ) {
			return false;
		}
		if (
			filters.stock_status &&
			row.stock_status !== filters.stock_status
		) {
			return false;
		}
		return true;
	} );

export default function BulkEditor() {
	const gridRef = useRef( null );
	const originalRowsRef = useRef( new Map() );
	const draftPriceEditsRef = useRef( new Map() );
	const [ productIds, setProductIds ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ selectedRows, setSelectedRows ] = useState( [] );
	const [ filters, setFilters ] = useState( {
		sku: '',
		status: '',
		stock_status: '',
	} );
	const [ setPriceValue, setSetPriceValue ] = useState( '' );
	const [ formulaValue, setFormulaValue ] = useState( '' );
	const [ attributeColumns, setAttributeColumns ] = useState( [] );
	const [ visibleColumns, setVisibleColumns ] = useState(
		COLUMN_CATALOG.filter( ( col ) => col.default !== false ).map(
			( col ) => col.id
		)
	);
	const [ pendingChanges, setPendingChanges ] = useState( [] );
	const pendingChangesRef = useRef( [] );

	const syncPendingChanges = useCallback( ( updater ) => {
		setPendingChanges( ( current ) => {
			const next =
				typeof updater === 'function' ? updater( current ) : updater;
			pendingChangesRef.current = next;
			return next;
		} );
	}, [] );

	const { rows, setRows, pushEdit, undo, redo, canUndo, canRedo } =
		useUndoRedo( [] );

	const columnDefs = useMemo(
		() => buildColumnDefs( attributeColumns, visibleColumns ),
		[ attributeColumns, visibleColumns ]
	);
	const filteredRows = useMemo(
		() => filterRows( rows, filters ),
		[ rows, filters ]
	);
	const hasSelection = selectedRows.length > 0;
	const selectionCount = selectedRows.length;

	const actionsHeading = useMemo( () => {
		if ( selectionCount === 0 ) {
			return __(
				'Select variations to edit',
				'coderembassy-bulk-variations-manager'
			);
		}
		if ( selectionCount === 1 ) {
			return __(
				'1 variation selected',
				'coderembassy-bulk-variations-manager'
			);
		}
		return `${ selectionCount } ${ __(
			'variations selected',
			'coderembassy-bulk-variations-manager'
		) }`;
	}, [ selectionCount ] );

	const actionsHelp = useMemo( () => {
		if ( selectionCount === 0 ) {
			return __(
				'Tick one or more rows in the table to enable bulk actions.',
				'coderembassy-bulk-variations-manager'
			);
		}
		return __(
			'Changes are staged first. Use Preview & Approve to save them.',
			'coderembassy-bulk-variations-manager'
		);
	}, [ selectionCount ] );

	const loadVariations = useCallback(
		async ( ids ) => {
			if ( ! ids.length ) {
				setRows( [] );
				setAttributeColumns( [] );
				return;
			}
			setLoading( true );
			try {
				const batches = await Promise.all(
					ids.map( async ( productId ) => {
						const response = await variations.list( {
							product_id: productId,
						} );
						return parseVariationsListResponse( response );
					} )
				);
				const merged = mergeVariationListBatches( batches );
				const normalizedRows = normalizeRows( merged.variations );
				setAttributeColumns( merged.attributeColumns );
				setVisibleColumns(
					defaultVisibleColumnIds( merged.attributeColumns )
				);
				originalRowsRef.current = new Map(
					normalizedRows.map( ( row ) => [
						Number( row.variation_id ),
						{ ...row },
					] )
				);
				draftPriceEditsRef.current = new Map();
				setRows( normalizedRows );
				syncPendingChanges( [] );
			} catch ( err ) {
				void err;
				setRows( [] );
				setAttributeColumns( [] );
			} finally {
				setLoading( false );
			}
		},
		[ setRows, syncPendingChanges ]
	);

	useEffect( () => {
		loadVariations( productIds );
	}, [ productIds, loadVariations ] );

	const captureCellEdit = useCallback(
		( event ) => {
			const field = event.colDef?.field;
			if ( ! field || field.startsWith( 'attribute_' ) ) {
				return;
			}

			let oldValue =
				event.oldValue !== undefined && event.oldValue !== null
					? event.oldValue
					: event.data?.[ field ];
			let newValue =
				event.newValue !== undefined && event.newValue !== null
					? event.newValue
					: event.data?.[ field ];

			if ( SALE_DATE_FIELDS.has( field ) ) {
				oldValue = normalizeSaleDateValue( oldValue );
				newValue = normalizeSaleDateValue( newValue );
			} else if ( PRICE_FIELDS.has( field ) ) {
				oldValue = normalizePriceInput( oldValue );
				newValue = normalizePriceInput( newValue );
			}

			if ( cellValuesEquivalent( field, oldValue, newValue ) ) {
				return;
			}

			event.data[ field ] = newValue;

			const edit = {
				variation_id: event.data.variation_id,
				field,
				oldValue,
				newValue,
			};

			pushEdit( edit );
			syncPendingChanges( ( current ) =>
				applyPendingEdit( current, edit )
			);

			event.api?.refreshCells( {
				rowNodes: event.node ? [ event.node ] : undefined,
				columns: [ field ],
				force: true,
			} );
		},
		[ pushEdit, syncPendingChanges ]
	);

	const recordChange = useCallback(
		( edit ) => {
			pushEdit( edit );
			syncPendingChanges( ( current ) =>
				applyPendingEdit( current, edit )
			);
		},
		[ pushEdit, syncPendingChanges ]
	);

	const normalizeFieldValue = useCallback( ( field, value ) => {
		if ( SALE_DATE_FIELDS.has( field ) ) {
			return normalizeSaleDateValue( value );
		}
		if ( PRICE_FIELDS.has( field ) ) {
			return normalizePriceInput( value );
		}
		return String( value ?? '' ).trim();
	}, [] );

	const collectGridChanges = useCallback( () => {
		const api = gridRef.current?.api;
		const changes = [];
		if ( ! api ) {
			return pendingChangesRef.current;
		}

		api.forEachNode( ( node ) => {
			const row = node.data || {};
			const variationId = Number( row.variation_id || 0 );
			if ( variationId <= 0 ) {
				return;
			}

			const original = originalRowsRef.current.get( variationId ) || {};
			EDITABLE_FIELDS.forEach( ( field ) => {
				const oldValue = normalizeFieldValue(
					field,
					original[ field ]
				);
				const newValue = normalizeFieldValue( field, row[ field ] );
				if ( oldValue === newValue ) {
					return;
				}
				changes.push( {
					variation_id: variationId,
					field,
					old_value: oldValue,
					new_value: newValue,
				} );
			} );
		} );

		return changes;
	}, [ normalizeFieldValue ] );

	const flushGridEdits = useCallback( async () => {
		const api = gridRef.current?.api;
		if ( ! api ) {
			return pendingChangesRef.current;
		}

		// Capture any in-flight editor value before AG Grid tears down the editor.
		const liveDrafts = ( api.getEditingCells?.() || [] )
			.map( ( cell ) => {
				const rowNode = api.getDisplayedRowAtIndex( cell.rowIndex );
				const field =
					typeof cell.column === 'string'
						? cell.column
						: cell.column?.getColId?.();
				if ( ! rowNode?.data || ! field ) {
					return null;
				}
				let value = rowNode.data?.[ field ];
				if (
					( field === 'regular_price' || field === 'sale_price' ) &&
					typeof document !== 'undefined'
				) {
					const editor = document.querySelector( '.bv-price-editor' );
					if ( editor && 'value' in editor ) {
						value = normalizePriceInput( editor.value );
					}
				}
				return { rowNode, field, value };
			} )
			.filter( Boolean );

		api.stopEditing( false );

		// Allow AG Grid to commit editor value into rowData.
		await new Promise( ( resolve ) => {
			window.setTimeout( resolve, 0 );
		} );

		liveDrafts.forEach( ( draft ) => {
			if ( ! draft ) {
				return;
			}
			const next = normalizeFieldValue( draft.field, draft.value );
			const current = normalizeFieldValue(
				draft.field,
				draft.rowNode.data?.[ draft.field ]
			);
			if ( next === current ) {
				return;
			}
			draft.rowNode.data[ draft.field ] = next;
			api.refreshCells( {
				rowNodes: [ draft.rowNode ],
				columns: [ draft.field ],
				force: true,
			} );
		} );

		draftPriceEditsRef.current.forEach( ( value, key ) => {
			const [ variationIdRaw, field ] = String( key ).split( ':' );
			const variationId = Number( variationIdRaw || 0 );
			if ( variationId <= 0 || ! field ) {
				return;
			}

			api.forEachNode( ( rowNode ) => {
				if (
					Number( rowNode.data?.variation_id || 0 ) !== variationId
				) {
					return;
				}
				const next = normalizeFieldValue( field, value );
				const current = normalizeFieldValue(
					field,
					rowNode.data?.[ field ]
				);
				if ( next === current ) {
					return;
				}
				rowNode.data[ field ] = next;
				api.refreshCells( {
					rowNodes: [ rowNode ],
					columns: [ field ],
					force: true,
				} );
			} );
		} );

		const changes = collectGridChanges();
		pendingChangesRef.current = changes;
		setPendingChanges( changes );
		draftPriceEditsRef.current = new Map();
		return changes;
	}, [ collectGridChanges, normalizeFieldValue ] );

	const applySelection = useCallback(
		( updateFactory ) => {
			selectedRows.forEach( ( row, index ) => {
				const updates =
					typeof updateFactory === 'function'
						? updateFactory( row, index )
						: updateFactory;
				Object.entries( updates || {} ).forEach(
					( [ field, newValue ] ) => {
						recordChange( {
							variation_id: row.variation_id,
							field,
							oldValue: row[ field ],
							newValue,
						} );
						row[ field ] = newValue;
					}
				);
			} );
			gridRef.current?.api?.refreshCells( { force: true } );
		},
		[ selectedRows, recordChange ]
	);

	const onActionClick = useCallback(
		( action ) => {
			if ( ! hasSelection ) {
				return;
			}
			if ( action === 'increase' ) {
				applySelection( ( row ) => ( {
					regular_price: applyPriceFormula(
						'+10%',
						parseFloat( row.regular_price ) || 0
					),
				} ) );
				return;
			}
			if ( action === 'decrease' ) {
				applySelection( ( row ) => ( {
					regular_price: applyPriceFormula(
						'-10%',
						parseFloat( row.regular_price ) || 0
					),
				} ) );
				return;
			}
			if ( action === 'set_price' ) {
				if ( '' === setPriceValue ) {
					return;
				}
				applySelection( { regular_price: setPriceValue } );
				return;
			}
			if ( action === 'enable' ) {
				applySelection( { status: 'publish' } );
				return;
			}
			if ( action === 'disable' ) {
				applySelection( { status: 'private' } );
				return;
			}
			if ( action === 'delete' ) {
				applySelection( { status: 'trash' } );
			}
		},
		[ applySelection, hasSelection, setPriceValue ]
	);

	const bulkActionContext = useMemo(
		() => ( {
			hasSelection,
			selectedRows,
			applySelection,
			recordChange,
			gridRef,
		} ),
		[ hasSelection, selectedRows, applySelection, recordChange ]
	);

	const bulkActions = useMemo( () => {
		const defaults = [
			{
				id: 'increase',
				label: __(
					'Increase price %',
					'coderembassy-bulk-variations-manager'
				),
				icon: arrowUp,
				className: 'bv-action-btn bv-action-btn--blue',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'increase' ),
			},
			{
				id: 'decrease',
				label: __(
					'Decrease price %',
					'coderembassy-bulk-variations-manager'
				),
				icon: arrowDown,
				className: 'bv-action-btn bv-action-btn--blue',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'decrease' ),
			},
			{
				id: 'set_price',
				label: __(
					'Set price',
					'coderembassy-bulk-variations-manager'
				),
				icon: tag,
				className: 'bv-action-btn bv-action-btn--slate',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'set_price' ),
			},
			{
				id: 'enable',
				label: __( 'Enable', 'coderembassy-bulk-variations-manager' ),
				icon: check,
				className: 'bv-action-btn bv-action-btn--green',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'enable' ),
			},
			{
				id: 'disable',
				label: __( 'Disable', 'coderembassy-bulk-variations-manager' ),
				icon: closeSmall,
				className: 'bv-action-btn bv-action-btn--orange',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'disable' ),
			},
			{
				id: 'delete',
				label: __( 'Delete', 'coderembassy-bulk-variations-manager' ),
				icon: trash,
				variant: 'danger',
				className: 'bv-action-btn',
				disabled: ! hasSelection,
				onClick: () => onActionClick( 'delete' ),
			},
		];
		const filtered = applyFilters(
			'coderembassy_bvm_bulk_actions',
			defaults,
			bulkActionContext
		);
		return Array.isArray( filtered ) ? filtered : defaults;
	}, [ bulkActionContext, hasSelection, onActionClick ] );

	const renderBulkAction = useCallback(
		( action ) => {
			if ( typeof action.render === 'function' ) {
				return (
					<Fragment key={ action.id }>
						{ action.render( bulkActionContext ) }
					</Fragment>
				);
			}
			return (
				<Button
					key={ action.id }
					size="sm"
					variant={ action.variant }
					className={ action.className }
					disabled={ action.disabled }
					onClick={
						action.onClick || ( () => onActionClick( action.id ) )
					}
				>
					{ action.icon && (
						<WPIcon icon={ action.icon } size={ 16 } />
					) }
					{ action.label }
				</Button>
			);
		},
		[ bulkActionContext, onActionClick ]
	);

	const applyFormulaToSelection = useCallback( () => {
		if ( ! hasSelection || ! formulaValue.trim() ) {
			return;
		}
		applySelection( ( row ) => ( {
			regular_price: applyPriceFormula(
				formulaValue,
				parseFloat( row.regular_price ) || 0
			),
		} ) );
	}, [ applySelection, formulaValue, hasSelection ] );

	const handleRowAction = useCallback(
		( action, row ) => {
			if ( action === 'view_history' ) {
				navigateTo( 'jobs' );
				return;
			}
			if ( action === 'delete' ) {
				recordChange( {
					variation_id: row.variation_id,
					field: 'status',
					oldValue: row.status,
					newValue: 'trash',
				} );
				row.status = 'trash';
				gridRef.current?.api?.refreshCells( { force: true } );
			}
		},
		[ recordChange ]
	);

	const handleTrackedCellEdit = useCallback(
		( edit ) => {
			recordChange( edit );
		},
		[ recordChange ]
	);

	const onCellValueChanged = useCallback(
		( event ) => {
			captureCellEdit( event );
		},
		[ captureCellEdit ]
	);

	const onCellEditingStopped = useCallback(
		( event ) => {
			captureCellEdit( event );
		},
		[ captureCellEdit ]
	);

	const onSelectionChanged = useCallback( () => {
		const api = gridRef.current?.api;
		setSelectedRows( api ? api.getSelectedRows() : [] );
	}, [] );

	useEffect( () => {
		const onKeyDown = ( event ) => {
			const mod = event.metaKey || event.ctrlKey;
			if ( mod && event.key.toLowerCase() === 'z' ) {
				event.preventDefault();
				if ( event.shiftKey ) {
					redo();
				} else {
					undo();
				}
			}
			if ( mod && event.key.toLowerCase() === 'y' ) {
				event.preventDefault();
				redo();
			}
			if ( mod && event.key.toLowerCase() === 'a' ) {
				event.preventDefault();
				gridRef.current?.api?.selectAll();
			}
			if ( event.key === 'Escape' ) {
				gridRef.current?.api?.deselectAll();
			}
		};
		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ undo, redo ] );

	const defaultColDef = useMemo(
		() => ( {
			resizable: true,
			sortable: true,
			filter: false,
		} ),
		[]
	);

	const gridOptions = useMemo(
		() => ( {
			rowSelection: 'multiple',
			singleClickEdit: true,
			stopEditingWhenCellsLoseFocus: true,
			suppressColumnMoveAnimation: true,
			getRowId: ( params ) => String( params.data.variation_id ),
			context: {
				onRowAction: handleRowAction,
				onCellEdit: handleTrackedCellEdit,
				onPriceDraftChange: ( draft ) => {
					const variationId = Number( draft?.variation_id || 0 );
					const field = String( draft?.field || '' );
					if ( variationId <= 0 || ! field ) {
						return;
					}
					draftPriceEditsRef.current.set(
						`${ variationId }:${ field }`,
						String( draft?.value ?? '' )
					);
				},
			},
		} ),
		[ handleRowAction, handleTrackedCellEdit ]
	);

	const onGridReady = useCallback( ( params ) => {
		gridRef.current = params;
	}, [] );

	return (
		<div className="bv-bulk-editor">
			<ProductPicker
				productIds={ productIds }
				onChange={ setProductIds }
			/>

			<section className="bv-card bv-filter-card">
				<div className="bv-jobs__footer">
					<Button
						variant="default"
						onClick={ () => navigateTo( 'import' ) }
					>
						{ __(
							'Open CSV Import',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				</div>
			</section>

			<ColumnPicker
				productId={ productIds[ 0 ] || 0 }
				attributeColumns={ attributeColumns }
				onChange={ setVisibleColumns }
				actions={
					<UndoRedo
						canUndo={ canUndo }
						canRedo={ canRedo }
						onUndo={ undo }
						onRedo={ redo }
					/>
				}
			/>

			<section className="bv-card bv-filter-card">
				<FilterBar filters={ filters } onChange={ setFilters } />
			</section>

			<section
				className={ `bv-card bv-actions-card${
					hasSelection
						? ' bv-actions-card--active'
						: ' bv-actions-card--idle'
				}` }
			>
				<CardIntro title={ actionsHeading } help={ actionsHelp } />
				<div className="bv-action-row">
					{ bulkActions.slice( 0, 2 ).map( renderBulkAction ) }
					<input
						type="number"
						className="bv-product-picker__input bv-action-row__price"
						placeholder={ __(
							'Set price',
							'coderembassy-bulk-variations-manager'
						) }
						value={ setPriceValue }
						disabled={ ! hasSelection }
						aria-disabled={ ! hasSelection }
						onChange={ ( event ) =>
							setSetPriceValue( event.target.value )
						}
					/>
					{ bulkActions.slice( 2 ).map( renderBulkAction ) }
				</div>
				<div className="bv-formula-row">
					<div className="bv-formula-row__field">
						<input
							type="text"
							className="bv-product-picker__input bv-formula-row__input"
							placeholder="=PRICE*1.15 or +15%"
							value={ formulaValue }
							disabled={ ! hasSelection }
							aria-disabled={ ! hasSelection }
							onChange={ ( event ) =>
								setFormulaValue( event.target.value )
							}
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' ) {
									applyFormulaToSelection();
								}
							} }
						/>
						<p className="bv-card__help bv-formula-row__help">
							{ __(
								'Examples: +15%, -10%, =PRICE*1.15',
								'coderembassy-bulk-variations-manager'
							) }
						</p>
					</div>
					<Button
						variant="primary"
						className="bv-formula-row__button"
						disabled={ ! hasSelection || ! formulaValue.trim() }
						onClick={ applyFormulaToSelection }
					>
						{ __(
							'Apply formula',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				</div>
			</section>

			<div
				className="ag-theme-quartz bv-bulk-editor__grid"
				aria-busy={ loading }
			>
				<AgGridReact
					ref={ gridRef }
					rowData={ filteredRows }
					columnDefs={ columnDefs }
					defaultColDef={ defaultColDef }
					gridOptions={ gridOptions }
					rowSelection="multiple"
					onGridReady={ onGridReady }
					onCellValueChanged={ onCellValueChanged }
					onCellEditingStopped={ onCellEditingStopped }
					onSelectionChanged={ onSelectionChanged }
					domLayout="normal"
				/>
			</div>

			<ApplyBar
				pendingChanges={ pendingChanges }
				productId={ productIds[ 0 ] }
				onBeforePreview={ flushGridEdits }
				onDiscard={ () => {
					syncPendingChanges( [] );
					draftPriceEditsRef.current = new Map();
					loadVariations( productIds );
				} }
			/>
		</div>
	);
}
