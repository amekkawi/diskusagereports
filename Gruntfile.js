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
						//'index.html',
						'*.txt',
						'*.md',

						// Include all app files except js, css, and templates.
						'app/**/*',
						'!app/{css,templates}',
						'!app/{css,templates}/**/*',
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

		cssmin: {

		},

		uglify: {

		},

		concat: {

		},

		requirejs: {
			options: {
				optimize: 'none',
				preserveLicenseComments: false,
				useStrict: true,
				wrap: true
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
		},

		htmlbuild: {
			dist: {
				options: {
					tagName: 'build',
					baseUrl: 'build'
				},
				src: [ 'index.html' ],
				dest: dirs.dest + '/index.html'
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-requirejs');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-replace');

	grunt.loadTasks('grunt');

	grunt.registerMultiTask('fn', 'Run a custom function', function(target) {
		var options = this.options({
			fn: null
		});
		if (typeof options.fn != "function")
			grunt.fail.warn('Target ' + this.target + ' does not have a fn option that is a function.');

		options.fn.apply(this, arguments);
	});

	grunt.registerTask('build', [
		'clean',
		'htmlbuild',
		'concat',
		'requirejs',
		//'cssmin',
		'uglify',
		'copy',
		'replace:version'
    ]);
};