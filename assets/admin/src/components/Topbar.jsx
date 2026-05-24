/**
 * Admin top bar — theme toggle and user (content area only; logo in sidebar).
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../store';
import { useTheme } from '../hooks/useTheme';
import { moon, sun } from '../icons/moon-sun';

const userInitials = ( user ) => {
	const name = ( user?.display_name || user?.name || '' ).trim();
	if ( ! name ) {
		return 'AD';
	}

	const parts = name.split( /\s+/ ).filter( Boolean );
	if ( parts.length >= 2 ) {
		return (
			parts[ 0 ].charAt( 0 ) + parts[ 1 ].charAt( 0 )
		).toUpperCase();
	}

	return name.slice( 0, 2 ).toUpperCase();
};

export default function Topbar() {
	const globals = useSelect(
		( select ) => select( STORE_NAME ).getGlobals(),
		[]
	);
	const { toggleTheme, resolvedTheme } = useTheme();

	const user = globals?.current_user || {};
	const themeIcon = resolvedTheme === 'dark' ? sun : moon;

	return (
		<header className="bv-topbar">
			<div className="bv-topbar__spacer" />

			<div className="bv-topbar__right bv-topbar__cluster">
				<button
					type="button"
					className="bv-topbar__theme"
					onClick={ toggleTheme }
					aria-label={ __(
						'Toggle dark mode',
						'coderembassy-bulk-variations-manager'
					) }
				>
					<span className="bv-topbar__theme-icon" aria-hidden="true">
						<Icon icon={ themeIcon } size={ 20 } />
					</span>
				</button>

				<div className="bv-topbar__user">
					{ user.avatar_url ? (
						<img
							className="bv-topbar__user-avatar"
							src={ user.avatar_url }
							alt=""
						/>
					) : (
						<span
							className="bv-topbar__user-initial"
							aria-hidden="true"
						>
							{ userInitials( user ) }
						</span>
					) }
					<span className="bv-topbar__user-name">
						{ user.display_name ||
							__(
								'Admin',
								'coderembassy-bulk-variations-manager'
							) }
					</span>
				</div>
			</div>
		</header>
	);
}
