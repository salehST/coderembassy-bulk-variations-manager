/**
 * Illustrated empty state with optional CTA.
 */
import Button from './Button';

export default function EmptyState( {
	title,
	description,
	actionLabel,
	onAction,
	className = '',
} ) {
	return (
		<section
			className={ `bv-empty${ className ? ` ${ className }` : '' }` }
			aria-labelledby="bv-empty-title"
		>
			<div className="bv-empty__art" aria-hidden="true">
				<svg
					className="bv-empty__svg"
					viewBox="0 0 120 80"
					role="img"
					aria-hidden="true"
				>
					<rect
						className="bv-empty__shape"
						x="10"
						y="12"
						width="100"
						height="56"
						rx="8"
					/>
					<circle className="bv-empty__dot" cx="36" cy="40" r="8" />
					<rect
						className="bv-empty__line"
						x="52"
						y="34"
						width="48"
						height="6"
						rx="3"
					/>
					<rect
						className="bv-empty__line bv-empty__line--short"
						x="52"
						y="46"
						width="32"
						height="6"
						rx="3"
					/>
				</svg>
			</div>
			<h2 id="bv-empty-title" className="bv-empty__title">
				{ title }
			</h2>
			{ description && <p className="bv-empty__desc">{ description }</p> }
			{ actionLabel && onAction && (
				<Button variant="primary" onClick={ onAction }>
					{ actionLabel }
				</Button>
			) }
		</section>
	);
}
