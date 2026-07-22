/**
 * Bulk editor column picker (horizontal responsive checkbox grid).
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { mergeColumnCatalog } from './columns';
import CardIntro from './CardIntro';

const storageKey = ( productId ) =>
	`coderembassy_bvm_columns_${ productId || 0 }`;

/**
 * Ensure default attribute columns are visible after image_url.
 *
 * @param {string[]} selected Current selection.
 * @param {Array<{ id: string, default?: boolean }>} catalog Full catalog.
 * @return {string[]}
 */
const mergeAttributeDefaults = ( selected, catalog ) => {
	const merged = [ ...selected ];
	const imageIndex = merged.indexOf( 'image_url' );
	const attrIds = catalog
		.filter(
			( column ) =>
				column.id.startsWith( 'attribute_' ) && column.default !== false
		)
		.map( ( column ) => column.id );

	attrIds.forEach( ( id, index ) => {
		if ( merged.includes( id ) ) {
			return;
		}
		if ( imageIndex >= 0 ) {
			merged.splice( imageIndex + 1 + index, 0, id );
			return;
		}
		merged.push( id );
	} );

	return merged;
};

export default function ColumnPicker( {
	productId = 0,
	attributeColumns = [],
	onChange,
	actions,
} ) {
	const catalog = useMemo(
		() => mergeColumnCatalog( attributeColumns ),
		[ attributeColumns ]
	);

	const defaults = useMemo(
		() =>
			catalog
				.filter( ( col ) => col.default !== false )
				.map( ( col ) => col.id ),
		[ catalog ]
	);

	const [ selected, setSelected ] = useState( defaults );

	useEffect( () => {
		try {
			const raw = window.localStorage.getItem( storageKey( productId ) );
			if ( raw ) {
				const parsed = JSON.parse( raw );
				if ( Array.isArray( parsed ) && parsed.length > 0 ) {
					const stored = parsed.filter( ( id ) =>
						catalog.some( ( col ) => col.id === id )
					);
					setSelected( mergeAttributeDefaults( stored, catalog ) );
					return;
				}
			}
		} catch ( err ) {
			void err;
		}
		setSelected( mergeAttributeDefaults( defaults, catalog ) );
	}, [ productId, catalog, defaults ] );

	useEffect( () => {
		try {
			window.localStorage.setItem(
				storageKey( productId ),
				JSON.stringify( selected )
			);
		} catch ( err ) {
			void err;
		}
		onChange?.( selected );
	}, [ productId, selected, onChange ] );

	const toggle = ( id ) => {
		setSelected( ( current ) =>
			current.includes( id )
				? current.filter( ( item ) => item !== id )
				: [ ...current, id ]
		);
	};

	const selectAll = () => {
		setSelected( catalog.map( ( column ) => column.id ) );
	};

	return (
		<section
			className="bv-card bv-columns-card"
			aria-label={ __(
				'Customize table',
				'coderembassy-bulk-variations-manager'
			) }
		>
			<CardIntro
				title={ __(
					'Customize table',
					'coderembassy-bulk-variations-manager'
				) }
				help={ __(
					'Choose which columns you want to see. This does not change product data.',
					'coderembassy-bulk-variations-manager'
				) }
				actions={ actions }
			/>
			<div className="bv-column-grid">
				{ catalog.map( ( column ) => {
					const checked = selected.includes( column.id );
					const inputId = `bv-col-${ column.id }`;
					const isAttribute = column.id.startsWith( 'attribute_' );
					return (
						<label
							key={ column.id }
							htmlFor={ inputId }
							className={ `bv-column-grid__item${
								isAttribute
									? ' bv-column-grid__item--attribute'
									: ''
							}` }
						>
							<input
								id={ inputId }
								type="checkbox"
								checked={ checked }
								onChange={ () => toggle( column.id ) }
							/>
							<span>{ column.label }</span>
						</label>
					);
				} ) }
			</div>
			<button
				type="button"
				className="bv-link-button"
				onClick={ selectAll }
			>
				{ __( 'Select all', 'coderembassy-bulk-variations-manager' ) }
			</button>
		</section>
	);
}
