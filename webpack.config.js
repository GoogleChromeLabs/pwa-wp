const defaultConfig = require('@wordpress/scripts/config/webpack.config');
module.exports = {
	...defaultConfig,
	entry: {
		'site-icon-maskable': './site-icon-maskable',
	},
};
