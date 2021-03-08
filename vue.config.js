var path = require('path');

module.exports = {
	css: {
		extract: {
			filename: '../styles/build.css'
		}
	},
	chainWebpack: config => {
		// Don't create an index.html file
		config.plugins.delete('html');
		config.plugins.delete('preload');
		config.plugins.delete('prefetch');

		// Don't copy the /public dir
		config.plugins.has('copy') && config.plugin('copy').tap(() => {
			return [];
		});
	},
	configureWebpack: {
		// Don't create a map file for the js package
		devtool: 'source-map',
		entry: [
			'@/styles/_global.less',
			'./js/load.js',
			'./lib/utils/update-reviewer.js'
		],
		optimization: {
			// Don't split vendor and app into separate JS files
			splitChunks: false
		},
		output: {
			filename: 'build.js',
			// Prevent lots of files from being created when running npm run dev
			hotUpdateChunkFilename: 'hot-updates/hot-update.js',
			hotUpdateMainFilename: 'hot-updates/hot-update.json'
		},
		resolve: {
			alias: {
				'@csp': path.resolve(__dirname, 'lib/ui-library/src'),
				'@': path.resolve(__dirname, '../../../lib/ui-library/src')
			}
		},
		watch: false
	},
	outputDir: path.resolve(__dirname, 'js'),
	runtimeCompiler: true
};
