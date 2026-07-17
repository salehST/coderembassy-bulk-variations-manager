/**
 * Job display helpers for the Jobs view.
 */
import { __ } from '@wordpress/i18n';

export const ACTIVE_JOB_STATUSES = [ 'queued', 'running', 'processing' ];

export const ROLLBACK_JOB_TYPES = [ 'bulk_edit', 'import', 'generate' ];

/**
 * @param {Object} job Job row from API.
 * @return {string} Normalized status slug for UI.
 */
export function getDisplayStatus( job ) {
	if ( ! job || typeof job !== 'object' ) {
		return '';
	}

	if (
		( job.review_status === 'pending_review' ||
			job.status === 'preview' ) &&
		job.status !== 'cancelled' &&
		job.review_status !== 'discarded' &&
		job.review_status !== 'applied'
	) {
		return 'pending_review';
	}

	if ( job.status === 'complete' ) {
		return 'completed';
	}

	return String( job.status || '' );
}

/**
 * @param {string} status Display status slug.
 * @return {{ status: string, label: string }} Badge status and label.
 */
export function getStatusBadge( status ) {
	const labels = {
		queued: __( 'Queued', 'coderembassy-bulk-variations-manager' ),
		running: __( 'Running', 'coderembassy-bulk-variations-manager' ),
		processing: __( 'Running', 'coderembassy-bulk-variations-manager' ),
		completed: __( 'Completed', 'coderembassy-bulk-variations-manager' ),
		complete: __( 'Completed', 'coderembassy-bulk-variations-manager' ),
		failed: __( 'Failed', 'coderembassy-bulk-variations-manager' ),
		cancelled: __( 'Cancelled', 'coderembassy-bulk-variations-manager' ),
		paused: __( 'Paused', 'coderembassy-bulk-variations-manager' ),
		preview: __( 'Preview', 'coderembassy-bulk-variations-manager' ),
		pending_review: __(
			'Pending review',
			'coderembassy-bulk-variations-manager'
		),
		rolled_back: __(
			'Rolled back',
			'coderembassy-bulk-variations-manager'
		),
	};

	const badgeStatus = {
		queued: 'muted',
		running: 'info',
		processing: 'info',
		completed: 'ok',
		complete: 'ok',
		failed: 'err',
		cancelled: 'muted',
		paused: 'warn',
		preview: 'info',
		pending_review: 'warn',
		rolled_back: 'muted',
	};

	return {
		status: badgeStatus[ status ] || 'muted',
		label: labels[ status ] || status,
	};
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when the job is actively processing.
 */
export function isJobActive( job ) {
	return ACTIVE_JOB_STATUSES.includes( String( job?.status || '' ) );
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when the source job was already rolled back.
 */
export function isJobRolledBack( job ) {
	return String( job?.status || '' ) === 'rolled_back';
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when rollback is available.
 */
export function canRollback( job ) {
	const status = getDisplayStatus( job );
	const type = String( job?.type || '' );
	return (
		status === 'completed' &&
		! isJobRolledBack( job ) &&
		ROLLBACK_JOB_TYPES.includes( type ) &&
		( ( job?.processed || 0 ) > 0 || hasJobChanges( job ) )
	);
}

/**
 * @param {Object} job Job row.
 * @return {number} Number of change rows on the job.
 */
export function getJobChangesCount( job ) {
	return Array.isArray( job?.changes ) ? job.changes.length : 0;
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when the job has change rows to show on the diff screen.
 */
export function hasJobChanges( job ) {
	if ( getJobChangesCount( job ) > 0 ) {
		return true;
	}

	const meta = job?.meta && typeof job.meta === 'object' ? job.meta : {};
	const preview = meta.preview_changes;

	return Array.isArray( preview ) && preview.length > 0;
}

/**
 * @param {Object} job Job row.
 * @return {string} Review status from the job row or meta.
 */
export function getJobReviewStatus( job ) {
	const meta = job?.meta && typeof job.meta === 'object' ? job.meta : {};

	return String( job?.review_status || meta.review_status || '' );
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when the job was a discarded pending-review preview.
 */
export function isDiscardedPreview( job ) {
	return getJobReviewStatus( job ) === 'discarded';
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when a cancelled preview was discarded with nothing to inspect.
 */
export function isEmptyDiscardedPreview( job ) {
	return (
		getDisplayStatus( job ) === 'cancelled' &&
		isDiscardedPreview( job ) &&
		! hasJobChanges( job )
	);
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when the jobs list should show a View details action.
 */
export function shouldShowListJobDetails( job ) {
	const rawStatus = String( job?.status || '' );

	if ( ACTIVE_JOB_STATUSES.includes( rawStatus ) || rawStatus === 'paused' ) {
		return true;
	}

	const displayStatus = getDisplayStatus( job );

	if ( displayStatus === 'completed' || isEmptyDiscardedPreview( job ) ) {
		return false;
	}

	if ( displayStatus === 'failed' ) {
		return true;
	}

	if ( displayStatus === 'cancelled' ) {
		if ( job?.error_log ) {
			return true;
		}

		return hasJobChanges( job );
	}

	return false;
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when a completed job should offer "View changes".
 */
export function canViewAppliedChanges( job ) {
	return getDisplayStatus( job ) === 'completed' && hasJobChanges( job );
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when diff review is available.
 */
export function canReviewDiff( job ) {
	return getDisplayStatus( job ) === 'pending_review';
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when a pending-review preview can be discarded.
 */
export function canDiscardPreview( job ) {
	return canReviewDiff( job );
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when a pending-review preview has no stored change rows.
 */
export function isOrphanPreview( job ) {
	return canReviewDiff( job ) && ! hasJobChanges( job );
}

/**
 * @param {Object} job Job row.
 * @return {string} Human-readable job type label.
 */
export function formatJobType( job ) {
	const type = String( job?.type || '' ).replace( /_/g, ' ' );
	return type.charAt( 0 ).toUpperCase() + type.slice( 1 );
}

/**
 * @param {string|null|undefined} raw ISO or MySQL datetime.
 * @return {string} Formatted date or em dash.
 */
export function formatJobDate( raw ) {
	if ( ! raw ) {
		return '—';
	}
	const date = new Date( raw );
	if ( Number.isNaN( date.getTime() ) ) {
		return String( raw );
	}
	return date.toLocaleString();
}

/**
 * @param {Object} job Job row.
 * @return {string|null} Best available updated timestamp.
 */
export function getJobUpdatedAt( job ) {
	if ( canReviewDiff( job ) ) {
		return job?.created_at || null;
	}

	return job?.completed_at || job?.started_at || job?.created_at || null;
}

/**
 * @param {Object} job Job row.
 * @return {number} Progress percentage from 0–100.
 */
export function getJobProgressPercent( job ) {
	if ( getDisplayStatus( job ) === 'completed' ) {
		return 100;
	}

	const progress = Number( job?.progress );
	const total = Number( job?.total_items || 0 );
	const processed = Number( job?.processed || 0 );
	const computed =
		total > 0
			? Math.min( 100, Math.round( ( processed / total ) * 100 ) )
			: 0;

	// Some completed jobs can keep progress=0 even though processed/total is done.
	// Prefer computed progress whenever it indicates forward movement.
	if ( ! Number.isNaN( progress ) && progress >= 0 && progress <= 100 ) {
		if ( computed > 0 && progress < computed ) {
			return computed;
		}
		return progress;
	}

	if ( total <= 0 ) {
		return 0;
	}

	return computed;
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True while the job should be polled for updates.
 */
export function isJobPollingActive( job ) {
	return ACTIVE_JOB_STATUSES.includes( String( job?.status || '' ) );
}

/**
 * @param {Object} job Job row.
 * @return {boolean} True when running with no progress for over 60 seconds.
 */
export function isJobStuckWithoutProgress( job ) {
	if ( String( job?.status || '' ) !== 'running' ) {
		return false;
	}
	if ( Number( job?.processed || 0 ) > 0 ) {
		return false;
	}
	const started = job?.started_at || getJobUpdatedAt( job );
	if ( ! started ) {
		return false;
	}
	const ts = new Date( started ).getTime();
	if ( Number.isNaN( ts ) ) {
		return false;
	}
	return Date.now() - ts > 60000;
}

/**
 * @param {Object} job Job row.
 * @return {string} Label for the changes count row on job detail.
 */
export function getChangesCountLabel( job ) {
	if ( canReviewDiff( job ) ) {
		return __( 'Pending changes', 'coderembassy-bulk-variations-manager' );
	}

	const status = getDisplayStatus( job );
	if ( status === 'completed' ) {
		return __( 'Applied changes', 'coderembassy-bulk-variations-manager' );
	}

	if ( String( job?.type || '' ) === 'rollback' ) {
		return __( 'Rollback changes', 'coderembassy-bulk-variations-manager' );
	}

	return __( 'Change rows', 'coderembassy-bulk-variations-manager' );
}

/**
 * @param {Object} job Job row.
 * @return {{ title: string, help: string, emptyTitle: string, emptyDescription: string, showApply: boolean, showDiscard: boolean }} Diff page copy for the job state.
 */
export function getDiffPageMeta( job ) {
	if ( canReviewDiff( job ) ) {
		if ( isOrphanPreview( job ) ) {
			return {
				title: __(
					'No stored preview changes',
					'coderembassy-bulk-variations-manager'
				),
				help: __(
					'This preview was created before change rows were stored, so it cannot be applied. Discard it and create a new preview from the Bulk Editor.',
					'coderembassy-bulk-variations-manager'
				),
				emptyTitle: __(
					'No stored preview changes',
					'coderembassy-bulk-variations-manager'
				),
				emptyDescription: __(
					'This preview was created before change rows were stored, so it cannot be applied. Discard it and create a new preview from the Bulk Editor.',
					'coderembassy-bulk-variations-manager'
				),
				showApply: false,
				showDiscard: true,
			};
		}

		const reviewId = Number( job?.id || 0 );

		return {
			title: `${ __(
				'Review changes',
				'coderembassy-bulk-variations-manager'
			) } #${ reviewId }`,
			help: __(
				'Review staged changes before they are written to your store. Apply to run the job, or discard the preview without applying.',
				'coderembassy-bulk-variations-manager'
			),
			emptyTitle: __(
				'No changes to preview',
				'coderembassy-bulk-variations-manager'
			),
			emptyDescription: __(
				'This job has no stored change rows yet. If you just created a preview job, wait a moment and refresh the job details page.',
				'coderembassy-bulk-variations-manager'
			),
			showApply: true,
			showDiscard: true,
		};
	}

	if ( getDisplayStatus( job ) === 'completed' ) {
		const completedId = Number( job?.id || 0 );

		return {
			title: `${ __(
				'Applied changes',
				'coderembassy-bulk-variations-manager'
			) } #${ completedId }`,
			help: __(
				'These values were written when the job completed. Apply is not available for finished jobs.',
				'coderembassy-bulk-variations-manager'
			),
			emptyTitle: __(
				'No applied changes were recorded for this job',
				'coderembassy-bulk-variations-manager'
			),
			emptyDescription: __(
				'The job finished without stored change rows in the history table.',
				'coderembassy-bulk-variations-manager'
			),
			showApply: false,
			showDiscard: false,
		};
	}

	if ( String( job?.type || '' ) === 'rollback' ) {
		const rollbackId = Number( job?.id || 0 );

		return {
			title: `${ __(
				'Rollback changes',
				'coderembassy-bulk-variations-manager'
			) } #${ rollbackId }`,
			help: __(
				'Inverse deltas applied when this rollback job ran.',
				'coderembassy-bulk-variations-manager'
			),
			emptyTitle: __(
				'No rollback changes were recorded',
				'coderembassy-bulk-variations-manager'
			),
			emptyDescription: __(
				'This rollback job has no stored change rows.',
				'coderembassy-bulk-variations-manager'
			),
			showApply: false,
			showDiscard: false,
		};
	}

	const changeId = Number( job?.id || 0 );

	return {
		title: `${ __(
			'Changes',
			'coderembassy-bulk-variations-manager'
		) } #${ changeId }`,
		help: __(
			'Change rows recorded for this job.',
			'coderembassy-bulk-variations-manager'
		),
		emptyTitle: __(
			'No changes recorded',
			'coderembassy-bulk-variations-manager'
		),
		emptyDescription: __(
			'This job has no stored change rows.',
			'coderembassy-bulk-variations-manager'
		),
		showApply: false,
		showDiscard: false,
	};
}

/**
 * @param {Object} job Job row.
 * @return {{ percent: number, processed: number, total: number, waiting: boolean }} Progress label parts for UI.
 */
export function getJobProgressDisplay( job ) {
	const total = Number( job?.total_items || 0 );
	const rawProcessed = Number( job?.processed || 0 );
	const processed =
		getDisplayStatus( job ) === 'completed' && total > 0
			? Math.max( rawProcessed, total )
			: rawProcessed;
	const percent = getJobProgressPercent( job );

	return {
		percent,
		processed,
		total,
		waiting: total <= 0,
	};
}
