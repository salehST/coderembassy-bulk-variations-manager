/**
 * Poll /jobs while any job is active; stop when none remain.
 */
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { jobs as jobsApi } from '../api/endpoints';
import { STORE_NAME } from '../store';
import { ACTIVE_JOB_STATUSES } from '../components/views/Jobs/jobUtils';

const BASE_INTERVAL = 3000;
const MAX_BACKOFF = 30000;

const hasActiveJobs = ( list ) =>
	Array.isArray( list ) &&
	list.some( ( job ) =>
		ACTIVE_JOB_STATUSES.includes( String( job?.status || '' ) )
	);

export function useJobsPolling() {
	const jobState = useSelect(
		( select ) => select( STORE_NAME ).getJobs(),
		[]
	);
	const { setJobs, setJobsPolling } = useDispatch( STORE_NAME );
	const backoffRef = useRef( BASE_INTERVAL );
	const timerRef = useRef( null );
	const cancelledRef = useRef( false );

	const clearTimer = useCallback( () => {
		if ( timerRef.current ) {
			window.clearTimeout( timerRef.current );
			timerRef.current = null;
		}
	}, [] );

	const pollOnce = useCallback( async () => {
		const response = await jobsApi.list( { per_page: 50 } );
		const data = Array.isArray( response?.data ) ? response.data : [];
		const total = Number( response?.total || data.length );
		setJobs( data, total );
		backoffRef.current = BASE_INTERVAL;
		const active = hasActiveJobs( data );
		setJobsPolling( active );
		return active;
	}, [ setJobs, setJobsPolling ] );

	const scheduleNext = useCallback(
		( delay = BASE_INTERVAL ) => {
			clearTimer();
			timerRef.current = window.setTimeout( async () => {
				if ( cancelledRef.current ) {
					return;
				}
				try {
					const active = await pollOnce();
					if ( cancelledRef.current ) {
						return;
					}
					if ( active ) {
						scheduleNext( BASE_INTERVAL );
					} else {
						setJobsPolling( false );
					}
				} catch ( err ) {
					void err;
					if ( cancelledRef.current ) {
						return;
					}
					backoffRef.current = Math.min(
						backoffRef.current * 2,
						MAX_BACKOFF
					);
					scheduleNext( backoffRef.current );
				}
			}, delay );
		},
		[ clearTimer, pollOnce, setJobsPolling ]
	);

	useEffect( () => {
		cancelledRef.current = false;

		const start = async () => {
			try {
				const active = await pollOnce();
				if ( ! cancelledRef.current && active ) {
					scheduleNext( BASE_INTERVAL );
				}
			} catch ( err ) {
				void err;
				if ( ! cancelledRef.current ) {
					scheduleNext( backoffRef.current );
				}
			}
		};

		start();

		const onHashChange = () => {
			if ( cancelledRef.current ) {
				return;
			}
			pollOnce()
				.then( ( active ) => {
					if ( cancelledRef.current ) {
						return;
					}
					clearTimer();
					if ( active ) {
						scheduleNext( BASE_INTERVAL );
					}
				} )
				.catch( () => {} );
		};

		window.addEventListener( 'hashchange', onHashChange );

		return () => {
			cancelledRef.current = true;
			clearTimer();
			window.removeEventListener( 'hashchange', onHashChange );
			setJobsPolling( false );
		};
	}, [ clearTimer, pollOnce, scheduleNext, setJobsPolling ] );

	return jobState;
}
