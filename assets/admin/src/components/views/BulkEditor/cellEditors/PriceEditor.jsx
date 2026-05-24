/**
 * Price cell editor with PRICE formula support.
 */
import {
	forwardRef,
	useEffect,
	useImperativeHandle,
	useRef,
	useState,
} from '@wordpress/element';
import {
	commitPriceFromEditor,
	normalizePriceInput,
} from '../priceEditorUtils';

const PriceEditor = forwardRef( function PriceEditor( props, ref ) {
	const {
		value,
		eventKey,
		column,
		colDef,
		context,
		data,
		node,
		stopEditing,
	} = props;
	const inputRef = useRef( null );
	const initial = normalizePriceInput( value ?? '' );
	const draftRef = useRef( initial );
	const [ draft, setDraft ] = useState( initial );
	const field =
		column?.getColId?.() ||
		column?.colId ||
		colDef?.field ||
		'regular_price';

	useImperativeHandle( ref, () => ( {
		getValue() {
			const current =
				parseFloat(
					normalizePriceInput(
						node?.data?.[ field ] ?? data?.[ field ] ?? value ?? 0
					)
				) || 0;
			const liveValue = inputRef.current?.value ?? draftRef.current;
			return commitPriceFromEditor( liveValue, current );
		},
		isCancelBeforeStart() {
			return false;
		},
	} ) );

	useEffect( () => {
		inputRef.current?.focus();
		if ( eventKey && eventKey.length === 1 ) {
			draftRef.current = eventKey;
			setDraft( eventKey );
		}
	}, [ eventKey ] );

	const handleChange = ( event ) => {
		draftRef.current = event.target.value;
		setDraft( event.target.value );
		context?.onPriceDraftChange?.( {
			variation_id: node?.data?.variation_id || data?.variation_id || 0,
			field,
			value: normalizePriceInput( event.target.value ),
		} );
	};

	const handleStop = ( cancel = false ) => {
		stopEditing?.( cancel );
	};

	return (
		<input
			ref={ inputRef }
			className="bv-price-editor"
			type="text"
			value={ draft }
			placeholder="=PRICE*1.1 or +15%"
			aria-label="Price formula"
			onChange={ handleChange }
			onInput={ handleChange }
			onBlur={ () => handleStop() }
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

export default PriceEditor;
