module.exports = function(grunt) {
	var dirs = {
		dest: 'build'
	};

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		clean: {
			dist: {
				files: [{
					dot: true,
					src: [
						dirs.dest + '/*',
						'!' + dirs.dest + '/.git*'
					]
				}]
			}
		},

		copy: {
			dist: {
				files: [{
					expand: true,
					dot: true,
					dest: dirs.dest,
					src: [
						'scripts/**/*',
						'index.html',
						'*.txt',
						'*.md',

						// Include all app files except js and css
						'app/**/*',
						'!app/css',
						'!app/css/**/*',
						'!app/**/*.js',

						// Exclude all files starting with
						// a period except .htaccess
						'!**/.*',
						'**/.htaccess',

						// Exclude anything from vendor
						'!app/vendor',
						'!app/vendor/**/*'
					]
				}]
			}
		},

		useminPrepare: {
			html: 'index.html',
			options: {
				dest: dirs.dest
			}
		},

		usemin: {
			html: [ dirs.dest + '/*.html' ],
			options: {
				dirs: [ dirs.dest ]
			}
		},

		cssmin: {
			// Set by useminPrepare
		},

		uglify: {
			// Set by useminPrepare
			'build/app/config.js': 'build/app/config.js'
		},

		concat: {
			// Set by useminPrepare
		},

		requirejs: {
			dist: {
				options: {
					baseUrl: 'app',
					optimize: 'none',
					preserveLicenseComments: false,
					useStrict: true,
					wrap: true,
					name: 'config',
					out: 'build/app/config.js',
					mainConfigFile: 'app/config.js'
				}
			}
		},

		replace: {
			version: {
				options: {
					variables: {
						SourceVersion: '<%= pkg.version %>',
						UrlVersion: '<%= encodeURI(pkg.version) %>'
					}
				},
				files: [{
					expand: true,
					src: [
						dirs.dest + '/index.html',
						dirs.dest + '/app/**/*.js'
					]
				}]
			}
		}
	});

	/*if (package && package.devDependencies) {
		grunt.log.writeln("Loading NPM tasks..." + (typeof package.devDependencies));
		for (var dep in package.devDependencies) {
			grunt.log.writeln("Loading " + dep);
		}
	}*/

	grunt.loadNpmTasks('grunt-usemin');
	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-requirejs');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-replace');

	grunt.registerTask('build', [
		'clean',
		'useminPrepare',
		'concat',
		'requirejs',
		'cssmin',
		'uglify',
		'copy',
		'usemin',
		'replace:version'
    ]);
};