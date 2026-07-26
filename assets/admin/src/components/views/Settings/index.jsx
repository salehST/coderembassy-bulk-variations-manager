/**
 * Free settings screen.
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { settings as settingsApi } from '../../../api/endpoints';
import { STORE_NAME } from '../../../store';
import { persistTheme } from '../../../hooks/useTheme';
import Button from '../../shared/Button';
import Skeleton from '../../shared/Skeleton';

const THEME_OPTIONS = [
	{
		value: 'auto',
		label: __( 'Auto', 'coderembassy-bulk-variations-manager' ),
	},
	{
		value: 'light',
		label: __( 'Light', 'coderembassy-bulk-variations-manager' ),
	},
	{
		value: 'dark',
		label: __( 'Dark', 'coderembassy-bulk-variations-manager' ),
	},
];

const JOB_PAGE_OPTIONS = [ 20, 50, 100 ];

const normalizeSettings = ( data = {} ) => ( {
	theme: [ 'auto', 'light', 'dark' ].includes( data.theme )
		? data.theme
		: 'auto',
	default_product_id: Number( data.default_product_id || 0 ),
	jobs_per_page: JOB_PAGE_OPTIONS.includes( Number( data.jobs_per_page ) )
		? Number( data.jobs_per_page )
		: 50,
	remove_data_on_uninstall: !! data.remove_data_on_uninstall,
} );

export default function Settings() {
	const globals = useSelect(
		( select ) => select( STORE_NAME ).getGlobals(),
		[]
	);
	const { setTheme, pushToast } = useDispatch( STORE_NAME );
	const [ values, setValues ] = useState( () =>
		normalizeSettings( globals?.settings || {} )
	);
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;

		const load = async () => {
			setLoading( true );
			setError( '' );
			try {
				const response = await settingsApi.get();
				if ( ! cancelled ) {
					setValues( normalizeSettings( response ) );
				}
			} catch ( err ) {
				if ( ! cancelled ) {
					setError(
						err?.message ||
							__(
								'Settings could not be loaded.',
								'coderembassy-bulk-variations-manager'
							)
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		};

		load();
		return () => {
			cancelled = true;
		};
	}, [] );

	const updateValue = ( key, value ) => {
		setValues( ( current ) => ( {
			...current,
			[ key ]: value,
		} ) );
	};

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setSaving( true );
		setError( '' );

		try {
			const response = await settingsApi.update( values );
			const normalized = normalizeSettings( response );
			setValues( normalized );
			setTheme( normalized.theme );
			persistTheme( normalized.theme );
			pushToast( {
				id: `settings-saved-${ Date.now() }`,
				type: 'success',
				message: __(
					'Settings saved.',
					'coderembassy-bulk-variations-manager'
				),
			} );
		} catch ( err ) {
			setError(
				err?.message ||
					__(
						'Settings could not be saved.',
						'coderembassy-bulk-variations-manager'
					)
			);
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <Skeleton variant="card" />;
	}

	return (
		<form className="bv-settings" onSubmit={ handleSubmit }>
			<section className="bv-card bv-settings__hero">
				<div>
					<h2>
						{ __(
							'Settings',
							'coderembassy-bulk-variations-manager'
						) }
					</h2>
					<p className="bv-muted">
						{ __(
							'Control the defaults used by the editor, CSV import, and jobs screens.',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				</div>
				<Button variant="primary" type="submit" disabled={ saving }>
					{ saving
						? __(
								'Saving…',
								'coderembassy-bulk-variations-manager'
						  )
						: __(
								'Save settings',
								'coderembassy-bulk-variations-manager'
						  ) }
				</Button>
			</section>

			{ error && (
				<div className="bv-settings__notice" role="alert">
					{ error }
				</div>
			) }

			<div className="bv-settings__grid">
				<section className="bv-card bv-settings-card">
					<h3>
						{ __(
							'Appearance',
							'coderembassy-bulk-variations-manager'
						) }
					</h3>
					<div className="bv-settings-field">
						<label htmlFor="bv-setting-theme">
							{ __(
								'Theme',
								'coderembassy-bulk-variations-manager'
							) }
						</label>
						<select
							id="bv-setting-theme"
							value={ values.theme }
							onChange={ ( event ) =>
								updateValue( 'theme', event.target.value )
							}
						>
							{ THEME_OPTIONS.map( ( option ) => (
								<option
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
								</option>
							) ) }
						</select>
					</div>
				</section>

				<section className="bv-card bv-settings-card">
					<h3>
						{ __(
							'Workflow defaults',
							'coderembassy-bulk-variations-manager'
						) }
					</h3>
					<div className="bv-settings-field">
						<label htmlFor="bv-setting-default-product">
							{ __(
								'Default product ID for CSV import',
								'coderembassy-bulk-variations-manager'
							) }
						</label>
						<input
							id="bv-setting-default-product"
							type="number"
							min="0"
							value={ values.default_product_id || '' }
							onChange={ ( event ) =>
								updateValue(
									'default_product_id',
									Number( event.target.value || 0 )
								)
							}
							placeholder="18"
						/>
					</div>
					<div className="bv-settings-field">
						<label htmlFor="bv-setting-jobs-per-page">
							{ __(
								'Jobs per page',
								'coderembassy-bulk-variations-manager'
							) }
						</label>
						<select
							id="bv-setting-jobs-per-page"
							value={ values.jobs_per_page }
							onChange={ ( event ) =>
								updateValue(
									'jobs_per_page',
									Number( event.target.value )
								)
							}
						>
							{ JOB_PAGE_OPTIONS.map( ( option ) => (
								<option key={ option } value={ option }>
									{ option }
								</option>
							) ) }
						</select>
					</div>
				</section>

				<section className="bv-card bv-settings-card">
					<h3>
						{ __(
							'Plugin data',
							'coderembassy-bulk-variations-manager'
						) }
					</h3>
					<div className="bv-settings-toggle">
						<label
							className="bv-toggle"
							htmlFor="bv-setting-remove-data"
						>
							<input
								id="bv-setting-remove-data"
								className="bv-toggle__input"
								type="checkbox"
								aria-labelledby="bv-setting-remove-data-label"
								checked={ values.remove_data_on_uninstall }
								onChange={ ( event ) =>
									updateValue(
										'remove_data_on_uninstall',
										event.target.checked
									)
								}
							/>
							<span
								className="bv-toggle__track"
								aria-hidden="true"
							>
								<span className="bv-toggle__thumb" />
							</span>
						</label>
						<label
							id="bv-setting-remove-data-label"
							htmlFor="bv-setting-remove-data"
						>
							<strong>
								{ __(
									'Remove plugin data on uninstall',
									'coderembassy-bulk-variations-manager'
								) }
							</strong>
							<small>
								{ __(
									'When enabled, uninstall removes plugin job tables, plugin options, and plugin user preferences.',
									'coderembassy-bulk-variations-manager'
								) }
							</small>
						</label>
					</div>
				</section>
			</div>
		</form>
	);
}
