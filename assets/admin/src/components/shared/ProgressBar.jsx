/**
 * Progress bar with optional indeterminate state.
 */
import { __ } from '@wordpress/i18n';

export default function ProgressBar( {
	value = 0,
	max = 100,
	label,
	indeterminate = false,
	className = '',
} ) {
	const pct =
		max > 0 ? Math.min( 100, Math.round( ( value / max ) * 100 ) ) : 0;
	const ariaLabel =
		label || __( 'Progress', 'coderembassy-bulk-variations-manager' );

	return (
		<div className={ `bv-progress${ className ? ` ${ className }` : '' }` }>
			{ label && (
				<div className="bv-progress__label" id="bv-progress-label">
					{ label }
				</div>
			) }
			<div
				className="bv-progress__track"
				role="progressbar"
				aria-label={ ariaLabel }
				aria-valuemin={ indeterminate ? undefined : 0 }
				aria-valuemax={ indeterminate ? undefined : max }
				aria-valuenow={ indeterminate ? undefined : value }
				aria-busy={ indeterminate || undefined }
				aria-labelledby={ label ? 'bv-progress-label' : undefined }
			>
				<div
					className={ `bv-progress__bar${
						indeterminate ? ' bv-progress__bar--indeterminate' : ''
					}` }
					style={ indeterminate ? undefined : { width: `${ pct }%` } }
				/>
			</div>
		</div>
	);
}
