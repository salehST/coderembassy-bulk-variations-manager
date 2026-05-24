/**
 * Global keyboard shortcut registry.
 */
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../store';

const isEditableTarget = ( target ) => {
	if ( ! target || ! target.tagName ) {
		return false;
	}
	const tag = target.tagName.toLowerCase();
	return tag === 'input' || tag === 'textarea' || target.isContentEditable;
};

export function useShortcut( bindings = [] ) {
	const paletteOpen = useSelect(
		( select ) => select( STORE_NAME ).isPaletteOpen(),
		[]
	);
	const { togglePalette, toggleShortcuts } = useDispatch( STORE_NAME );

	useEffect( () => {
		const onKeyDown = ( event ) => {
			if ( isEditableTarget( event.target ) ) {
				return;
			}

			const key = event.key.toLowerCase();
			const mod = event.metaKey || event.ctrlKey;

			if ( mod && key === 'k' ) {
				event.preventDefault();
				togglePalette();
				return;
			}

			if ( event.key === '?' && event.shiftKey ) {
				event.preventDefault();
				toggleShortcuts();
			}

			bindings.forEach( ( binding ) => {
				if ( binding.key !== key ) {
					return;
				}
				if ( !! binding.mod !== mod ) {
					return;
				}
				if ( binding.shift && ! event.shiftKey ) {
					return;
				}
				event.preventDefault();
				binding.handler( event );
			} );
		};

		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ bindings, togglePalette, toggleShortcuts ] );

	return { paletteOpen };
}
