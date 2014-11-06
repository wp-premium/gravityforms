module.exports = function( grunt ) {
	// Project configuration.
	grunt.initConfig( {
		// Package
		pkg: grunt.file.readJSON( 'package.json' ),

		// Make POT
		makepot: {
			target: {
				options: {
					cwd: '',
					domainPath: 'languages',
					type: 'wp-plugin',
					exclude: [],
				}
			}
		},
	} );

	grunt.loadNpmTasks( 'grunt-wp-i18n' );

	// Default task(s).
	grunt.registerTask( 'pot', [ 'makepot' ] );
};
