/**
 * Date cell editor for sale period columns.
 */
import {
	forwardRef,
	useEffect,
	useImperativeHandle,
	useRef,
	useState,
} from '@wordpress/element';
import {
	commitSaleDateFromEditor,
	normalizeSaleDateValue,
} from '../../../../utils/saleDateUtils';

const DateEditor = forwardRef( function DateEditor( props, ref ) {
	const { value, stopEditing } = props;
	const inputRef = useRef( null );
	const initial = normalizeSaleDateValue( value ?? '' );
	const draftRef = useRef( initial );
	const [ draft, setDraft ] = useState( initial );

	useImperativeHandle( ref, () => ( {
		getValue() {
			const liveValue = inputRef.current?.value ?? draftRef.current;
			return commitSaleDateFromEditor( liveValue );
		},
	} ) );

	useEffect( () => {
		inputRef.current?.focus();
	}, [] );

	const handleChange = ( event ) => {
		draftRef.current = event.target.value;
		setDraft( event.target.value );
	};

	const handleStop = ( cancel = false ) => {
		stopEditing?.( cancel );
	};

	return (
		<input
			ref={ inputRef }
			className="bv-date-editor"
			type="date"
			value={ draft ? String( draft ).slice( 0, 10 ) : '' }
			aria-label="Date"
			onChange={ handleChange }
			onInput={ handleChange }
			onKeyDown={ ( event ) => {
				if ( event.key === 'Enter' ) {
					event.preventDefault();
					handleStop();
				}
				if ( event.key === 'Escape' ) {
					event.preventDefault();
					handleStop( true );
				}
			} }
		/>
	);
} );

export default DateEditor;
