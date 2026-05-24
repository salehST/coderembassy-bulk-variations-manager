/**
 * Tiny sparkline-like bar chart.
 */
export default function MiniBar( { values = [], className = '' } ) {
	const numeric = values.map( ( value ) => Number( value || 0 ) );
	const max = Math.max( ...numeric, 0 );
	const hasData = max > 0;

	return (
		<div
			className={ `bv-mini-bar ${ className }`.trim() }
			role="img"
			aria-label="Activity chart"
		>
			{ numeric.map( ( value, index ) => {
				const heightPercent = hasData
					? Math.max( 12, Math.round( ( value / max ) * 100 ) )
					: 8;

				return (
					<span
						key={ index }
						className={ `bv-mini-bar__item${
							hasData ? '' : ' bv-mini-bar__item--empty'
						}` }
						style={ { height: `${ heightPercent }%` } }
					/>
				);
			} ) }
		</div>
	);
}
