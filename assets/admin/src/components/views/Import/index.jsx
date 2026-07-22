/**
 * Free CSV import preview + approve flow.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { imports, jobs, products, settings } from '../../../api/endpoints';
import { STORE_NAME } from '../../../store';
import { navigateTo } from '../../../navigation';
import Button from '../../shared/Button';
import CardIntro from '../BulkEditor/CardIntro';
import EmptyState from '../../shared/EmptyState';

const SAMPLE_COLUMNS = [
	{
		key: 'product_id',
		label: __( 'Product ID', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'variation_id',
		label: __( 'Variation ID', 'coderembassy-bulk-variations-manager' ),
	},
	{ key: 'sku', label: __( 'SKU', 'coderembassy-bulk-variations-manager' ) },
	{
		key: 'regular_price',
		label: __( 'Regular price', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'sale_price',
		label: __( 'Sale price', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'sale_from',
		label: __( 'Sale from', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'sale_to',
		label: __( 'Sale to', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'stock_quantity',
		label: __( 'Stock qty', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'stock_status',
		label: __( 'Stock status', 'coderembassy-bulk-variations-manager' ),
	},
	{
		key: 'status',
		label: __( 'Status', 'coderembassy-bulk-variations-manager' ),
	},
];

/**
 * @param {string} key CSV/import column key.
 * @return {string} Human-readable column label.
 */
const formatImporterColumnLabel = ( key ) =>
	String( key || '' )
		.replace( /^attribute_/, 'Attribute: ' )
		.replace( /^pa_/, '' )
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( letter ) => letter.toUpperCase() );

/**
 * @param {Object|Array|null} issues API errors/warnings map or list.
 * @return {Array<{ row: number, message: string }>} Row/issue pairs for display.
 */
const normalizeRowIssues = ( issues ) => {
	if ( ! issues ) {
		return [];
	}
	if ( Array.isArray( issues ) ) {
		return issues.map( ( entry, index ) => {
			if ( typeof entry === 'string' ) {
				return { row: index + 1, message: entry };
			}
			if ( entry && typeof entry === 'object' ) {
				const messages = [
					entry.message,
					entry.error,
					...( Array.isArray( entry.issues ) ? entry.issues : [] ),
					...( Array.isArray( entry.warnings )
						? entry.warnings
						: [] ),
				].filter( Boolean );

				return {
					row: Number(
						entry.row ?? entry.line ?? entry._row_num ?? index + 1
					),
					message:
						messages.join( ' ' ) ||
						__(
							'This row has a validation problem.',
							'coderembassy-bulk-variations-manager'
						),
				};
			}
			return { row: index + 1, message: String( entry ) };
		} );
	}
	return Object.entries( issues ).map( ( [ row, message ] ) => ( {
		row: Number( row ),
		message:
			String( message ) ||
			__(
				'This row has a validation problem.',
				'coderembassy-bulk-variations-manager'
			),
	} ) );
};

/**
 * @param {Object} preview API preview payload.
 * @return {Array<{ key: string, label: string, value: number, tone: string }>} Summary stat cards.
 */
const buildSummaryCards = ( preview ) => [
	{
		key: 'total',
		label: __( 'Total rows', 'coderembassy-bulk-variations-manager' ),
		value: preview.total_rows || 0,
		tone: 'neutral',
	},
	{
		key: 'valid',
		label: __( 'Valid rows', 'coderembassy-bulk-variations-manager' ),
		value: preview.valid_count || 0,
		tone: 'ok',
	},
	{
		key: 'invalid',
		label: __( 'Invalid rows', 'coderembassy-bulk-variations-manager' ),
		value: preview.invalid_count || 0,
		tone: preview.invalid_count > 0 ? 'warn' : 'neutral',
	},
	{
		key: 'warnings',
		label: __( 'Warnings', 'coderembassy-bulk-variations-manager' ),
		value: preview.warning_count || 0,
		tone: preview.warning_count > 0 ? 'warn' : 'neutral',
	},
];

/**
 * @param {unknown} value Cell value.
 * @return {string} Display string for a table cell.
 */
const formatCell = ( value ) => {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}
	return String( value );
};

/**
 * @param {Array<{ slug: string, label: string }>} options Attribute options.
 * @return {string} Compact allowed values label.
 */
const formatAllowedOptions = ( options ) => {
	if ( ! Array.isArray( options ) || options.length === 0 ) {
		return '—';
	}
	return options
		.map( ( option ) => {
			const slug = String( option?.slug ?? '' );
			const label = String( option?.label ?? slug );
			if ( ! slug ) {
				return label;
			}
			return label === slug ? slug : `${ label } (${ slug })`;
		} )
		.join( ', ' );
};

export default function ImportView() {
	const { pushToast } = useDispatch( STORE_NAME );
	const [ file, setFile ] = useState( null );
	const [ defaultProductId, setDefaultProductId ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ attributeGuide, setAttributeGuide ] = useState( null );
	const [ attributeLoading, setAttributeLoading ] = useState( false );
	const [ attributeError, setAttributeError ] = useState( '' );
	const [ templateDownloading, setTemplateDownloading ] = useState( false );
	const productIdTouched = useRef( false );

	const canPreview = !! file && ! loading;
	const canApprove =
		preview &&
		Array.isArray( preview.valid_rows ) &&
		preview.valid_rows.length > 0 &&
		! submitting;

	const summaryCards = useMemo(
		() => ( preview ? buildSummaryCards( preview ) : [] ),
		[ preview ]
	);

	const invalidRows = useMemo(
		() => normalizeRowIssues( preview?.errors ),
		[ preview ]
	);

	const warningRows = useMemo(
		() => normalizeRowIssues( preview?.warnings ),
		[ preview ]
	);

	const sampleRows = useMemo(
		() =>
			Array.isArray( preview?.sample_rows ) ? preview.sample_rows : [],
		[ preview ]
	);

	const sampleColumns = useMemo( () => {
		if ( sampleRows.length === 0 ) {
			return SAMPLE_COLUMNS;
		}

		const known = new Set( SAMPLE_COLUMNS.map( ( column ) => column.key ) );
		const extraColumns = [];
		sampleRows.forEach( ( row ) => {
			Object.keys( row || {} ).forEach( ( key ) => {
				if ( known.has( key ) ) {
					return;
				}
				known.add( key );
				extraColumns.push( {
					key,
					label: formatImporterColumnLabel( key ),
				} );
			} );
		} );

		return [ ...SAMPLE_COLUMNS, ...extraColumns ];
	}, [ sampleRows ] );

	const previewStatus = useMemo( () => {
		if ( ! preview ) {
			return null;
		}

		const validCount = Number( preview.valid_count || 0 );
		const invalidCount = Number( preview.invalid_count || 0 );

		if ( validCount === 0 && invalidCount > 0 ) {
			return {
				tone: 'error',
				title: __(
					'Fix the CSV before previewing',
					'coderembassy-bulk-variations-manager'
				),
				message: sprintf(
					/* translators: %d: invalid row count */
					__(
						'%d row(s) cannot be imported yet. Fix the row(s) below, then validate again.',
						'coderembassy-bulk-variations-manager'
					),
					invalidCount
				),
			};
		}

		if ( validCount > 0 && invalidCount > 0 ) {
			return {
				tone: 'warning',
				title: __(
					'Some rows are ready',
					'coderembassy-bulk-variations-manager'
				),
				message: sprintf(
					/* translators: 1: valid row count, 2: invalid row count */
					__(
						'%1$d valid row(s) can continue to Preview & Approve. %2$d invalid row(s) will be skipped until fixed.',
						'coderembassy-bulk-variations-manager'
					),
					validCount,
					invalidCount
				),
			};
		}

		const warningCount = Number( preview.warning_count || 0 );

		if ( validCount > 0 && warningCount > 0 ) {
			return {
				tone: 'warning',
				title: __(
					'Ready with warnings',
					'coderembassy-bulk-variations-manager'
				),
				message: sprintf(
					/* translators: 1: valid row count, 2: warning count */
					__(
						'%1$d valid row(s) can continue. Review %2$d warning(s) before applying.',
						'coderembassy-bulk-variations-manager'
					),
					validCount,
					warningCount
				),
			};
		}

		return {
			tone: validCount > 0 ? 'success' : 'neutral',
			title:
				validCount > 0
					? __(
							'Ready to preview',
							'coderembassy-bulk-variations-manager'
					  )
					: __(
							'No importable rows found',
							'coderembassy-bulk-variations-manager'
					  ),
			message:
				validCount > 0
					? sprintf(
							/* translators: %d: valid row count */
							__(
								'%d valid row(s) can continue to Preview & Approve.',
								'coderembassy-bulk-variations-manager'
							),
							validCount
					  )
					: __(
							'Check that the CSV has headers and at least one editable value.',
							'coderembassy-bulk-variations-manager'
					  ),
		};
	}, [ preview ] );

	const fetchAttributeGuide = useCallback( async ( productId ) => {
		const id = Number( productId );
		if ( ! id ) {
			setAttributeGuide( null );
			setAttributeError( '' );
			return;
		}

		setAttributeLoading( true );
		setAttributeError( '' );
		try {
			const response = await products.importAttributes( id );
			setAttributeGuide( response );
		} catch ( err ) {
			setAttributeGuide( null );
			setAttributeError(
				err?.bv?.message ||
					err?.message ||
					__(
						'Unable to load product attributes.',
						'coderembassy-bulk-variations-manager'
					)
			);
		} finally {
			setAttributeLoading( false );
		}
	}, [] );

	useEffect( () => {
		const timer = window.setTimeout( () => {
			if ( defaultProductId ) {
				fetchAttributeGuide( defaultProductId );
			} else {
				setAttributeGuide( null );
				setAttributeError( '' );
			}
		}, 500 );

		return () => window.clearTimeout( timer );
	}, [ defaultProductId, fetchAttributeGuide ] );

	useEffect( () => {
		let cancelled = false;

		settings
			.get()
			.then( ( response ) => {
				const savedProductId = Number(
					response?.default_product_id || 0
				);
				if (
					! cancelled &&
					! productIdTouched.current &&
					savedProductId > 0
				) {
					setDefaultProductId( String( savedProductId ) );
				}
			} )
			.catch( () => {} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const handlePreview = async () => {
		if ( ! file ) {
			return;
		}
		setLoading( true );
		setError( '' );
		setPreview( null );
		try {
			const csvContent = await file.text();
			const response = await imports.preview( {
				csv_content: csvContent,
				product_id: defaultProductId ? Number( defaultProductId ) : 0,
			} );
			setPreview( response );
		} catch ( err ) {
			const message =
				err?.isWordPressCriticalError ||
				err?.code === 'coderembassy_bvm_wordpress_critical_error'
					? err.message
					: err?.bv?.message || err?.message;
			setError(
				message ||
					__(
						'Unable to preview this CSV file.',
						'coderembassy-bulk-variations-manager'
					)
			);
		} finally {
			setLoading( false );
		}
	};

	const handleDownloadTemplate = async () => {
		const productId = Number( defaultProductId );
		if ( ! productId || templateDownloading ) {
			return;
		}

		setTemplateDownloading( true );
		try {
			const response = await products.importCsvTemplate( productId );
			const csv = String( response?.csv ?? '' );
			if ( ! csv ) {
				throw new Error(
					__(
						'CSV template was empty.',
						'coderembassy-bulk-variations-manager'
					)
				);
			}

			const filename =
				String( response?.filename ?? '' ) ||
				`import-template-product-${ productId }.csv`;
			const blob = new Blob( [ csv ], {
				type: 'text/csv;charset=utf-8',
			} );
			const url = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = filename;
			link.style.display = 'none';
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
			URL.revokeObjectURL( url );

			pushToast( {
				id: `import-template-${ Date.now() }`,
				type: 'success',
				message: __(
					'CSV template downloaded.',
					'coderembassy-bulk-variations-manager'
				),
			} );
		} catch ( err ) {
			pushToast( {
				id: `import-template-error-${ Date.now() }`,
				type: 'error',
				message:
					err?.bv?.message ||
					err?.message ||
					__(
						'Unable to download CSV template.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		} finally {
			setTemplateDownloading( false );
		}
	};

	const handleCreatePreviewJob = async () => {
		if ( ! canApprove ) {
			return;
		}
		setSubmitting( true );
		try {
			const payload = {
				type: 'import',
				source: 'manual',
				dry: true,
				total_items: preview.valid_rows.length,
				product_id: defaultProductId ? Number( defaultProductId ) : 0,
				changes: preview.valid_rows,
			};
			const job = await jobs.create( payload );
			const jobId = Number( job?.id || job?.job_id || 0 );
			if ( jobId > 0 ) {
				pushToast( {
					id: `import-preview-${ Date.now() }`,
					type: 'success',
					message: __(
						'Import preview created. Review and apply the job.',
						'coderembassy-bulk-variations-manager'
					),
				} );
				navigateTo( `jobs/${ jobId }/diff` );
				return;
			}
			throw new Error(
				__(
					'Import preview job was not created.',
					'coderembassy-bulk-variations-manager'
				)
			);
		} catch ( err ) {
			setError(
				err?.message ||
					__(
						'Unable to create import preview job.',
						'coderembassy-bulk-variations-manager'
					)
			);
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<div className="bv-import-view">
			<section className="bv-card">
				<CardIntro
					title={ __(
						'CSV Import',
						'coderembassy-bulk-variations-manager'
					) }
					help={ __(
						'Upload a CSV, review validation results, then preview and approve before applying.',
						'coderembassy-bulk-variations-manager'
					) }
				/>
				<div className="bv-import-view__controls">
					<label
						className="bv-dashboard__kpi-label"
						htmlFor="bv-import-file"
					>
						{ __(
							'CSV file',
							'coderembassy-bulk-variations-manager'
						) }
					</label>
					<input
						id="bv-import-file"
						type="file"
						accept=".csv,text/csv"
						onChange={ ( event ) =>
							setFile( event.target.files?.[ 0 ] || null )
						}
					/>
					<label
						className="bv-dashboard__kpi-label"
						htmlFor="bv-import-product-id"
					>
						{ __(
							'Default product ID (optional for rows with product_id)',
							'coderembassy-bulk-variations-manager'
						) }
					</label>
					<input
						id="bv-import-product-id"
						type="number"
						min="0"
						value={ defaultProductId }
						onChange={ ( event ) => {
							productIdTouched.current = true;
							setDefaultProductId( event.target.value );
						} }
					/>
					<div className="bv-import-view__product-actions">
						<Button
							variant="default"
							disabled={ ! defaultProductId || attributeLoading }
							onClick={ () =>
								fetchAttributeGuide( defaultProductId )
							}
						>
							{ attributeLoading
								? __(
										'Loading attributes…',
										'coderembassy-bulk-variations-manager'
								  )
								: __(
										'Load attributes',
										'coderembassy-bulk-variations-manager'
								  ) }
						</Button>
					</div>
					{ attributeError ? (
						<p className="bv-muted">{ attributeError }</p>
					) : null }
					{ attributeGuide ? (
						<div className="bv-import-attributes">
							<div className="bv-import-attributes__header">
								<h3 className="bv-import-attributes__title">
									{ attributeGuide.product_name
										? sprintf(
												/* translators: 1: product name, 2: product ID */
												__(
													'CSV columns for %1$s (#%2$d)',
													'coderembassy-bulk-variations-manager'
												),
												attributeGuide.product_name,
												Number(
													attributeGuide.product_id ||
														defaultProductId
												)
										  )
										: __(
												'Variation attributes for CSV',
												'coderembassy-bulk-variations-manager'
										  ) }
								</h3>
								<Button
									variant="default"
									disabled={
										templateDownloading || attributeLoading
									}
									onClick={ handleDownloadTemplate }
								>
									{ templateDownloading
										? __(
												'Preparing template…',
												'coderembassy-bulk-variations-manager'
										  )
										: __(
												'Download CSV template',
												'coderembassy-bulk-variations-manager'
										  ) }
								</Button>
							</div>
							{ ! attributeGuide.is_variable ? (
								<p className="bv-import-attributes__notice bv-import-attributes__notice--warn">
									{ attributeGuide.message ||
										__(
											'This product is not variable. New variation rows need a variable parent product.',
											'coderembassy-bulk-variations-manager'
										) }
								</p>
							) : null }
							{ attributeGuide.is_variable &&
								Array.isArray( attributeGuide.attributes ) &&
								attributeGuide.attributes.length > 0 && (
									<>
										{ attributeGuide.all_combinations_exist ? (
											<p className="bv-import-attributes__notice bv-import-attributes__notice--warn">
												{ __(
													'All attribute combinations already exist on this product. To create a new variation, add another option in WooCommerce first, then reload attributes.',
													'coderembassy-bulk-variations-manager'
												) }
											</p>
										) : null }
										<div className="bv-import-attributes__table-wrap">
											<table className="bv-import-attributes__table">
												<thead>
													<tr>
														<th scope="col">
															{ __(
																'CSV column',
																'coderembassy-bulk-variations-manager'
															) }
														</th>
														<th scope="col">
															{ __(
																'Attribute',
																'coderembassy-bulk-variations-manager'
															) }
														</th>
														<th scope="col">
															{ __(
																'Allowed values (slug)',
																'coderembassy-bulk-variations-manager'
															) }
														</th>
													</tr>
												</thead>
												<tbody>
													{ attributeGuide.attributes.map(
														( attribute ) => (
															<tr
																key={
																	attribute.csv_column
																}
															>
																<td>
																	<code>
																		{
																			attribute.csv_column
																		}
																	</code>
																</td>
																<td>
																	{
																		attribute.label
																	}
																</td>
																<td>
																	{ formatAllowedOptions(
																		attribute.options
																	) }
																</td>
															</tr>
														)
													) }
												</tbody>
											</table>
										</div>
										{ attributeGuide.existing_combination_count >
										0 ? (
											<p className="bv-muted bv-import-attributes__meta">
												{ sprintf(
													/* translators: 1: existing count, 2: total possible */
													__(
														'%1$d of %2$d combinations already exist on this product.',
														'coderembassy-bulk-variations-manager'
													),
													Number(
														attributeGuide.existing_combination_count ||
															0
													),
													Number(
														attributeGuide.total_possible_combinations ||
															0
													)
												) }
											</p>
										) : null }
									</>
								) }
							{ attributeGuide.is_variable &&
								( ! Array.isArray(
									attributeGuide.attributes
								) ||
									attributeGuide.attributes.length ===
										0 ) && (
									<p className="bv-muted">
										{ attributeGuide.message ||
											__(
												'No variation attributes are configured on this product.',
												'coderembassy-bulk-variations-manager'
											) }
									</p>
								) }
						</div>
					) : null }
					<div className="bv-import-view__actions">
						<Button
							variant="default"
							disabled={ ! canPreview }
							onClick={ handlePreview }
						>
							{ loading
								? __(
										'Previewing…',
										'coderembassy-bulk-variations-manager'
								  )
								: __(
										'Validate CSV',
										'coderembassy-bulk-variations-manager'
								  ) }
						</Button>
						<Button
							variant="primary"
							disabled={ ! canApprove }
							onClick={ handleCreatePreviewJob }
						>
							{ submitting
								? __(
										'Creating…',
										'coderembassy-bulk-variations-manager'
								  )
								: __(
										'Preview & Approve',
										'coderembassy-bulk-variations-manager'
								  ) }
						</Button>
					</div>
				</div>
				{ error ? <p className="bv-muted">{ error }</p> : null }
			</section>

			<section className="bv-card">
				{ ! preview ? (
					<EmptyState
						title={ __(
							'No CSV preview yet',
							'coderembassy-bulk-variations-manager'
						) }
						description={ __(
							'Upload a CSV file and click Validate CSV to see row counts, warnings, and sample rows.',
							'coderembassy-bulk-variations-manager'
						) }
					/>
				) : (
					<div className="bv-import-preview">
						<div className="bv-import-preview__summary">
							{ summaryCards.map( ( card ) => (
								<div
									key={ card.key }
									className={ `bv-import-preview__stat bv-import-preview__stat--${ card.tone }` }
								>
									<p className="bv-import-preview__stat-value">
										{ card.value }
									</p>
									<p className="bv-import-preview__stat-label">
										{ card.label }
									</p>
								</div>
							) ) }
						</div>

						{ previewStatus ? (
							<div
								className={ `bv-import-preview__notice bv-import-preview__notice--${ previewStatus.tone }` }
								role="status"
							>
								<strong>{ previewStatus.title }</strong>
								<span>{ previewStatus.message }</span>
							</div>
						) : null }

						{ invalidRows.length > 0 ? (
							<div className="bv-import-preview__section">
								<div className="bv-import-preview__section-heading">
									<h3 className="bv-import-preview__heading">
										{ __(
											'Rows to fix',
											'coderembassy-bulk-variations-manager'
										) }
									</h3>
									<p className="bv-import-preview__section-help">
										{ __(
											'These rows will not be included in the preview job.',
											'coderembassy-bulk-variations-manager'
										) }
									</p>
								</div>
								<ul className="bv-import-preview__issue-list">
									{ invalidRows.map( ( item ) => (
										<li
											key={ `invalid-${ item.row }` }
											className="bv-import-preview__issue"
										>
											<span className="bv-import-preview__issue-row">
												{ sprintf(
													/* translators: %d: CSV row number */
													__(
														'Row %d',
														'coderembassy-bulk-variations-manager'
													),
													item.row
												) }
											</span>
											<span className="bv-import-preview__issue-message">
												{ item.message }
											</span>
										</li>
									) ) }
								</ul>
							</div>
						) : null }

						{ warningRows.length > 0 ? (
							<div className="bv-import-preview__section">
								<div className="bv-import-preview__section-heading">
									<h3 className="bv-import-preview__heading">
										{ __(
											'Warnings to review',
											'coderembassy-bulk-variations-manager'
										) }
									</h3>
									<p className="bv-import-preview__section-help">
										{ __(
											'Warnings do not block import, but the values may need a second look.',
											'coderembassy-bulk-variations-manager'
										) }
									</p>
								</div>
								<ul className="bv-import-preview__issue-list bv-import-preview__issue-list--warnings">
									{ warningRows.map( ( item ) => (
										<li
											key={ `warning-${ item.row }` }
											className="bv-import-preview__issue"
										>
											<span className="bv-import-preview__issue-row">
												{ sprintf(
													/* translators: %d: CSV row number */
													__(
														'Row %d',
														'coderembassy-bulk-variations-manager'
													),
													item.row
												) }
											</span>
											<span className="bv-import-preview__issue-message">
												{ item.message }
											</span>
										</li>
									) ) }
								</ul>
							</div>
						) : null }

						<div className="bv-import-preview__section">
							<div className="bv-import-preview__section-heading">
								<h3 className="bv-import-preview__heading">
									{ __(
										'Rows ready for preview',
										'coderembassy-bulk-variations-manager'
									) }
								</h3>
								<p className="bv-import-preview__section-help">
									{ __(
										'Showing a sample of valid rows that will be sent to Preview & Approve.',
										'coderembassy-bulk-variations-manager'
									) }
								</p>
							</div>
							{ sampleRows.length === 0 ? (
								<p className="bv-import-preview__empty-note">
									{ __(
										'No valid rows to preview.',
										'coderembassy-bulk-variations-manager'
									) }
								</p>
							) : (
								<div className="bv-import-preview__table-wrap">
									<table className="bv-import-preview__table">
										<thead>
											<tr>
												{ sampleColumns.map(
													( column ) => (
														<th
															key={ column.key }
															scope="col"
														>
															{ column.label }
														</th>
													)
												) }
											</tr>
										</thead>
										<tbody>
											{ sampleRows.map(
												( row, index ) => (
													<tr
														key={ `sample-${ index }` }
													>
														{ sampleColumns.map(
															( column ) => (
																<td
																	key={
																		column.key
																	}
																>
																	{ formatCell(
																		row[
																			column
																				.key
																		]
																	) }
																</td>
															)
														) }
													</tr>
												)
											) }
										</tbody>
									</table>
								</div>
							) }
						</div>

						<details className="bv-import-preview__technical">
							<summary>
								{ __(
									'Technical details',
									'coderembassy-bulk-variations-manager'
								) }
							</summary>
							<pre className="bv-import-preview__json">
								{ JSON.stringify( preview, null, 2 ) }
							</pre>
						</details>
					</div>
				) }
			</section>
		</div>
	);
}
