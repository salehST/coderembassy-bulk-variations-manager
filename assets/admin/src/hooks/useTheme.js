/**
 * Theme hook — applies data-theme on #bv-admin-root and respects system preference.
 */
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../store';

export const THEME_KEY = 'bv_admin_theme';

const resolveTheme = ( theme ) => {
	if ( theme !== 'auto' ) {
		return theme;
	}

	if (
		typeof window !== 'undefined' &&
		window.matchMedia?.( '(prefers-color-scheme: dark)' ).matches
	) {
		return 'dark';
	}

	return 'light';
};

export function useTheme() {
	const theme = useSelect(
		( select ) => select( STORE_NAME ).getTheme(),
		[]
	);
	const { setTheme } = useDispatch( STORE_NAME );

	useEffect( () => {
		const root = document.getElementById( 'bv-admin-root' );
		if ( ! root ) {
			return undefined;
		}

		const apply = () => {
			root.setAttribute( 'data-theme', resolveTheme( theme ) );
		};

		apply();

		if ( theme !== 'auto' ) {
			return undefined;
		}

		const media = window.matchMedia( '(prefers-color-scheme: dark)' );
		const onChange = () => apply();
		media.addEventListener( 'change', onChange );
		return () => media.removeEventListener( 'change', onChange );
	}, [ theme ] );

	/** Flip between light and dark (topbar toggle). */
	const toggleTheme = () => {
		const resolved = resolveTheme( theme );
		const next = resolved === 'dark' ? 'light' : 'dark';
		setTheme( next );
		persistTheme( next );
	};

	/** Cycle auto → light → dark (settings UI). */
	const cycleTheme = () => {
		const order = [ 'auto', 'light', 'dark' ];
		const index = order.indexOf( theme );
		const next = order[ ( index + 1 ) % order.length ];
		setTheme( next );
		persistTheme( next );
	};

	return {
		theme,
		resolvedTheme: resolveTheme( theme ),
		toggleTheme,
		cycleTheme,
		setTheme,
	};
}

export function readInitialTheme( fallback = 'auto' ) {
	try {
		return window.localStorage.getItem( THEME_KEY ) || fallback;
	} catch ( err ) {
		void err;
		return fallback;
	}
}

export function persistTheme( theme ) {
	try {
		window.localStorage.setItem( THEME_KEY, theme );
	} catch ( err ) {
		void err;
	}
}
