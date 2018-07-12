/* eslint-env node */
/* jshint node:true */
/* eslint-disable camelcase, no-console, no-param-reassign */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		// Clean up the build.
		clean: {
			build: {
				src: [ 'build' ]
			}
		},

		// Shell actions.
		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			readme: {
				command: 'npm run generate-readme' // Generate the readme.md.
			},
			phpunit: {
				command: 'phpunit'
			},
			verify_matching_versions: {
				command: 'php bin/verify-version-consistency.php'
			},
			create_build_zip: {
				command: 'if [ ! -e build ]; then echo "Run grunt build first."; exit 1; fi; if [ -e pwa.zip ]; then rm pwa.zip; fi; cd build; zip -r ../pwa.zip .; cd ..; echo; echo "ZIP of build: $(pwd)/pwa.zip"'
			}
		},

		// Deploys a git Repo to the WordPress SVN repo.
		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'pwa',
					build_dir: 'build',
					assets_dir: 'wp-assets'
				}
			}
		}

	} );

	// Load tasks.
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-wp-deploy' );

	// Register tasks.
	grunt.registerTask( 'default', [
		'build'
	] );

	grunt.registerTask( 'readme', [
		'shell:readme'
	] );

	grunt.registerTask( 'build', function() {
		var done, spawnQueue, stdout;
		done = this.async();
		spawnQueue = [];
		stdout = [];

		spawnQueue.push(
			{
				cmd: 'git',
				args: [ '--no-pager', 'log', '-1', '--format=%h', '--date=short' ]
			},
			{
				cmd: 'git',
				args: [ 'ls-files' ]
			}
		);

		function finalize() {
			var commitHash, lsOutput, versionAppend, paths;
			commitHash = stdout.shift();
			lsOutput = stdout.shift();
			versionAppend = commitHash + '-' + new Date().toISOString().replace( /\.\d+/, '' ).replace( /-|:/g, '' );

			paths = lsOutput.trim().split( /\n/ ).filter( function( file ) {
				return ! /^(\.|bin|([^/]+)+\.(md|json|xml)|Gruntfile\.js|tests|wp-assets|dev-lib|readme\.md|composer\..*|webpack.*)/.test( file );
			} );

			grunt.task.run( 'clean' );
			grunt.config.set( 'copy', {
				build: {
					src: paths,
					dest: 'build',
					expand: true,
					options: {
						noProcess: [ '*/**', 'LICENSE' ], // That is, only process pwa.php and readme.txt.
						process: function( content, srcpath ) {
							var matches, version, versionRegex;
							if ( /pwa\.php$/.test( srcpath ) ) {
								versionRegex = /(\*\s+Version:\s+)(\d+(\.\d+)+-\w+)/;

								// If not a stable build (e.g. 0.7.0-beta), amend the version with the git commit and current timestamp.
								matches = content.match( versionRegex );
								if ( matches ) {
									version = matches[ 2 ] + '-' + versionAppend;
									console.log( 'Updating version in pwa.php to ' + version );
									content = content.replace( versionRegex, '$1' + version );
									content = content.replace( /(define\(\s*'PWA_VERSION',\s*')(.+?)(?=')/, '$1' + version );
								}
							}
							return content;
						}
					}
				}
			} );
			grunt.task.run( 'readme' );
			grunt.task.run( 'copy' );

			done();
		}

		function doNext() {
			var nextSpawnArgs = spawnQueue.shift();
			if ( ! nextSpawnArgs ) {
				finalize();
			} else {
				grunt.util.spawn(
					nextSpawnArgs,
					function( err, res ) {
						if ( err ) {
							throw new Error( err.message );
						}
						stdout.push( res.stdout );
						doNext();
					}
				);
			}
		}

		doNext();
	} );

	grunt.registerTask( 'create-build-zip', [
		'shell:create_build_zip'
	] );

	grunt.registerTask( 'build-release', [
		'build'
	] );

	grunt.registerTask( 'deploy', [
		'shell:verify_matching_versions',
		'shell:phpunit',
		'build-release',
		'wp_deploy'
	] );
};
