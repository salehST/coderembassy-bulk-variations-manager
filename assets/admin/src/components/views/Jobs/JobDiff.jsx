/**
 * Job diff/review screen.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { jobs as jobsApi } from '../../../api/endpoints';
import { navigateTo } from '../../../navigation';
import { STORE_NAME } from '../../../store';
import Button from '../../shared/Button';
import ProgressBar from '../../shared/ProgressBar';
import CardIntro from '../BulkEditor/CardIntro';
import EmptyState from '../../shared/EmptyState';
import Skeleton from '../../shared/Skeleton';
import JobStatusBadge from './JobStatusBadge';
import {
	canReviewDiff,
	canRollback,
	getDiffPageMeta,
	getDisplayStatus,
	getJobProgressDisplay,
} from './jobUtils';
import { formatDiffCellValue, formatDiffFieldLabel } from './jobFieldLabels';

const getRows = ( job ) => {
	if ( Array.isArray( job?.changes ) ) {
		return job.changes;
	}
	const preview = job?.meta?.preview_changes;
	return Array.isArray( preview ) ? preview : [];
};

export default function JobDiff( { jobId } ) {
	const { pushToast } = useDispatch( STORE_NAME );
	const [ loading, setLoading ] = useState( true );
	const [ job, setJob ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const loadJob = useCallback( async () => {
		if ( ! jobId ) {
			setJob( null );
			setLoading( false );
			return;
		}
		setLoading( true );
		try {
			const data = await jobsApi.get( jobId );
			setJob( data );
		} catch ( err ) {
			void err;
			setJob( null );
		} finally {
			setLoading( false );
		}
	}, [ jobId ] );

	useEffect( () => {
		loadJob();
	}, [ loadJob ] );

	const rows = useMemo( () => getRows( job ), [ job ] );
	const meta = useMemo( () => getDiffPageMeta( job || {} ), [ job ] );
	const isPendingReview = canReviewDiff( job || {} );
	const rollbackAvailable = canRollback( job || {} );
	const status = getDisplayStatus( job || {} );
	const progressDisplay = getJobProgressDisplay( job || {} );

	const handleApply = async () => {
		if ( ! jobId || busy ) {
			return;
		}
		setBusy( true );
		try {
			const response = await jobsApi.apply( jobId );
			const updated = response?.job || ( await jobsApi.get( jobId ) );
			setJob( updated );
			pushToast( {
				id: `job-apply-${ Date.now() }`,
				type: 'success',
				message: __(
					'Changes applied to your store.',
					'coderembassy-bulk-variations-manager'
				),
			} );
			navigateTo( `jobs/${ jobId }/diff` );
		} catch ( err ) {
			pushToast( {
				id: `job-apply-err-${ Date.now() }`,
				type: 'error',
				message:
					err?.message ||
					__(
						'Unable to apply this job.',
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
			pushToast( {
				id: `job-discard-${ Date.now() }`,
				type: 'success',
				message: __(
					'Preview discarded.',
					'coderembassy-bulk-variations-manager'
				),
			} );
			navigateTo( 'jobs' );
		} catch ( err ) {
			pushToast( {
				id: `job-discard-err-${ Date.now() }`,
				type: 'error',
				message:
					err?.message ||
					__(
						'Unable to discard this preview.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		} finally {
			setBusy( false );
		}
	};

	const handleRollback = async () => {
		if ( ! jobId || busy || ! rollbackAvailable ) {
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

			pushToast( {
				id: `job-rollback-${ Date.now() }`,
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
				return;
			}
			loadJob();
		} catch ( err ) {
			pushToast( {
				id: `job-rollback-err-${ Date.now() }`,
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

	if ( loading ) {
		return (
			<div className="bv-jobs">
				<Skeleton variant="card" />
			</div>
		);
	}

	if ( ! job ) {
		return (
			<div className="bv-jobs">
				<section className="bv-card">
					<EmptyState
						title={ __(
							'Job not found',
							'coderembassy-bulk-variations-manager'
						) }
						description={ __(
							'This job no longer exists or could not be loaded.',
							'coderembassy-bulk-variations-manager'
						) }
						actionLabel={ __(
							'Back to jobs',
							'coderembassy-bulk-variations-manager'
						) }
						onAction={ () => navigateTo( 'jobs' ) }
					/>
				</section>
			</div>
		);
	}

	return (
		<div className="bv-jobs">
			<section className="bv-card bv-jobs__card">
				<CardIntro
					title={ meta.title }
					help={ meta.help }
					actions={
						<Button
							variant="default"
							onClick={ () => navigateTo( 'jobs' ) }
						>
							{ __(
								'Back to jobs',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					}
				/>
				<dl className="bv-jobs-detail__meta bv-jobs-detail__meta--compact">
					<div className="bv-jobs-detail__row">
						<dt>
							{ __(
								'Status',
								'coderembassy-bulk-variations-manager'
							) }
						</dt>
						<dd>
							<JobStatusBadge job={ job } />
						</dd>
					</div>
					<div className="bv-jobs-detail__row">
						<dt>
							{ __(
								'Progress',
								'coderembassy-bulk-variations-manager'
							) }
						</dt>
						<dd>
							<div className="bv-jobs-detail__progress">
								<ProgressBar
									value={ progressDisplay.percent }
									max={ 100 }
									className="bv-jobs-detail__progress-bar"
								/>
								{ progressDisplay.waiting ? (
									<span className="bv-jobs-detail__progress-meta">
										{ __(
											'Waiting to start',
											'coderembassy-bulk-variations-manager'
										) }
									</span>
								) : (
									<span className="bv-jobs-detail__progress-meta">
										<strong>
											{ progressDisplay.percent }%
										</strong>
										<span className="bv-jobs-detail__progress-ratio">
											{ progressDisplay.processed } /{ ' ' }
											{ progressDisplay.total }
										</span>
									</span>
								) }
							</div>
						</dd>
					</div>
				</dl>

				{ rows.length === 0 ? (
					<EmptyState
						title={ meta.emptyTitle }
						description={ meta.emptyDescription }
					/>
				) : (
					<div className="bv-jobs-table-wrap">
						<table className="bv-jobs-table">
							<thead>
								<tr>
									<th scope="col">
										{ __(
											'Variation',
											'coderembassy-bulk-variations-manager'
										) }
									</th>
									<th scope="col">
										{ __(
											'Field',
											'coderembassy-bulk-variations-manager'
										) }
									</th>
									<th scope="col">
										{ __(
											'Old value',
											'coderembassy-bulk-variations-manager'
										) }
									</th>
									<th scope="col">
										{ __(
											'New value',
											'coderembassy-bulk-variations-manager'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ rows.map( ( row, index ) => {
									const objectId =
										row?.variation_id ||
										row?.object_id ||
										'-';
									const field = String( row?.field || '' );
									const oldValue = formatDiffCellValue(
										field,
										row?.old_value
									);
									const newValue = formatDiffCellValue(
										field,
										row?.new_value
									);

									return (
										<tr
											key={ `${ objectId }-${ field }-${ index }` }
										>
											<td>{ objectId }</td>
											<td>
												{ formatDiffFieldLabel(
													field
												) }
											</td>
											<td>{ oldValue || '—' }</td>
											<td>{ newValue || '—' }</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</div>
				) }

				<div className="bv-jobs__footer">
					{ meta.showDiscard && (
						<Button
							variant="default"
							disabled={ busy }
							onClick={ handleDiscard }
						>
							{ __(
								'Discard',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					) }
					{ meta.showApply && isPendingReview && rows.length > 0 && (
						<Button
							variant="primary"
							disabled={ busy }
							onClick={ handleApply }
						>
							{ __(
								'Apply',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					) }
					{ rollbackAvailable && (
						<Button
							variant="default"
							disabled={ busy }
							onClick={ handleRollback }
						>
							{ __(
								'Rollback',
								'coderembassy-bulk-variations-manager'
							) }
						</Button>
					) }
					{ ! isPendingReview && status === 'completed' && (
						<p className="bv-muted">
							{ __(
								'This job is already completed.',
								'coderembassy-bulk-variations-manager'
							) }
						</p>
					) }
				</div>
			</section>
		</div>
	);
}
