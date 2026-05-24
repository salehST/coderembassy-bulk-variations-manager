/**
 * Dashboard — hero, KPI tiles, quick start, time saved, and recent jobs.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../../store';
import { jobs } from '../../api/endpoints';
import { navigateTo } from '../Router';
import {
	canRollback,
	getDisplayStatus,
	getJobUpdatedAt,
	getStatusBadge,
} from './Jobs/jobUtils';
import Badge from '../shared/Badge';
import Button from '../shared/Button';
import EmptyState from '../shared/EmptyState';
import Skeleton from '../shared/Skeleton';
import MiniBar from '../shared/Charts/MiniBar';

const MINUTES_PER_ROW = 2;
const RUNNING_STATUSES = [ 'running', 'queued', 'processing' ];

const QUICK_START_STEPS = [
	__(
		'Open the bulk editor and pick a variable product.',
		'coderembassy-bulk-variations-manager'
	),
	__(
		'Edit prices, stock, or attributes across variations in one grid.',
		'coderembassy-bulk-variations-manager'
	),
	__(
		'Apply changes and track the job from the Jobs screen.',
		'coderembassy-bulk-variations-manager'
	),
];

const isWithinDays = ( dateString, days ) => {
	if ( ! dateString ) {
		return false;
	}
	const date = new Date( dateString );
	if ( Number.isNaN( date.getTime() ) ) {
		return false;
	}
	const cutoff = Date.now() - days * 24 * 60 * 60 * 1000;
	return date.getTime() >= cutoff;
};

const isThisMonth = ( dateString ) => {
	if ( ! dateString ) {
		return false;
	}
	const date = new Date( dateString );
	const now = new Date();
	return (
		date.getFullYear() === now.getFullYear() &&
		date.getMonth() === now.getMonth()
	);
};

const estimateMinutesSaved = ( jobList ) =>
	jobList.reduce( ( total, job ) => {
		if ( getDisplayStatus( job ) !== 'completed' ) {
			return total;
		}
		const rows = Number( job.total_items || job.meta?.total_items || 0 );
		return total + Math.max( rows, 1 ) * MINUTES_PER_ROW;
	}, 0 );

const jobBadgeStatus = ( status ) => {
	if ( status === 'failed' ) {
		return 'err';
	}
	if ( status === 'completed' ) {
		return 'ok';
	}
	return 'info';
};

export default function Dashboard() {
	const { openModal, pushToast } = useDispatch( STORE_NAME );

	const handleRollback = async ( job ) => {
		const jobId = Number( job?.id || 0 );
		if ( ! jobId ) {
			return;
		}
		try {
			const response = await jobs.rollback( jobId );
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
				id: `dashboard-rollback-${ Date.now() }`,
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
			}
		} catch ( err ) {
			pushToast( {
				id: `dashboard-rollback-err-${ Date.now() }`,
				type: 'error',
				message:
					err?.message ||
					__(
						'Rollback failed. Please try again.',
						'coderembassy-bulk-variations-manager'
					),
			} );
		}
	};

	const [ loading, setLoading ] = useState( true );
	const [ recentJobs, setRecentJobs ] = useState( [] );
	const [ allJobs, setAllJobs ] = useState( [] );

	useEffect( () => {
		let cancelled = false;

		const load = async () => {
			setLoading( true );

			const [ jobsRecent, jobsStats ] = await Promise.all( [
				jobs.list( { per_page: 5 } ).catch( () => ( { data: [] } ) ),
				jobs.list( { per_page: 100 } ).catch( () => ( { data: [] } ) ),
			] );

			if ( cancelled ) {
				return;
			}

			setRecentJobs( jobsRecent.data || [] );
			setAllJobs( jobsStats.data || [] );
			setLoading( false );
		};

		load();
		return () => {
			cancelled = true;
		};
	}, [] );

	const stats = useMemo( () => {
		const running = allJobs.filter( ( job ) =>
			RUNNING_STATUSES.includes( job.status )
		).length;
		const failed30 = allJobs.filter(
			( job ) =>
				getDisplayStatus( job ) === 'failed' &&
				isWithinDays( getJobUpdatedAt( job ), 30 )
		).length;
		const edits7 = allJobs.filter(
			( job ) =>
				getDisplayStatus( job ) === 'completed' &&
				isWithinDays( getJobUpdatedAt( job ), 7 )
		).length;
		const variationsManaged = allJobs.reduce(
			( sum, job ) =>
				sum + Number( job.total_items || job.meta?.total_items || 0 ),
			0
		);

		const monthJobs = allJobs.filter( ( job ) =>
			isThisMonth( getJobUpdatedAt( job ) || job.created_at )
		);
		const minutesSaved = estimateMinutesSaved( monthJobs );
		const hoursSaved = Math.round( minutesSaved / 60 );

		const trend = Array.from( { length: 7 }, ( _, index ) => {
			const dayStart = Date.now() - ( 6 - index ) * 86400000;
			const dayEnd = dayStart + 86400000;
			return monthJobs.filter( ( job ) => {
				const ts = new Date(
					getJobUpdatedAt( job ) || job.created_at
				).getTime();
				return ts >= dayStart && ts < dayEnd;
			} ).length;
		} );

		return {
			running,
			failed30,
			edits7,
			variationsManaged,
			hoursSaved,
			trend,
		};
	}, [ allJobs ] );

	const isFreshInstall = ! loading && recentJobs.length === 0;

	if ( loading ) {
		return (
			<div className="bv-dashboard" aria-busy="true">
				<Skeleton variant="card" />
				<div className="bv-dashboard__kpis">
					<Skeleton variant="card" />
					<Skeleton variant="card" />
					<Skeleton variant="card" />
					<Skeleton variant="card" />
				</div>
			</div>
		);
	}

	if ( isFreshInstall ) {
		return (
			<div className="bv-dashboard">
				<section
					className="bv-hero bv-dashboard__hero"
					aria-labelledby="bv-hero-title"
				>
					<div className="bv-dashboard__hero-body">
						<div className="bv-dashboard__hero-main">
							<h1
								id="bv-hero-title"
								className="bv-dashboard__hero-h1"
							>
								{ __(
									'CoderEmbassy Bulk Variations Manager for WooCommerce',
									'coderembassy-bulk-variations-manager'
								) }
							</h1>
							<p className="bv-dashboard__hero-desc">
								{ __(
									'The variation operations center for WooCommerce.',
									'coderembassy-bulk-variations-manager'
								) }
							</p>
							<div className="bv-dashboard__hero-actions">
								<Button
									onClick={ () => navigateTo( 'editor' ) }
								>
									{ __(
										'Edit a product',
										'coderembassy-bulk-variations-manager'
									) }
								</Button>
								<Button
									variant="primary"
									onClick={ () => navigateTo( 'import' ) }
								>
									{ __(
										'Run CSV import',
										'coderembassy-bulk-variations-manager'
									) }
								</Button>
							</div>
						</div>
					</div>
				</section>
				<EmptyState
					title={ __(
						'Welcome to CoderEmbassy Bulk Variations Manager',
						'coderembassy-bulk-variations-manager'
					) }
					description={ __(
						'Run your first bulk edit or CSV import to see KPIs, recent jobs, and time saved here.',
						'coderembassy-bulk-variations-manager'
					) }
					actionLabel={ __(
						'Edit a product',
						'coderembassy-bulk-variations-manager'
					) }
					onAction={ () => navigateTo( 'editor' ) }
				/>
			</div>
		);
	}

	return (
		<div className="bv-dashboard">
			<section
				className="bv-hero bv-dashboard__hero"
				aria-labelledby="bv-hero-title"
			>
				<div className="bv-dashboard__hero-body">
					<div className="bv-dashboard__hero-main">
						<h1
							id="bv-hero-title"
							className="bv-dashboard__hero-h1"
						>
							{ __(
								'CoderEmbassy Bulk Variations Manager for WooCommerce',
								'coderembassy-bulk-variations-manager'
							) }
						</h1>
						<p className="bv-dashboard__hero-desc">
							{ __(
								'The variation operations center for WooCommerce.',
								'coderembassy-bulk-variations-manager'
							) }
						</p>
						<div className="bv-dashboard__hero-actions">
							<Button onClick={ () => navigateTo( 'editor' ) }>
								{ __(
									'Edit a product',
									'coderembassy-bulk-variations-manager'
								) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => navigateTo( 'import' ) }
							>
								{ __(
									'Run CSV import',
									'coderembassy-bulk-variations-manager'
								) }
							</Button>
						</div>
					</div>
				</div>
			</section>

			<section
				className="bv-dashboard__kpis"
				aria-label={ __(
					'Key metrics',
					'coderembassy-bulk-variations-manager'
				) }
			>
				<button
					type="button"
					className="bv-dashboard__kpi bv-card"
					onClick={ () => navigateTo( 'editor' ) }
				>
					<p className="bv-dashboard__kpi-value">
						{ stats.variationsManaged }
					</p>
					<p className="bv-dashboard__kpi-label">
						{ __(
							'Variations managed',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				</button>
				<button
					type="button"
					className="bv-dashboard__kpi bv-card"
					onClick={ () => navigateTo( 'jobs' ) }
				>
					<p className="bv-dashboard__kpi-value">{ stats.edits7 }</p>
					<p className="bv-dashboard__kpi-label">
						{ __(
							'Edits (7d)',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				</button>
				<button
					type="button"
					className="bv-dashboard__kpi bv-card"
					onClick={ () => navigateTo( 'jobs' ) }
				>
					<p
						className={ `bv-dashboard__kpi-value${
							stats.running > 0 ? ' bv-pulse' : ''
						}` }
					>
						{ stats.running }
					</p>
					<p className="bv-dashboard__kpi-label">
						{ __(
							'Running jobs',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				</button>
				<button
					type="button"
					className="bv-dashboard__kpi bv-card"
					onClick={ () => navigateTo( 'jobs?status=failed' ) }
				>
					<p className="bv-dashboard__kpi-value">
						{ stats.failed30 }
					</p>
					<p className="bv-dashboard__kpi-label">
						{ __(
							'Failed jobs',
							'coderembassy-bulk-variations-manager'
						) }
					</p>
				</button>
			</section>

			<section
				className="bv-quickstart bv-card"
				aria-labelledby="bv-quickstart-title"
			>
				<header className="bv-quickstart__header">
					<h2
						id="bv-quickstart-title"
						className="bv-dashboard__section-title"
					>
						{ __(
							'Quick start',
							'coderembassy-bulk-variations-manager'
						) }
					</h2>
					<button
						type="button"
						className="bv-quickstart__pill"
						onClick={ () => openModal( 'tour' ) }
					>
						{ __( 'Tour', 'coderembassy-bulk-variations-manager' ) }
					</button>
				</header>
				<ol className="bv-quickstart__list">
					{ QUICK_START_STEPS.map( ( step, index ) => (
						<li key={ step } className="bv-quickstart__step">
							<span
								className="bv-quickstart__num"
								aria-hidden="true"
							>
								{ index + 1 }
							</span>
							<span>{ step }</span>
						</li>
					) ) }
				</ol>
			</section>

			<div className="bv-dashboard__lower">
				<section
					className="bv-dashboard__time-saved bv-card"
					aria-labelledby="bv-time-saved-title"
				>
					<h2
						id="bv-time-saved-title"
						className="bv-dashboard__section-title"
					>
						{ __(
							'Time saved this month',
							'coderembassy-bulk-variations-manager'
						) }
					</h2>
					<p className="bv-dashboard__hero-value">
						{ stats.hoursSaved }{ ' ' }
						<span className="bv-dashboard__hero-unit">
							{ __(
								'hours saved',
								'coderembassy-bulk-variations-manager'
							) }
						</span>
					</p>
					<MiniBar
						values={ stats.trend }
						className="bv-dashboard__hero-chart"
					/>
				</section>

				<section
					className="bv-dashboard__recent bv-card"
					aria-labelledby="bv-recent-jobs-title"
				>
					<h2
						id="bv-recent-jobs-title"
						className="bv-dashboard__section-title"
					>
						{ __(
							'Recent jobs',
							'coderembassy-bulk-variations-manager'
						) }
					</h2>
					{ recentJobs.length === 0 ? (
						<p className="bv-muted">
							{ __(
								'No jobs yet.',
								'coderembassy-bulk-variations-manager'
							) }
						</p>
					) : (
						<ul className="bv-dashboard__list">
							{ recentJobs.map( ( job ) => (
								<li
									key={ job.id }
									className="bv-dashboard__list-item"
								>
									<div>
										<strong>#{ job.id }</strong>{ ' ' }
										{ job.type ||
											job.meta?.type ||
											__(
												'Job',
												'coderembassy-bulk-variations-manager'
											) }
										<Badge
											status={ jobBadgeStatus(
												getDisplayStatus( job )
											) }
											label={
												getStatusBadge(
													getDisplayStatus( job )
												).label
											}
										/>
									</div>
									<div className="bv-dashboard__list-actions">
										<Button
											variant="ghost"
											size="sm"
											onClick={ () =>
												navigateTo( `jobs/${ job.id }` )
											}
										>
											{ __(
												'View',
												'coderembassy-bulk-variations-manager'
											) }
										</Button>
										{ canRollback( job ) && (
											<Button
												variant="ghost"
												size="sm"
												onClick={ () =>
													handleRollback( job )
												}
											>
												{ __(
													'Rollback',
													'coderembassy-bulk-variations-manager'
												) }
											</Button>
										) }
									</div>
								</li>
							) ) }
						</ul>
					) }
				</section>
			</div>
		</div>
	);
}
