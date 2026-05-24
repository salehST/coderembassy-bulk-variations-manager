/**
 * Primary navigation sidebar — items from the view registry (Free shell).
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../store';
import { useTheme } from '../hooks/useTheme';
import { navigateTo, parseHash } from '../navigation';
import { getSidebarNavItems } from '../registry/viewRegistry';

/**
 * @param {string}                         itemRoute Registered top-level route.
 * @param {{ path: string, base: string }} current   Parsed hash route.
 * @return {boolean} True when the nav item matches the current hash route.
 */
const isNavActive = ( itemRoute, current ) => {
	const pathOnly = current.path;
	return pathOnly === itemRoute || pathOnly.startsWith( `${ itemRoute }/` );
};

export default function Sidebar() {
	const [ route, setRoute ] = useState( () => parseHash() );

	const globals = useSelect(
		( select ) => select( STORE_NAME ).getGlobals(),
		[]
	);
	const runningCount = useSelect(
		( select ) => select( STORE_NAME ).getRunningJobsCount(),
		[]
	);
	const { resolvedTheme } = useTheme();

	useEffect( () => {
		const onHashChange = () => setRoute( parseHash() );
		window.addEventListener( 'hashchange', onHashChange );
		onHashChange();
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	const logo =
		resolvedTheme === 'dark' ? globals?.logo_dark : globals?.logo_light;

	const navItems = getSidebarNavItems();

	return (
		<aside className="bv-sidebar">
			<div className="bv-sidebar__header">
				{ logo ? (
					<img
						className="bv-sidebar__logo"
						src={ logo }
						alt={ __(
							'CoderEmbassy Bulk Variations Manager',
							'coderembassy-bulk-variations-manager'
						) }
					/>
				) : (
					<strong className="bv-sidebar__brand">
						{ __(
							'CoderEmbassy Bulk Variations Manager',
							'coderembassy-bulk-variations-manager'
						) }
					</strong>
				) }
			</div>

			<nav
				className="bv-sidebar__nav"
				aria-label={ __(
					'CoderEmbassy Bulk Variations Manager',
					'coderembassy-bulk-variations-manager'
				) }
			>
				<ul
					className="bv-sidebar__list"
					style={ { listStyle: 'none', margin: 0, padding: 0 } }
				>
					{ navItems.map( ( item ) => {
						const active = isNavActive( item.route, route );
						const showBadge =
							item.badge === 'runningJobs' && runningCount > 0;

						return (
							<li key={ item.id }>
								<button
									type="button"
									className={ `bv-nav-item${
										active ? ' is-active' : ''
									}` }
									onClick={ () => navigateTo( item.route ) }
									aria-current={ active ? 'page' : undefined }
								>
									<span>{ item.getLabel() }</span>
									{ showBadge && (
										<span
											className={ `bv-nav-item__badge${
												runningCount > 0
													? ' bv-pulse'
													: ''
											}` }
										>
											{ runningCount }
										</span>
									) }
								</button>
							</li>
						);
					} ) }
				</ul>
			</nav>
		</aside>
	);
}
