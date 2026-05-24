/**
 * Component.
 */
export default function Skeleton( {
	variant = 'text',
	rows = 1,
	className = '',
} ) {
	if ( variant === 'card' ) {
		return (
			<div
				className={ `bv-skeleton bv-skeleton--card${
					className ? ` ${ className }` : ''
				}` }
				role="status"
				aria-busy="true"
				aria-label="Loading"
			>
				<div className="bv-skeleton__block bv-skeleton__block--title" />
				<div className="bv-skeleton__block" />
				<div className="bv-skeleton__block bv-skeleton__block--short" />
			</div>
		);
	}

	if ( variant === 'row' ) {
		return (
			<div
				className={ `bv-skeleton bv-skeleton--row${
					className ? ` ${ className }` : ''
				}` }
				role="status"
				aria-busy="true"
				aria-label="Loading"
			>
				{ Array.from( { length: rows } ).map( ( _, index ) => (
					<div key={ index } className="bv-skeleton__row" />
				) ) }
			</div>
		);
	}

	return (
		<div
			className={ `bv-skeleton bv-skeleton--text${
				className ? ` ${ className }` : ''
			}` }
			role="status"
			aria-busy="true"
			aria-label="Loading"
		>
			{ Array.from( { length: rows } ).map( ( _, index ) => (
				<div
					key={ index }
					className="bv-skeleton__line"
					style={ { width: `${ 100 - index * 12 }%` } }
				/>
			) ) }
		</div>
	);
}
