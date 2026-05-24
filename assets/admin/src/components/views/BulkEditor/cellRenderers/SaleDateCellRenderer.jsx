/**
 * Sale from / sale to display with optional schedule hints.
 */
import { __ } from '@wordpress/i18n';
import { getSaleDateScheduleHint } from '../../../../utils/saleDateUtils';

const HINT_LABELS = {
	expired: __(
		'Expired — sale price is stored but not active',
		'coderembassy-bulk-variations-manager'
	),
	scheduled: __(
		'Scheduled — sale has not started yet',
		'coderembassy-bulk-variations-manager'
	),
};

function hintTitle( hint ) {
	if ( ! hint ) {
		return '';
	}
	if ( hint.type === 'expired' ) {
		return HINT_LABELS.expired;
	}
	if ( hint.type === 'future' ) {
		return HINT_LABELS.scheduled;
	}
	return '';
}

function hintShortLabel( hint ) {
	if ( ! hint ) {
		return '';
	}
	if ( hint.type === 'expired' ) {
		return __( 'Expired', 'coderembassy-bulk-variations-manager' );
	}
	if ( hint.type === 'future' ) {
		return __( 'Scheduled', 'coderembassy-bulk-variations-manager' );
	}
	return '';
}

export default function SaleDateCellRenderer( params ) {
	const field = params.colDef?.field;
	const raw = params.value ?? params.data?.[ field ];
	const display = raw ? String( raw ).slice( 0, 10 ) : '';

	if ( ! display ) {
		return <span className="bv-cell-placeholder">-</span>;
	}

	const hint = getSaleDateScheduleHint( field, display );

	return (
		<span className="bv-sale-date-cell">
			<span className="bv-sale-date-cell__value">{ display }</span>
			{ hint ? (
				<span
					className={ `bv-sale-date-hint bv-sale-date-hint--${ hint.type }` }
					title={ hintTitle( hint ) }
				>
					{ hintShortLabel( hint ) }
				</span>
			) : null }
		</span>
	);
}
