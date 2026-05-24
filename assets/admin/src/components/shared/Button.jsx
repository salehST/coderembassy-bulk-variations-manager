/**
 * Shared button primitive.
 */
export default function Button( {
	type = 'button',
	variant = 'default',
	size = 'md',
	className = '',
	children,
	...props
} ) {
	const classes = [
		'bv-btn',
		`bv-btn--${ variant }`,
		`bv-btn--${ size }`,
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<button type={ type } className={ classes } { ...props }>
			{ children }
		</button>
	);
}
