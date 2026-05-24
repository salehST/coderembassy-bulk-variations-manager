/**
 * Sticky apply bar — preview job then route to diff drawer.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from '../../shared/Button';
import { jobs } from '../../../api/endpoints';
import { navigateTo } from '../../../navigation';

export default function ApplyBar( {
	pendingChanges = [],
	productId,
	onBeforePreview,
	onDiscard,
} ) {
	const [ submitting, setSubmitting ] = useState( false );
	const count = pendingChanges.length;

	const handlePreviewApprove = async () => {
		if ( submitting ) {
			return;
		}

		const nextChanges =
			typeof onBeforePreview === 'function'
				? await onBeforePreview()
				: pendingChanges;
		const changes =
			Array.isArray( nextChanges ) && nextChanges.length > 0
				? nextChanges
				: pendingChanges;

		if ( ! Array.isArray( changes ) || changes.length === 0 ) {
			return;
		}

		setSubmitting( true );
		try {
			const job = await jobs.create( {
				type: 'bulk_edit',
				source: 'editor',
				product_id: productId,
				total_items: changes.length,
				dry: 1,
				changes,
			} );
			const jobId = job?.id || job?.job_id;
			if ( jobId ) {
				navigateTo( `jobs/${ jobId }/diff` );
			}
		} catch ( err ) {
			void err;
		} finally {
			setSubmitting( false );
		}
	};

	if ( ! count && ! submitting ) {
		return null;
	}

	return (
		<div className="bv-apply-bar" role="region" aria-live="polite">
			<span>
				{ count > 0
					? `${ count } ${ __(
							'changes pending',
							'coderembassy-bulk-variations-manager'
					  ) }`
					: __(
							'Review grid changes before applying.',
							'coderembassy-bulk-variations-manager'
					  ) }
			</span>
			<div className="bv-apply-bar__actions">
				<Button variant="ghost" onClick={ onDiscard }>
					{ __( 'Discard', 'coderembassy-bulk-variations-manager' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ submitting }
					onClick={ handlePreviewApprove }
				>
					{ __(
						'Preview & Approve',
						'coderembassy-bulk-variations-manager'
					) }
				</Button>
			</div>
		</div>
	);
}
