export default function ThumbRenderer( params ) {
	const src = String( params.value || params.data?.image_url || '' ).trim();
	if ( ! src ) {
		return (
			<span className="bv-grid-thumb-placeholder" aria-hidden="true">
				<svg
					viewBox="0 0 24 24"
					width="20"
					height="20"
					role="presentation"
				>
					<path
						fill="currentColor"
						d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5.04-6.71l-2.75 3.54-1.96-2.36L6.5 17h11l-3.54-4.71z"
					/>
				</svg>
			</span>
		);
	}
	return (
		<img
			className="bv-grid-thumb"
			src={ src }
			alt=""
			loading="lazy"
			width="34"
			height="34"
		/>
	);
}
