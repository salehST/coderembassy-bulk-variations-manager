/**
 * Admin app entrypoint.
 */
import { render } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import App from './components/App';
import { STORE_NAME } from './store';
import { readInitialTheme } from './hooks/useTheme';

const root = document.getElementById( 'coderembassy-bvm-admin-root' );

if ( root ) {
	const globals = window.CoderEmbassyBvmAdmin || {};
	dispatch( STORE_NAME ).setGlobals( globals );
	dispatch( STORE_NAME ).setTheme(
		readInitialTheme(
			globals?.settings?.theme || globals?.initial_theme || 'auto'
		)
	);
	render( <App />, root );
}
