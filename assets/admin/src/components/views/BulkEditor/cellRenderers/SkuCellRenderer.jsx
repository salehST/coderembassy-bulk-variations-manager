export default function SkuCellRenderer( params ) {
	const value = String( params.value || '' ).trim();
	return value ? (
		<span className="bv-grid-sku" title={ value }>
			{ value }
		</span>
	) : (
		<span className="bv-cell-placeholder">—</span>
	);
}
