const baseConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );
const presetConfig = require( '@wordpress/jest-preset-default' );

module.exports = {
	...baseConfig,
	setupFilesAfterEnv: [
		...presetConfig.setupFilesAfterEnv,
		'<rootDir>/jest.setup.js',
	],
	transform: {
		...baseConfig.transform,
		'\\.mjs$': baseConfig.transform[ '\\.[jt]sx?$' ],
	},
	transformIgnorePatterns: [ 'node_modules/(?!(.*/)?(uuid|@wordpress)/)' ],
};
