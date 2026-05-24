/**
 * Jobs list — track bulk operations.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { jobs as jobsApi } from '../../../api/endpoints';
import { parseHash } from '../../../navigation';
import { STORE_NAME } from '../../../store';
import CardIntro from '../BulkEditor/CardIntro';
import EmptyState from '../../shared/EmptyState';
import ProgressBar from '../../shared/ProgressBar';
import Skeleton from '../../shared/Skeleton';
import JobRowActions from './JobRowActions';
import JobStatusBadge from './JobStatusBadge';
import {
	formatJobDate,
	formatJobType,
	getJobProgressPercent,
	getJobUpdatedAt,
} from './jobUtils';

const readStatusFilter = () => {
	const { raw } = parseHash();
	const query = raw.includes( '?' ) ? raw.split( '?' )[ 1 ] : '';
	if ( ! query ) {
		return '';
	}
	const params = new URLSearchParams( query );
	return params.get( 'status' ) || '';
};

export default function Jobs() {
	const storeJobs = useSelect(
		( select ) => select( STORE_NAME ).getJobs(),
		[]
	);
	const { setJobs, pushToast } = useDispatch( STORE_NAME );

	const [ loading, setLoading ] = useState( true );
	const [ jobList, setJobList ] = useState( [] );
	const [ statusFilter, setStatusFilter ] = useState( readStatusFilter );

	const loadJobs = useCallback( async () => {
		setLoading( true );
		try {
			const query = { per_page: 50 };
			if ( statusFilter ) {
				query.status =
					statusFilter === 'completed' ? 'complete' : statusFilter;
			}
			const response = await jobsApi.list( query );
			const data = Array.isArray( response?.data ) ? response.data : [];
			const total = Number( response?.total || data.length );
			setJobList( data );
			if ( ! statusFilter ) {
				setJobs( data, total );
			}
		} catch ( err ) {
			void err;
			setJobList( [] );
			if ( ! statusFilter ) {
				setJobs( [], 0 );
			}
		} finally {
			setLoading( false );
		}
	}, [ setJobs, statusFilter ] );

	useEffect( () => {
		setStatusFilter( readStatusFilter() );
	}, [] );

	useEffect( () => {
		const onHashChange = () => setStatusFilter( readStatusFilter() );
		window.addEventListener( 'hashchange', onHashChange );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	useEffect( () => {
		loadJobs();
	}, [ loadJobs ] );

	useEffect( () => {
		if ( statusFilter || ! storeJobs?.list?.length ) {
			return;
		}
		setJobList( storeJobs.list );
	}, [ statusFilter, storeJobs ] );

	const handleToast = useCallback(
		( toast ) => {
			pushToast( {
				id: `jobs-toast-${ Date.now() }`,
				...toast,
			} );
		},
		[ pushToast ]
	);

	const renderBody = () => {
		if ( loading && ! jobList.length ) {
			return <Skeleton variant="card" />;
		}

		if ( jobList.length === 0 ) {
			return (
				<EmptyState
					title={ __(
						'No jobs yet',
						'coderembassy-bulk-variations-manager'
					) }
					description={ __(
						'No jobs yet. Run a bulk edit or import to see it here.',
						'coderembassy-bulk-variations-manager'
					) }
					actionLabel={ __(
						'Open Bulk Editor',
						'coderembassy-bulk-variations-manager'
					) }
					onAction={ () => {
						window.location.hash = '#/editor';
					} }
				/>
			);
		}

		return (
			<div className="bv-jobs-table-wrap">
				<table className="bv-jobs-table">
					<thead>
						<tr>
							<th scope="col">
								{ __(
									'ID',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Type',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Status',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Progress',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Items',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Created',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Updated',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
							<th scope="col">
								{ __(
									'Actions',
									'coderembassy-bulk-variations-manager'
								) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ jobList.map( ( job ) => {
							const progress = getJobProgressPercent( job );
							const processed = Number( job.processed || 0 );
							const total = Number( job.total_items || 0 );

							return (
								<tr key={ job.id }>
									<td
										data-label={ __(
											'ID',
											'coderembassy-bulk-variations-manager'
										) }
									>
										<strong>#{ job.id }</strong>
									</td>
									<td
										data-label={ __(
											'Type',
											'coderembassy-bulk-variations-manager'
										) }
									>
										{ formatJobType( job ) }
									</td>
									<td
										data-label={ __(
											'Status',
											'coderembassy-bulk-variations-manager'
										) }
									>
										<JobStatusBadge job={ job } />
									</td>
									<td
										data-label={ __(
											'Progress',
											'coderembassy-bulk-variations-manager'
										) }
									>
										<div className="bv-jobs-table__progress">
											<ProgressBar
												value={ progress }
												max={ 100 }
												className="bv-jobs-table__progress-bar"
											/>
											<span className="bv-jobs-table__progress-label">
												<strong>{ progress }%</strong>
												{ processed } / { total }
											</span>
										</div>
									</td>
									<td
										data-label={ __(
											'Items',
											'coderembassy-bulk-variations-manager'
										) }
									>
										{ total }
									</td>
									<td
										data-label={ __(
											'Created',
											'coderembassy-bulk-variations-manager'
										) }
									>
										{ formatJobDate( job.created_at ) }
									</td>
									<td
										data-label={ __(
											'Updated',
											'coderembassy-bulk-variations-manager'
										) }
									>
										{ formatJobDate(
											getJobUpdatedAt( job )
										) }
									</td>
									<td
										data-label={ __(
											'Actions',
											'coderembassy-bulk-variations-manager'
										) }
									>
										<JobRowActions
											job={ job }
											onRefresh={ loadJobs }
											onToast={ handleToast }
										/>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>
		);
	};

	return (
		<div className="bv-jobs">
			<section className="bv-card bv-jobs__card">
				<CardIntro
					title={ __(
						'Jobs',
						'coderembassy-bulk-variations-manager'
					) }
					help={ __(
						'Track bulk edits, imports, previews, and rollbacks.',
						'coderembassy-bulk-variations-manager'
					) }
				/>

				{ renderBody() }
			</section>
		</div>
	);
}
