/**
 * Promise-based confirm dialog.
 */
import { createPortal, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from './Button';

let pending = null;
let setModalState = null;

export default function ConfirmModal() {
	const [ state, setState ] = useState( null );
	const confirmRef = useRef( null );

	useEffect( () => {
		setModalState = setState;
		return () => {
			setModalState = null;
		};
	}, [] );

	useEffect( () => {
		if ( state ) {
			confirmRef.current?.focus();
		}
	}, [ state ] );

	useEffect( () => {
		if ( ! state ) {
			return undefined;
		}

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				resolveModal( false );
			}
		};

		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ state ] );

	if ( ! state ) {
		return null;
	}

	const {
		title,
		body,
		confirmLabel = __( 'Confirm', 'coderembassy-bulk-variations-manager' ),
		cancelLabel = __( 'Cancel', 'coderembassy-bulk-variations-manager' ),
		destructive = false,
	} = state;

	return createPortal(
		<div className="bv-modal-backdrop" role="presentation">
			<div
				className="bv-modal bv-modal--confirm"
				role="dialog"
				aria-modal="true"
				aria-labelledby="bv-confirm-title"
				aria-describedby="bv-confirm-body"
			>
				<h2 id="bv-confirm-title" className="bv-modal__title">
					{ title }
				</h2>
				{ body && (
					<div id="bv-confirm-body" className="bv-modal__body">
						{ body }
					</div>
				) }
				<div className="bv-modal__actions" role="group">
					<Button onClick={ () => resolveModal( false ) }>
						{ cancelLabel }
					</Button>
					<Button
						ref={ confirmRef }
						variant={ destructive ? 'danger' : 'primary' }
						onClick={ () => resolveModal( true ) }
					>
						{ confirmLabel }
					</Button>
				</div>
			</div>
		</div>,
		document.body
	);
}

function resolveModal( confirmed ) {
	if ( ! pending ) {
		return;
	}
	const { resolve } = pending;
	pending = null;
	setModalState?.( null );
	resolve( confirmed );
}

ConfirmModal.show = function show( options ) {
	return new Promise( ( resolve, reject ) => {
		pending = { resolve, reject };
		setModalState?.( options );
	} );
};
