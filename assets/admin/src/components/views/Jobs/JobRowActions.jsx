/**
 * Per-job action buttons for the Jobs list and detail views.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { jobs as jobsApi } from '../../../api/endpoints';
import { navigateTo } from '../../../navigation';
import Button from '../../shared/Button';
import {
	canDiscardPreview,
	canReviewDiff,
	canRollback,
	canViewAppliedChanges,
	hasJobChanges,
	isEmptyDiscardedPreview,
	isJobRolledBack,
	shouldShowListJobDetails,
} from './jobUtils';

export default function JobRowActions( {
	job,
	onRefresh,
	onToast,
	layout = 'table',
} ) {
	const [ busy, setBusy ] = useState( false );
	const jobId = Number( job?.id || 0 );
	const rawStatus = String( job?.status || '' );
	const isDetail = layout === 'detail';
	const actionSize = isDetail ? 'md' : 'sm';
	const controlVariant = isDetail ? 'default' : 'ghost';
	const cancelVariant = isDetail ? 'cancel' : 'ghost';
	const pendingReview = canReviewDiff( job );
	const previewHasChanges = pendingReview && hasJobChanges( job );
	const showListJobDetails = ! isDetail && shouldShowListJobDetails( job );
	const showListViewChanges = ! isDetail && canViewAppliedChanges( job );
	const showDiscardedLabel = ! isDetail && isEmptyDiscardedPreview( job );

	const runAction = async ( action, successMessage ) => {
		if ( ! jobId || busy ) {
			return;
		}
		setBusy( true );
		try {
			await action( jobId );
			onToast?.( { type: 'success', message: successMessage } );
			onRefresh?.();
		} catch ( err ) {
			onToast?.( {
				type: 'error',
				message:
					err?.message ||
					__(
						'Action failed. Please try again.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		} finally {
			setBusy( false );
		}
	};

	const handleDiscard = async () => {
		if ( ! jobId || busy ) {
			return;
		}
		setBusy( true );
		try {
			await jobsApi.discard( jobId );
			onToast?.( {
				type: 'success',
				message: __(
					'Preview discarded.',
					'coderembassy-bulk-variations-manager'
				),
			} );
			if ( isDetail ) {
				navigateTo( 'jobs' );
			} else {
				onRefresh?.();
			}
		} catch ( err ) {
			onToast?.( {
				type: 'error',
				message:
					err?.message ||
					__(
						'Unable to discard preview. Please try again.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		} finally {
			setBusy( false );
		}
	};

	const handleRollback = async () => {
		if ( ! jobId || busy ) {
			return;
		}
		setBusy( true );
		try {
			const response = await jobsApi.rollback( jobId );
			const rollbackJobId = Number(
				response?.rollback_job_id || response?.rollback_job?.id || 0
			);
			const rollbackStatus = String(
				response?.rollback_status ||
					response?.rollback_job?.status ||
					''
			);
			const isComplete =
				rollbackStatus === 'complete' || rollbackStatus === 'completed';

			onToast?.( {
				type: 'success',
				message: isComplete
					? __(
							'Rollback completed.',
							'coderembassy-bulk-variations-manager'
					  )
					: __(
							'Rollback started.',
							'coderembassy-bulk-variations-manager'
					  ),
			} );

			if ( rollbackJobId > 0 ) {
				navigateTo( `jobs/${ rollbackJobId }` );
			} else {
				onRefresh?.();
			}
		} catch ( err ) {
			onToast?.( {
				type: 'error',
				message:
					err?.message ||
					__(
						'Rollback failed. Please try again.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		} finally {
			setBusy( false );
		}
	};

	const viewDetails = () => navigateTo( `jobs/${ jobId }` );
	const viewDiff = () => navigateTo( `jobs/${ jobId }/diff` );

	return (
		<div
			className={
				isDetail
					? 'bv-jobs-actions bv-jobs-actions--detail'
					: 'bv-jobs-actions'
			}
		>
			{ ( rawStatus === 'running' || rawStatus === 'queued' ) && (
				<>
					{ showListJobDetails && (
						<Button
							size={ actionSize }
							variant={ controlVariant }
							onClick={ viewDetails }
						>
							{ __(
								'View details',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					) }
					<Button
						size={ actionSize }
						variant={ cancelVariant }
						disabled={ busy }
						onClick={ () =>
							runAction(
								jobsApi.cancel,
								__(
									'Job cancelled.',
									'coderembassy-bulk-variations-manager'
								)
							)
						}
					>
						{ __(
							'Cancel',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				</>
			) }

			{ rawStatus === 'paused' && (
				<>
					{ showListJobDetails && (
						<Button
							size={ actionSize }
							variant={ controlVariant }
							onClick={ viewDetails }
						>
							{ __(
								'View details',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					) }
					<Button
						size={ actionSize }
						variant={ cancelVariant }
						disabled={ busy }
						onClick={ () =>
							runAction(
								jobsApi.cancel,
								__(
									'Job cancelled.',
									'coderembassy-bulk-variations-manager'
								)
							)
						}
					>
						{ __(
							'Cancel',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				</>
			) }

			{ previewHasChanges && (
				<Button
					size={ actionSize }
					variant="primary"
					onClick={ viewDiff }
				>
					{ __(
						'View diff',
						'coderembassy-bulk-variations-manager'
					) }
				</Button>
			) }

			{ ! isDetail && pendingReview && ! previewHasChanges && (
				<Button
					size={ actionSize }
					variant={ controlVariant }
					onClick={ viewDetails }
				>
					{ __(
						'View details',
						'coderembassy-bulk-variations-manager'
					) }
				</Button>
			) }

			{ showListViewChanges && (
				<Button
					size={ actionSize }
					variant={ controlVariant }
					onClick={ viewDiff }
				>
					{ __(
						'View changes',
						'coderembassy-bulk-variations-manager'
					) }
				</Button>
			) }

			{ showListJobDetails &&
				rawStatus !== 'running' &&
				rawStatus !== 'queued' &&
				rawStatus !== 'paused' && (
					<Button
						size={ actionSize }
						variant={ controlVariant }
						onClick={ viewDetails }
					>
						{ __(
							'View details',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				) }

			{ isDetail && canViewAppliedChanges( job ) && (
				<Button
					size={ actionSize }
					variant={ controlVariant }
					onClick={ viewDiff }
				>
					{ __(
						'View changes',
						'coderembassy-bulk-variations-manager'
					) }
				</Button>
			) }

			{ canDiscardPreview( job ) && (
				<Button
					size={ actionSize }
					variant={ cancelVariant }
					disabled={ busy }
					onClick={ handleDiscard }
				>
					{ __( 'Discard', 'coderembassy-bulk-variations-manager' ) }
				</Button>
			) }

			{ canRollback( job ) && (
				<Button
					size={ actionSize }
					variant={ controlVariant }
					disabled={ busy }
					onClick={ handleRollback }
				>
					{ __( 'Rollback', 'coderembassy-bulk-variations-manager' ) }
				</Button>
			) }

			{ showDiscardedLabel && (
				<span className="bv-jobs-actions__note bv-muted">
					{ __(
						'Discarded',
						'coderembassy-bulk-variations-manager'
					) }
				</span>
			) }

			{ isDetail && isJobRolledBack( job ) && (
				<span className="bv-jobs-actions__note bv-muted">
					{ __(
						'Already rolled back',
						'coderembassy-bulk-variations-manager'
					) }
				</span>
			) }
		</div>
	);
}
