/**
 * Single job detail view.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { jobs as jobsApi } from '../../../api/endpoints';
import { navigateTo } from '../../../navigation';
import { STORE_NAME } from '../../../store';
import CardIntro from '../BulkEditor/CardIntro';
import Button from '../../shared/Button';
import ProgressBar from '../../shared/ProgressBar';
import Skeleton from '../../shared/Skeleton';
import JobRowActions from './JobRowActions';
import JobStatusBadge from './JobStatusBadge';
import {
	formatJobDate,
	formatJobType,
	canReviewDiff,
	getChangesCountLabel,
	getJobChangesCount,
	getJobProgressDisplay,
	isOrphanPreview,
	getJobUpdatedAt,
	isJobPollingActive,
	isJobStuckWithoutProgress,
} from './jobUtils';

const POLL_INTERVAL_MS = 1500;

export default function JobDetail( { jobId } ) {
	const { pushToast } = useDispatch( STORE_NAME );
	const [ initialLoading, setInitialLoading ] = useState( true );
	const [ job, setJob ] = useState( null );
	const [ error, setError ] = useState( '' );
	const loadJob = useCallback(
		async ( { background = false } = {} ) => {
			if ( ! jobId ) {
				return null;
			}
			if ( ! background ) {
				setInitialLoading( true );
				setError( '' );
			}
			try {
				const data = await jobsApi.get( jobId );
				setJob( data );
				return data;
			} catch ( err ) {
				if ( ! background ) {
					setJob( null );
					setError(
						err?.message ||
							__(
								'Unable to load this job.',
								'coderembassy-bulk-variations-manager'
							)
					);
				}
				return null;
			} finally {
				if ( ! background ) {
					setInitialLoading( false );
				}
			}
		},
		[ jobId ]
	);

	useEffect( () => {
		loadJob();
	}, [ loadJob ] );

	useEffect( () => {
		if ( ! jobId ) {
			return undefined;
		}

		let cancelled = false;
		let timer = null;

		const tick = async () => {
			if ( cancelled ) {
				return;
			}
			const data = await loadJob( { background: true } );
			if ( cancelled || ! data || ! isJobPollingActive( data ) ) {
				return;
			}
			timer = window.setTimeout( tick, POLL_INTERVAL_MS );
		};

		timer = window.setTimeout( tick, POLL_INTERVAL_MS );

		return () => {
			cancelled = true;
			if ( timer ) {
				window.clearTimeout( timer );
			}
		};
	}, [ jobId, loadJob ] );

	const handleToast = useCallback(
		( toast ) => {
			pushToast( {
				id: `job-detail-toast-${ Date.now() }`,
				...toast,
			} );
		},
		[ pushToast ]
	);

	const handleRefresh = useCallback( () => {
		loadJob( { background: true } );
	}, [ loadJob ] );

	if ( initialLoading ) {
		return (
			<div className="bv-jobs">
				<Skeleton variant="card" />
			</div>
		);
	}

	if ( error || ! job ) {
		return (
			<div className="bv-jobs">
				<section className="bv-card">
					<p className="bv-muted">{ error }</p>
					<Button
						variant="default"
						onClick={ () => navigateTo( 'jobs' ) }
					>
						{ __(
							'Back to jobs',
							'coderembassy-bulk-variations-manager'
						) }
					</Button>
				</section>
			</div>
		);
	}

	const changesCount = getJobChangesCount( job );
	const progressDisplay = getJobProgressDisplay( job );
	const meta = job.meta && typeof job.meta === 'object' ? job.meta : {};
	const showStuckHint = isJobStuckWithoutProgress( job );

	return (
		<div className="bv-jobs">
			<section className="bv-card bv-jobs__card">
				<CardIntro
					title={ `${ __(
						'Job',
						'coderembassy-bulk-variations-manager'
					) } #${ job.id }` }
					help={ formatJobType( job ) }
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

				<dl className="bv-jobs-detail__meta">
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
							{ showStuckHint && (
								<p className="bv-jobs-detail__stuck-hint">
									{ __(
										'This job has not reported progress yet. Check Action Scheduler or refresh the page.',
										'coderembassy-bulk-variations-manager'
									) }
								</p>
							) }
						</dd>
					</div>
					<div className="bv-jobs-detail__row">
						<dt>
							{ __(
								'Source',
								'coderembassy-bulk-variations-manager'
							) }
						</dt>
						<dd>{ job.source || meta.source || '-' }</dd>
					</div>
					<div className="bv-jobs-detail__row">
						<dt>
							{ __(
								'Created',
								'coderembassy-bulk-variations-manager'
							) }
						</dt>
						<dd>{ formatJobDate( job.created_at ) }</dd>
					</div>
					<div className="bv-jobs-detail__row">
						<dt>
							{ __(
								'Updated',
								'coderembassy-bulk-variations-manager'
							) }
						</dt>
						<dd>{ formatJobDate( getJobUpdatedAt( job ) ) }</dd>
					</div>
					{ job.started_at && (
						<div className="bv-jobs-detail__row">
							<dt>
								{ __(
									'Started',
									'coderembassy-bulk-variations-manager'
								) }
							</dt>
							<dd>{ formatJobDate( job.started_at ) }</dd>
						</div>
					) }
					{ job.completed_at && (
						<div className="bv-jobs-detail__row">
							<dt>
								{ __(
									'Completed',
									'coderembassy-bulk-variations-manager'
								) }
							</dt>
							<dd>{ formatJobDate( job.completed_at ) }</dd>
						</div>
					) }
					{ meta.product_id > 0 && (
						<div className="bv-jobs-detail__row">
							<dt>
								{ __(
									'Product ID',
									'coderembassy-bulk-variations-manager'
								) }
							</dt>
							<dd>{ meta.product_id }</dd>
						</div>
					) }
					{ changesCount > 0 && (
						<div className="bv-jobs-detail__row">
							<dt>{ getChangesCountLabel( job ) }</dt>
							<dd>{ changesCount }</dd>
						</div>
					) }
				</dl>

				{ job.error_log && (
					<div className="bv-jobs-detail__errors bv-card bv-card--nested">
						<h3>
							{ __(
								'Errors',
								'coderembassy-bulk-variations-manager'
							) }
						</h3>
						<pre className="bv-jobs-detail__error-log">
							{ job.error_log }
						</pre>
					</div>
				) }

				{ canReviewDiff( job ) && isOrphanPreview( job ) && (
					<p className="bv-jobs-detail__orphan-hint bv-muted">
						{ __(
							'No stored preview changes. Discard this preview and create a new one from the Bulk Editor.',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				) }

				<div className="bv-jobs-detail__actions bv-jobs-detail__actions--toolbar">
					<JobRowActions
						job={ job }
						layout="detail"
						onRefresh={ handleRefresh }
						onToast={ handleToast }
					/>
				</div>
			</section>
		</div>
	);
}
