/**
 * Shared card header block used across editor/jobs/import screens.
 */
export default function CardIntro( { title, help, actions } ) {
	return (
		<header className="bv-card-intro">
			<div className="bv-card-intro__main">
				{ title && <h2 className="bv-card-intro__title">{ title }</h2> }
				{ help && <p className="bv-card-intro__help">{ help }</p> }
			</div>
			{ actions && (
				<div className="bv-card-intro__actions">{ actions }</div>
			) }
		</header>
	);
}
