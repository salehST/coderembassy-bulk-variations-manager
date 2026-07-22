/**
 * Placeholder for non-core screens.
 */
import { __ } from '@wordpress/i18n';

export default function PlaceholderView( { title, viewId = '' } ) {
	if ( viewId === 'help' ) {
		return (
			<section className="bv-card">
				<h2>{ title }</h2>
				<ul className="bv-list">
					<li>
						{ __(
							'Bulk editor, CSV import, jobs, preview/approve, apply, and rollback are available in Free.',
							'coderembassy-bulk-variations-manager'
						) }
					</li>
					<li>
						{ __(
							'Use CSV Import to validate rows before creating a job.',
							'coderembassy-bulk-variations-manager'
						) }
					</li>
					<li>
						{ __(
							'Use Jobs to review diff, apply, and rollback.',
							'coderembassy-bulk-variations-manager'
						) }
					</li>
				</ul>
			</section>
		);
	}

	return (
		<section className="bv-card">
			<h2>{ title }</h2>
			<p className="bv-muted">
				{ __(
					'This screen is intentionally minimal.',
					'coderembassy-bulk-variations-manager'
				) }
			</p>
		</section>
	);
}
