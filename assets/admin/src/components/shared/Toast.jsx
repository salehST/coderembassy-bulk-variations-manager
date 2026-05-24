/**
 * Single toast item.
 */
import Button from './Button';

export default function Toast( { toast, onDismiss } ) {
	return (
		<div className={ `bv-toast bv-toast--${ toast.type || 'info' }` }>
			<p className="bv-toast__message">{ toast.message }</p>
			<Button
				size="sm"
				variant="ghost"
				onClick={ () => onDismiss?.( toast.id ) }
			>
				x
			</Button>
		</div>
	);
}
