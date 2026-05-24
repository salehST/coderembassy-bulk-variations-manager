/**
 * Select editor for post status.
 */
import { forwardRef, useImperativeHandle, useRef } from '@wordpress/element';

const OPTIONS = [ 'publish', 'private', 'draft', 'trash' ];

const StatusEditor = forwardRef( function StatusEditor( props, ref ) {
	const inputRef = useRef( null );

	useImperativeHandle( ref, () => ( {
		getValue() {
			return inputRef.current?.value || '';
		},
	} ) );

	return (
		<select
			ref={ inputRef }
			className="bv-status-editor"
			defaultValue={ String( props.value || 'publish' ) }
		>
			{ OPTIONS.map( ( value ) => (
				<option key={ value } value={ value }>
					{ value }
				</option>
			) ) }
		</select>
	);
} );

export default StatusEditor;
