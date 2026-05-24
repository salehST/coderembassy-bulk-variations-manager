/**
 * Bounded undo/redo stack for cell edits.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { redo as redoIcon, undo as undoIcon } from '@wordpress/icons';

const MAX_STACK = 100;

export function useUndoRedo( initialRows = [] ) {
	const [ rows, setRows ] = useState( initialRows );
	const [ undoStack, setUndoStack ] = useState( [] );
	const [ redoStack, setRedoStack ] = useState( [] );

	const pushEdit = useCallback( ( edit ) => {
		setUndoStack( ( stack ) => [ ...stack, edit ].slice( -MAX_STACK ) );
		setRedoStack( [] );
		setRows( ( current ) =>
			current.map( ( row ) =>
				row.variation_id === edit.variation_id
					? { ...row, [ edit.field ]: edit.newValue }
					: row
			)
		);
	}, [] );

	const undo = useCallback( () => {
		setUndoStack( ( stack ) => {
			if ( ! stack.length ) {
				return stack;
			}
			const edit = stack[ stack.length - 1 ];
			const nextStack = stack.slice( 0, -1 );
			setRedoStack( ( redo ) => [ ...redo, edit ] );
			setRows( ( current ) =>
				current.map( ( row ) =>
					row.variation_id === edit.variation_id
						? { ...row, [ edit.field ]: edit.oldValue }
						: row
				)
			);
			return nextStack;
		} );
	}, [] );

	const redo = useCallback( () => {
		setRedoStack( ( stack ) => {
			if ( ! stack.length ) {
				return stack;
			}
			const edit = stack[ stack.length - 1 ];
			const nextStack = stack.slice( 0, -1 );
			setUndoStack( ( undoHistory ) => [ ...undoHistory, edit ] );
			setRows( ( current ) =>
				current.map( ( row ) =>
					row.variation_id === edit.variation_id
						? { ...row, [ edit.field ]: edit.newValue }
						: row
				)
			);
			return nextStack;
		} );
	}, [] );

	const resetRows = useCallback( ( nextRows ) => {
		setRows( nextRows );
		setUndoStack( [] );
		setRedoStack( [] );
	}, [] );

	return {
		rows,
		setRows: resetRows,
		undoStack,
		redoStack,
		pushEdit,
		undo,
		redo,
		canUndo: undoStack.length > 0,
		canRedo: redoStack.length > 0,
	};
}

export default function UndoRedo( { canUndo, canRedo, onUndo, onRedo } ) {
	const labels = useMemo(
		() => ( {
			undo: __( 'Undo', 'coderembassy-bulk-variations-manager' ),
			redo: __( 'Redo', 'coderembassy-bulk-variations-manager' ),
		} ),
		[]
	);

	return (
		<div className="bv-undo-redo" role="group" aria-label={ labels.undo }>
			<button
				type="button"
				className="bv-undo-redo__btn"
				disabled={ ! canUndo }
				onClick={ onUndo }
				aria-label={ labels.undo }
				title={ labels.undo }
			>
				<Icon icon={ undoIcon } size={ 18 } />
			</button>
			<button
				type="button"
				className="bv-undo-redo__btn"
				disabled={ ! canRedo }
				onClick={ onRedo }
				aria-label={ labels.redo }
				title={ labels.redo }
			>
				<Icon icon={ redoIcon } size={ 18 } />
			</button>
		</div>
	);
}
