/**
 * Free-tier view registry. Pro addon may extend via `bulkVariations.views` filter.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { DEFAULT_ROUTE } from '../navigation';
import Dashboard from '../components/views/Dashboard';
import BulkEditor from '../components/views/BulkEditor';
import ImportView from '../components/views/Import';
import Jobs from '../components/views/Jobs';
import PlaceholderView from '../components/views/PlaceholderView';

/** @typedef {import('react').ComponentType} ViewComponent */

/**
 * @typedef {Object} ViewDefinition
 * @property {string}        id        Stable view id.
 * @property {string}        route     Hash route segment.
 * @property {() => string}  getLabel  Sidebar / title label.
 * @property {ViewComponent} component Root view component.
 * @property {boolean}       [sidebar] Show in sidebar nav (default true).
 * @property {number}        [order]   Sidebar sort order.
 * @property {string|null}   [badge]   Sidebar badge key (`runningJobs`) or null.
 * @property {'free'|'pro'}  [tier]    Omit or `free` for Free shell; Pro addon uses `pro`.
 */

/** @type {ViewDefinition[]} */
const FREE_VIEWS = [
	{
		id: 'dashboard',
		route: 'dashboard',
		getLabel: () =>
			__( 'Dashboard', 'coderembassy-bulk-variations-manager' ),
		component: Dashboard,
		sidebar: true,
		order: 10,
		badge: null,
		tier: 'free',
	},
	{
		id: 'editor',
		route: 'editor',
		getLabel: () =>
			__( 'Bulk Editor', 'coderembassy-bulk-variations-manager' ),
		component: BulkEditor,
		sidebar: true,
		order: 20,
		badge: null,
		tier: 'free',
	},
	{
		id: 'import',
		route: 'import',
		getLabel: () =>
			__( 'CSV Import', 'coderembassy-bulk-variations-manager' ),
		component: ImportView,
		sidebar: true,
		order: 25,
		badge: null,
		tier: 'free',
	},
	{
		id: 'jobs',
		route: 'jobs',
		getLabel: () => __( 'Jobs', 'coderembassy-bulk-variations-manager' ),
		component: Jobs,
		sidebar: true,
		order: 30,
		badge: 'runningJobs',
		tier: 'free',
	},
	{
		id: 'settings',
		route: 'settings',
		getLabel: () =>
			__( 'Settings', 'coderembassy-bulk-variations-manager' ),
		component: PlaceholderView,
		sidebar: true,
		order: 40,
		badge: null,
		tier: 'free',
	},
	{
		id: 'help',
		route: 'help',
		getLabel: () => __( 'Help', 'coderembassy-bulk-variations-manager' ),
		component: PlaceholderView,
		sidebar: true,
		order: 50,
		badge: null,
		tier: 'free',
	},
];

export const VIEWS_FILTER = 'bulkVariations.views';
export const SIDEBAR_FILTER = 'bulkVariations.sidebarNav';

/**
 * @param {ViewDefinition} view View definition.
 * @return {boolean} True when the view belongs in the Free shell.
 */
const isFreeShellView = ( view ) => view.tier !== 'pro';

/**
 * All views registered for the current shell (Free + filtered extensions).
 *
 * @return {ViewDefinition[]} Registered view definitions.
 */
export function getRegisteredViews() {
	const merged = applyFilters( VIEWS_FILTER, [ ...FREE_VIEWS ] );

	if ( ! Array.isArray( merged ) ) {
		return [ ...FREE_VIEWS ];
	}

	return merged.filter( isFreeShellView );
}

/**
 * Sidebar navigation items (Free shell never shows locked/pro-only entries).
 *
 * @return {ViewDefinition[]} Sidebar nav entries.
 */
export function getSidebarNavItems() {
	const nav = getRegisteredViews().filter(
		( view ) => view.sidebar !== false
	);
	const sorted = [ ...nav ].sort(
		( a, b ) => ( a.order || 0 ) - ( b.order || 0 )
	);

	const filtered = applyFilters( SIDEBAR_FILTER, sorted );

	if ( ! Array.isArray( filtered ) ) {
		return sorted;
	}

	return filtered.filter( isFreeShellView );
}

/**
 * Resolve a top-level route to a view definition (defaults to Dashboard).
 *
 * @param {string} route Route segment.
 * @return {ViewDefinition} Matching view or dashboard fallback.
 */
export function resolveView( route ) {
	const views = getRegisteredViews();
	const match = views.find( ( view ) => view.route === route );

	if ( match ) {
		return match;
	}

	return (
		views.find( ( view ) => view.route === DEFAULT_ROUTE ) ||
		FREE_VIEWS[ 0 ]
	);
}

/**
 * Whether the base route is registered or is a supported jobs sub-route.
 *
 * @param {string}   base     First hash segment.
 * @param {string[]} segments Remaining segments.
 * @return {boolean} True when the route is allowed.
 */
export function isKnownRoute( base, segments = [] ) {
	if ( resolveView( base ).route === base ) {
		return true;
	}

	return base === 'jobs' && segments.length > 0;
}

/**
 * @return {string[]} Top-level route ids.
 */
export function getRegisteredRoutes() {
	return getRegisteredViews().map( ( view ) => view.route );
}
