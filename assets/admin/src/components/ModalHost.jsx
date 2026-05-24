/**
 * Modal host — tour and importer stubs; extensible for future modals.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { closeSmall } from '@wordpress/icons';
import { STORE_NAME } from '../store';
import Button from './shared/Button';

const MODAL_COPY = {
	tour: {
		title: __( 'Product tour', 'coderembassy-bulk-variations-manager' ),
		body: __(
			'The guided tour will launch here in a future update.',
			'coderembassy-bulk-variations-manager'
		),
	},
	importer: {
		title: __( 'CSV import', 'coderembassy-bulk-variations-manager' ),
		body: __(
			'The CSV importer modal will open here in a future update.',
			'coderembassy-bulk-variations-manager'
		),
	},
};

export default function ModalHost() {
	const modals = useSelect(
		( select ) => select( STORE_NAME ).getModals(),
		[]
	);
	const { closeModal } = useDispatch( STORE_NAME );
	const keys = Object.keys( modals );

	useEffect( () => {
		if ( ! keys.length ) {
			return undefined;
		}

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				keys.forEach( ( key ) => closeModal( key ) );
			}
		};

		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ keys, closeModal ] );

	if ( ! keys.length ) {
		return null;
	}

	return (
		<>
			{ keys.map( ( key ) => {
				const copy = MODAL_COPY[ key ] || {
					title: key,
					body: '',
				};

				return (
					<div
						key={ key }
						className="bv-modal-backdrop"
						role="presentation"
						onClick={ ( event ) => {
							if ( event.target === event.currentTarget ) {
								closeModal( key );
							}
						} }
					>
						<div
							className="bv-modal"
							role="dialog"
							aria-modal="true"
							aria-labelledby={ `bv-modal-${ key }-title` }
						>
							<div className="bv-modal__header">
								<h2
									id={ `bv-modal-${ key }-title` }
									className="bv-modal__title"
								>
									{ copy.title }
								</h2>
								<button
									type="button"
									className="bv-topbar__icon-btn"
									onClick={ () => closeModal( key ) }
									aria-label={ __(
										'Close',
										'coderembassy-bulk-variations-manager'
									) }
								>
									<Icon icon={ closeSmall } size={ 20 } />
								</button>
							</div>
							{ copy.body && (
								<p className="bv-muted">{ copy.body }</p>
							) }
							<div className="bv-modal__actions">
								<Button onClick={ () => closeModal( key ) }>
									{ __(
										'Close',
										'coderembassy-bulk-variations-manager'
									) }
								</Button>
							</div>
						</div>
					</div>
				);
			} ) }
		</>
	);
}
