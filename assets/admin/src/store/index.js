/**
 * WordPress data store for the Bulk Variations admin app.
 */
import { createReduxStore, register } from '@wordpress/data';

export const STORE_NAME = 'coderembassy-bulk-variations-manager/admin';

const DEFAULT_STATE = {
	globals: {},
	ui: {
		activeView: 'dashboard',
		theme: 'auto',
		toasts: [],
		modals: {},
		shortcutsVisible: false,
		paletteOpen: false,
	},
	jobs: {
		list: [],
		total: 0,
		polling: false,
	},
};

const actions = {
	setGlobals( globals ) {
		return { type: 'SET_GLOBALS', globals };
	},
	setActiveView( activeView ) {
		return { type: 'SET_ACTIVE_VIEW', activeView };
	},
	setTheme( theme ) {
		return { type: 'SET_THEME', theme };
	},
	pushToast( toast ) {
		return { type: 'PUSH_TOAST', toast };
	},
	dismissToast( id ) {
		return { type: 'DISMISS_TOAST', id };
	},
	openModal( key, payload ) {
		return { type: 'OPEN_MODAL', key, payload };
	},
	closeModal( key ) {
		return { type: 'CLOSE_MODAL', key };
	},
	togglePalette() {
		return { type: 'TOGGLE_PALETTE' };
	},
	setPaletteOpen( paletteOpen ) {
		return { type: 'SET_PALETTE_OPEN', paletteOpen };
	},
	toggleShortcuts( shortcutsVisible ) {
		return { type: 'TOGGLE_SHORTCUTS', shortcutsVisible };
	},
	setJobs( list, total ) {
		return { type: 'SET_JOBS', list, total };
	},
	setJobsPolling( polling ) {
		return { type: 'SET_JOBS_POLLING', polling };
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_GLOBALS':
			return { ...state, globals: action.globals };
		case 'SET_ACTIVE_VIEW':
			return {
				...state,
				ui: { ...state.ui, activeView: action.activeView },
			};
		case 'SET_THEME':
			return {
				...state,
				ui: { ...state.ui, theme: action.theme },
			};
		case 'PUSH_TOAST':
			return {
				...state,
				ui: {
					...state.ui,
					toasts: [
						...state.ui.toasts,
						{
							id: action.toast.id || `toast-${ Date.now() }`,
							...action.toast,
						},
					],
				},
			};
		case 'DISMISS_TOAST':
			return {
				...state,
				ui: {
					...state.ui,
					toasts: state.ui.toasts.filter(
						( toast ) => toast.id !== action.id
					),
				},
			};
		case 'OPEN_MODAL':
			return {
				...state,
				ui: {
					...state.ui,
					modals: {
						...state.ui.modals,
						[ action.key ]: action.payload ?? true,
					},
				},
			};
		case 'CLOSE_MODAL': {
			const modals = { ...state.ui.modals };
			delete modals[ action.key ];
			return {
				...state,
				ui: { ...state.ui, modals },
			};
		}
		case 'TOGGLE_PALETTE':
			return {
				...state,
				ui: { ...state.ui, paletteOpen: ! state.ui.paletteOpen },
			};
		case 'SET_PALETTE_OPEN':
			return {
				...state,
				ui: { ...state.ui, paletteOpen: action.paletteOpen },
			};
		case 'TOGGLE_SHORTCUTS':
			return {
				...state,
				ui: {
					...state.ui,
					shortcutsVisible:
						typeof action.shortcutsVisible === 'boolean'
							? action.shortcutsVisible
							: ! state.ui.shortcutsVisible,
				},
			};
		case 'SET_JOBS':
			return {
				...state,
				jobs: {
					...state.jobs,
					list: Array.isArray( action.list ) ? action.list : [],
					total: Number( action.total || 0 ),
				},
			};
		case 'SET_JOBS_POLLING':
			return {
				...state,
				jobs: { ...state.jobs, polling: !! action.polling },
			};
		default:
			return state;
	}
};

const selectors = {
	getGlobals( state ) {
		return state.globals;
	},
	getUi( state ) {
		return state.ui;
	},
	getActiveView( state ) {
		return state.ui.activeView;
	},
	getTheme( state ) {
		return state.ui.theme || 'auto';
	},
	getToasts( state ) {
		return state.ui.toasts;
	},
	getModals( state ) {
		return state.ui.modals;
	},
	isPaletteOpen( state ) {
		return !! state.ui.paletteOpen;
	},
	getJobs( state ) {
		return state.jobs;
	},
	getRunningJobsCount( state ) {
		return ( state.jobs.list || [] ).filter( ( job ) =>
			[ 'queued', 'running', 'processing' ].includes(
				String( job?.status || '' )
			)
		).length;
	},
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );
