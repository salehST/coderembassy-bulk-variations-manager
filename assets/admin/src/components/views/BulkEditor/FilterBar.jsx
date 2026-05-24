/**
 * Quick filters for the bulk editor grid.
 */
import { __ } from '@wordpress/i18n';
import CardIntro from './CardIntro';

export default function FilterBar( { filters, onChange } ) {
	const update = ( key, value ) => {
		onChange?.( { ...filters, [ key ]: value } );
	};

	return (
		<>
			<CardIntro
				title={ __(
					'Find rows',
					'coderembassy-bulk-variations-manager'
				) }
				help={ __(
					'Filter the table before selecting variations to edit.',
					'coderembassy-bulk-variations-manager'
				) }
			/>
			<div className="bv-editor-filter-bar" role="search">
				<label
					className="bv-editor-filter-bar__field"
					htmlFor="bv-filter-sku"
				>
					<span className="bv-editor-filter-bar__label">
						{ __(
							'SKU contains',
							'coderembassy-bulk-variations-manager'
						) }
					</span>
					<input
						id="bv-filter-sku"
						type="search"
						className="bv-editor-filter-bar__input"
						value={ filters.sku || '' }
						placeholder={ __(
							'Filter SKU…',
							'coderembassy-bulk-variations-manager'
						) }
						onChange={ ( event ) =>
							update( 'sku', event.target.value )
						}
					/>
				</label>
				<label
					className="bv-editor-filter-bar__field"
					htmlFor="bv-filter-status"
				>
					<span className="bv-editor-filter-bar__label">
						{ __(
							'Status',
							'coderembassy-bulk-variations-manager'
						) }
					</span>
					<select
						id="bv-filter-status"
						className="bv-editor-filter-bar__input"
						value={ filters.status || '' }
						onChange={ ( event ) =>
							update( 'status', event.target.value )
						}
					>
						<option value="">
							{ __(
								'All statuses',
								'coderembassy-bulk-variations-manager'
							) }
						</option>
						<option value="publish">publish</option>
						<option value="private">private</option>
					</select>
				</label>
				<label
					className="bv-editor-filter-bar__field"
					htmlFor="bv-filter-stock"
				>
					<span className="bv-editor-filter-bar__label">
						{ __(
							'Stock',
							'coderembassy-bulk-variations-manager'
						) }
					</span>
					<select
						id="bv-filter-stock"
						className="bv-editor-filter-bar__input"
						value={ filters.stock_status || '' }
						onChange={ ( event ) =>
							update( 'stock_status', event.target.value )
						}
					>
						<option value="">
							{ __(
								'All stock',
								'coderembassy-bulk-variations-manager'
							) }
						</option>
						<option value="instock">instock</option>
						<option value="outofstock">outofstock</option>
						<option value="onbackorder">onbackorder</option>
					</select>
				</label>
			</div>
		</>
	);
}
