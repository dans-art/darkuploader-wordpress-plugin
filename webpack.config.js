const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'darkup-history': path.resolve(__dirname, 'src/js/history/index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'dist/js'),
	},
	// No externals override for @wordpress/dataviews: this WP install doesn't
	// register a wp-dataviews script/style handle at all (verified against
	// wp-includes/script-loader.php — it only exists privately inside the
	// Site Editor's own edit-site.js bundle), so it has to be bundled into
	// our own JS via node_modules/@wordpress/dataviews (a real dependency in
	// package.json) rather than mapped to a core global. Everything else
	// (@wordpress/element, /components, /api-fetch, /url, /i18n) is a real
	// core-exposed global here and stays externalized by the default config.
	plugins: [
		...defaultConfig.plugins,
		// @wordpress/dataviews declares "sideEffects": false, so a plain JS
		// `import '...css'` gets tree-shaken away in production — copy the
		// compiled stylesheet as a plain file instead and enqueue it directly.
		new CopyWebpackPlugin({
			patterns: [
				{
					from: path.resolve(__dirname, 'node_modules/@wordpress/dataviews/build-style/style.css'),
					to: path.resolve(__dirname, 'dist/js/darkup-history.css'),
				},
			],
		}),
	],
};
