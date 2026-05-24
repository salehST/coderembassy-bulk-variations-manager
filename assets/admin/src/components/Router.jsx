/**
 * Hash router — resolves views from the registry.
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../store';
import { DEFAULT_ROUTE, navigateTo, parseHash } from '../navigation';
import { isKnownRoute, resolveView } from '../registry/viewRegistry';
import JobDetail from './views/Jobs/JobDetail';
import JobDiff from './views/Jobs/JobDiff';
import PlaceholderView from './views/PlaceholderView';

export { navigateTo, parseHash, DEFAULT_ROUTE } from '../navigation';

export default function Router() {
	const [ route, setRoute ] = useState( parseHash );
	const { setActiveView } = useDispatch( STORE_NAME );

	useEffect( () => {
		const onHashChange = () => setRoute( parseHash() );
		window.addEventListener( 'hashchange', onHashChange );
		setRoute( parseHash() );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	useEffect( () => {
		const { base, segments } = route;

		if ( ! isKnownRoute( base, segments ) ) {
			navigateTo( DEFAULT_ROUTE );
			return;
		}

		let view = base;

		if ( base === 'jobs' && segments[ 1 ] ) {
			view = segments[ 2 ] === 'diff' ? 'jobs-diff' : 'jobs-detail';
		}

		setActiveView( view );
	}, [ route, setActiveView ] );

	const { segments, base } = route;

	if ( ! isKnownRoute( base, segments ) ) {
		return null;
	}

	if ( base === 'jobs' && segments[ 1 ] && segments[ 2 ] === 'diff' ) {
		return <JobDiff jobId={ Number( segments[ 1 ] ) } />;
	}

	if ( base === 'jobs' && segments[ 1 ] ) {
		return <JobDetail jobId={ Number( segments[ 1 ] ) } />;
	}

	const definition = resolveView( base );
	const ViewComponent = definition.component;

	if ( ViewComponent === PlaceholderView ) {
		return (
			<PlaceholderView
				viewId={ definition.id }
				title={ definition.getLabel() }
			/>
		);
	}

	return <ViewComponent />;
}
