/**
 * Simple card wrapper with optional heading.
 */
export default function Card( { title, children, className = '' } ) {
	return (
		<section className={ `bv-card ${ className }`.trim() }>
			{ title && <h3 className="bv-card__title">{ title }</h3> }
			{ children }
		</section>
	);
}
