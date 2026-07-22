/**
 * Column picker with localStorage persistence per product.
 */
import { useEffect, useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from './Button';

const storageKey = ( productId ) => `coderembassy_bvm_columns_${ productId }`;

export default function ColumnPicker( {
	productId,
	columns = [],
	onChange,
	className = '',
} ) {
	const listId = useId();
	const [ selected, setSelected ] = useState( () => {
		try {
			const raw = window.localStorage.getItem( storageKey( productId ) );
			if ( raw ) {
				return JSON.parse( raw );
			}
		} catch ( err ) {
			void err;
		}
		return columns
			.filter( ( col ) => col.default !== false )
			.map( ( col ) => col.id );
	} );

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

	return (
		<div
			className={ `bv-column-picker${
				className ? ` ${ className }` : ''
			}` }
			role="group"
			aria-labelledby={ `${ listId }-label` }
		>
			<div id={ `${ listId }-label` } className="bv-column-picker__label">
				{ __(
					'Visible columns',
					'coderembassy-bulk-variations-manager'
				) }
			</div>
			<ul className="bv-column-picker__list">
				{ columns.map( ( column ) => {
					const checked = selected.includes( column.id );
					const inputId = `${ listId }-${ column.id }`;
					return (
						<li
							key={ column.id }
							className="bv-column-picker__item"
						>
							<label htmlFor={ inputId }>
								<input
									id={ inputId }
									type="checkbox"
									checked={ checked }
									onChange={ () => toggle( column.id ) }
									aria-checked={ checked }
								/>
								<span>{ column.label }</span>
							</label>
						</li>
					);
				} ) }
			</ul>
			<Button
				variant="ghost"
				size="sm"
				onClick={ () =>
					setSelected( columns.map( ( column ) => column.id ) )
				}
			>
				{ __( 'Select all', 'coderembassy-bulk-variations-manager' ) }
			</Button>
		</div>
	);
}
