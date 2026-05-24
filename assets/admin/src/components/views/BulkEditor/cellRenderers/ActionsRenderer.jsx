import Button from '../../../shared/Button';

export default function ActionsRenderer( params ) {
	const onRowAction = params.context?.onRowAction;
	const row = params.data || {};

	return (
		<div className="bv-grid-actions">
			<Button
				size="sm"
				variant="ghost"
				onClick={ () => onRowAction?.( 'view_history', row ) }
				title="View job history"
			>
				...
			</Button>
		</div>
	);
}
