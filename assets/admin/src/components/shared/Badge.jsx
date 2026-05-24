/**
 * Status badge.
 */
export default function Badge( { status = 'muted', label } ) {
	return (
		<span className={ `bv-badge bv-badge--${ status }` }>
			{ label || '-' }
		</span>
	);
}
