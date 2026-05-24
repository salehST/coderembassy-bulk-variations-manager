/**
 * Product picker for the Free plugin: one variable product at a time.
 */
import { useEffect, useState } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { search } from '@wordpress/icons';
import { products } from '../../../api/endpoints';
import Button from '../../shared/Button';
import CardIntro from './CardIntro';

export default function ProductPicker( {
	productIds = [],
	onChange,
	onSearchResults,
	onProductMeta, // optional
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ titleMap, setTitleMap ] = useState( {} );

	useEffect( () => {
		if ( ! query || query.length < 2 ) {
			setResults( [] );
			return undefined;
		}

		const timer = window.setTimeout( async () => {
			setLoading( true );
			try {
				const response = await products.search( query );
				let rows = [];
				if ( Array.isArray( response?.data ) ) {
					rows = response.data;
				} else if ( Array.isArray( response ) ) {
					rows = response;
				}
				setResults( rows );
				onSearchResults?.( rows );
				if ( rows.length ) {
					setTitleMap( ( current ) => {
						const next = { ...current };
						rows.forEach( ( row ) => {
							const rowTitle =
								row?.title || row?.label || row?.name || '';
							if ( row?.id && rowTitle ) {
								next[ row.id ] = rowTitle;
							}
						} );
						return next;
					} );
				}
			} catch ( err ) {
				void err;
				setResults( [] );
			} finally {
				setLoading( false );
			}
		}, 300 );

		return () => window.clearTimeout( timer );
	}, [ query, onSearchResults ] );

	const addProduct = ( product ) => {
		const id = product?.id;
		const numeric = parseInt( id, 10 );
		if ( ! numeric ) {
			return;
		}
		const title = product?.title || product?.label || product?.name || '';
		if ( title ) {
			setTitleMap( ( current ) => ( {
				...current,
				[ numeric ]: title,
			} ) );
		}
		onProductMeta?.( {
			id: numeric,
			title: title || `#${ numeric }`,
			variation_count: product?.variation_count || 0,
			sku: product?.sku || '',
		} );

		onChange?.( [ numeric ] );
	};

	const selectedTitle = productIds
		.map( ( id ) => titleMap[ id ] || `#${ id }` )
		.join( ', ' );

	return (
		<section
			className="bv-product-picker bv-card"
			aria-label={ __(
				'Choose a product',
				'coderembassy-bulk-variations-manager'
			) }
		>
			<CardIntro
				title={ __(
					'Choose a product',
					'coderembassy-bulk-variations-manager'
				) }
				help={ __(
					'Search for a variable product, then edit its variations in the table below.',
					'coderembassy-bulk-variations-manager'
				) }
			/>
			<label className="screen-reader-text" htmlFor="bv-product-search">
				{ __(
					'Search variable products',
					'coderembassy-bulk-variations-manager'
				) }
			</label>
			<div className="bv-product-picker__search-wrap">
				<input
					id="bv-product-search"
					className="bv-product-picker__input"
					type="search"
					value={ query }
					placeholder={ __(
						'Search products by name…',
						'coderembassy-bulk-variations-manager'
					) }
					onChange={ ( event ) => setQuery( event.target.value ) }
				/>
				<Icon
					className="bv-product-picker__search-icon"
					icon={ search }
					size={ 20 }
					aria-hidden="true"
				/>
			</div>
			{ loading && (
				<p className="bv-muted">
					{ __(
						'Searching…',
						'coderembassy-bulk-variations-manager'
					) }
				</p>
			) }
			{ results.length > 0 && (
				<ul className="bv-product-picker__results">
					{ results.map( ( row ) => (
						<li key={ row.id }>
							<Button
								variant="ghost"
								size="sm"
								onClick={ () => {
									addProduct( row );
									setQuery( '' );
									setResults( [] );
								} }
							>
								{ row.title ||
									row.label ||
									row.name ||
									`#${ row.id }` }
								{ row.sku ? ` (${ row.sku })` : '' } (
								{ row.variation_count || 0 }{ ' ' }
								{ __(
									'variations',
									'coderembassy-bulk-variations-manager'
								) }
								)
							</Button>
						</li>
					) ) }
				</ul>
			) }
			{ productIds.length > 0 && (
				<p className="bv-product-picker__editing">
					{ __( 'Editing:', 'coderembassy-bulk-variations-manager' ) }{ ' ' }
					<strong>{ selectedTitle }</strong>
				</p>
			) }
		</section>
	);
}
