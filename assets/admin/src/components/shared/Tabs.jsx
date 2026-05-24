/**
 * Accessible tabs with keyboard navigation.
 */
import { useId, useState } from '@wordpress/element';

export default function Tabs( {
	tabs = [],
	defaultTab,
	onChange,
	className = '',
} ) {
	const baseId = useId();
	const [ active, setActive ] = useState( defaultTab || tabs[ 0 ]?.id || '' );

	const selectTab = ( id ) => {
		setActive( id );
		onChange?.( id );
	};

	const onKeyDown = ( event, index ) => {
		const max = tabs.length - 1;
		let next = index;

		if ( event.key === 'ArrowRight' ) {
			next = index >= max ? 0 : index + 1;
		} else if ( event.key === 'ArrowLeft' ) {
			next = index <= 0 ? max : index - 1;
		} else if ( event.key === 'Home' ) {
			next = 0;
		} else if ( event.key === 'End' ) {
			next = max;
		} else {
			return;
		}

		event.preventDefault();
		const tab = tabs[ next ];
		if ( tab ) {
			selectTab( tab.id );
			document.getElementById( `${ baseId }-tab-${ tab.id }` )?.focus();
		}
	};

	const current = tabs.find( ( tab ) => tab.id === active ) || tabs[ 0 ];

	return (
		<div className={ `bv-tabs${ className ? ` ${ className }` : '' }` }>
			<div className="bv-tabs__list" role="tablist" aria-label="Sections">
				{ tabs.map( ( tab, index ) => {
					const selected = tab.id === current?.id;
					return (
						<button
							key={ tab.id }
							id={ `${ baseId }-tab-${ tab.id }` }
							type="button"
							className={ `bv-tabs__tab${
								selected ? ' is-active' : ''
							}` }
							role="tab"
							aria-selected={ selected }
							aria-controls={ `${ baseId }-panel-${ tab.id }` }
							tabIndex={ selected ? 0 : -1 }
							onClick={ () => selectTab( tab.id ) }
							onKeyDown={ ( event ) => onKeyDown( event, index ) }
						>
							{ tab.label }
						</button>
					);
				} ) }
			</div>
			{ current && (
				<div
					id={ `${ baseId }-panel-${ current.id }` }
					className="bv-tabs__panel"
					role="tabpanel"
					aria-labelledby={ `${ baseId }-tab-${ current.id }` }
					tabIndex={ 0 }
				>
					{ current.content }
				</div>
			) }
		</div>
	);
}
