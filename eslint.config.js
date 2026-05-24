const wordpressConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wordpressConfig,
	{
		files: [ 'assets/admin/src/**/*.{js,jsx}' ],
		rules: {
			'jsdoc/require-returns-description': 'off',
			'jsdoc/check-line-alignment': 'off',
			'jsdoc/require-param': 'off',
		},
	},
];
