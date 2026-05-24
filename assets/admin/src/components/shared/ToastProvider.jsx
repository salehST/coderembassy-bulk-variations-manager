/**
 * Toast provider with useToast hook and auto-dismiss.
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useRef,
} from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import Toast from './Toast';

const ToastContext = createContext( null );
const AUTO_DISMISS_MS = 5000;

export function useToast() {
	const context = useContext( ToastContext );
	if ( ! context ) {
		throw new Error( 'useToast must be used within ToastProvider' );
	}
	return context;
}

export default function ToastProvider( { children } ) {
	const toasts = useSelect(
		( select ) => select( STORE_NAME ).getToasts(),
		[]
	);
	const { pushToast, dismissToast } = useDispatch( STORE_NAME );
	const timers = useRef( {} );

	const toast = useCallback(
		( message, options = {} ) => {
			const id = options.id || `toast-${ Date.now() }`;
			pushToast( {
				id,
				message,
				type: options.type || 'info',
				...options,
			} );
			return id;
		},
		[ pushToast ]
	);

	useEffect( () => {
		const prefersReduced = window.matchMedia?.(
			'(prefers-reduced-motion: reduce)'
		).matches;

		toasts.forEach( ( item ) => {
			if ( timers.current[ item.id ] ) {
				return;
			}
			timers.current[ item.id ] = window.setTimeout(
				() => {
					dismissToast( item.id );
					delete timers.current[ item.id ];
				},
				prefersReduced ? AUTO_DISMISS_MS * 2 : AUTO_DISMISS_MS
			);
		} );

		Object.keys( timers.current ).forEach( ( id ) => {
			if ( ! toasts.find( ( item ) => item.id === id ) ) {
				window.clearTimeout( timers.current[ id ] );
				delete timers.current[ id ];
			}
		} );
	}, [ toasts, dismissToast ] );

	const value = {
		toast,
		success: ( message, options ) =>
			toast( message, { ...options, type: 'success' } ),
		error: ( message, options ) =>
			toast( message, { ...options, type: 'error' } ),
		dismiss: dismissToast,
	};

	return (
		<ToastContext.Provider value={ value }>
			{ children }
			{ toasts.length > 0 && (
				<div
					className="bv-toast-stack"
					role="region"
					aria-label="Notifications"
				>
					{ toasts.map( ( item ) => (
						<Toast
							key={ item.id }
							toast={ item }
							onDismiss={ dismissToast }
						/>
					) ) }
				</div>
			) }
		</ToastContext.Provider>
	);
}
