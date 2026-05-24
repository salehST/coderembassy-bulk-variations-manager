import Badge from '../../shared/Badge';
import { getDisplayStatus, getStatusBadge } from './jobUtils';

export default function JobStatusBadge( { job } ) {
	const display = getDisplayStatus( job );
	const badge = getStatusBadge( display );
	return <Badge status={ badge.status } label={ badge.label } />;
}
