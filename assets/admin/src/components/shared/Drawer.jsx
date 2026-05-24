/**
 * Right slide-in drawer with focus trap and Esc close.
 */
import { createPortal, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from './Button';

const FOCUSABLE =
	'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function Drawer( {
	open = false,
	title,
	onClose,
	children,
	className = '',
} ) {
	const panelRef = useRef( null );
	const lastFocusRef = useRef( null );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		const panel = panelRef.current;
		const ownerDoc = panel?.ownerDocument;
		lastFocusRef.current = ownerDoc?.activeElement || null;
		const focusables = panel?.querySelectorAll( FOCUSABLE ) || [];
		focusables[ 0 ]?.focus();

		const trapFocus = ( event ) => {
			if ( event.key !== 'Tab' || ! panel || ! ownerDoc ) {
				return;
			}
			const nodes = panel.querySelectorAll( FOCUSABLE );
			if ( ! nodes.length ) {
				return;
			}
			const first = nodes[ 0 ];
			const last = nodes[ nodes.length - 1 ];
			const active = ownerDoc.activeElement;
			if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		};

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				onClose?.();
				return;
			}
			trapFocus( event );
		};

		window.addEventListener( 'keydown', onKeyDown );
		return () => {
			window.removeEventListener( 'keydown', onKeyDown );
			lastFocusRef.current?.focus?.();
		};
	}, [ open, onClose ] );

	if ( ! open ) {
		return null;
	}

	return createPortal(
		<div className="bv-drawer-backdrop" role="presentation">
			<aside
				ref={ panelRef }
				className={ `bv-drawer${ className ? ` ${ className }` : '' }` }
				role="dialog"
				aria-modal="true"
				aria-labelledby="bv-drawer-title"
			>
				<header className="bv-drawer__header">
					<h2 id="bv-drawer-title" className="bv-drawer__title">
						{ title }
					</h2>
					<Button
						variant="ghost"
						size="sm"
						onClick={ onClose }
						aria-label={ __(
							'Close drawer',
							'coderembassy-bulk-variations-manager'
						) }
					>
						×
					</Button>
				</header>
				<div className="bv-drawer__body">{ children }</div>
			</aside>
		</div>,
		document.body
	);
}
