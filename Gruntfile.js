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
						'!app/{css,models,views,templates}',
						'!app/{css,models,views,templates}/**/*',
						'!app/**/*.js',

						// Exclude all files starting with
						// a period except .htaccess
						'!**/.*',
						'**/.htaccess',

						// Exclude anything from vendor
						'!vendor',
						'!vendor/**/*'
					]
				}]
			}
		},

		less: {
			options: {
				yuicompress: true
			},
			dummy: {

			}
		},

		uglify: {
			options: {
				banner: [
					'/*',
					' Disk Usage Reports',
					' http://diskusagereports.com/',
					' Version <%= pkg.version %>',
					'',
					' See http://diskusagereports.com/ for source code and licenses.',
					'/\n' ].join('\n *')
			},
			dummy: {

			}
		},

		concat: {
			dummy: {

			}
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
						UrlVersion: '<%= encodeURI(pkg.version) %>',
						CopyYear: grunt.template.today('yyyy')
					}
				},
				files: [{
					expand: true,
					src: [
						dirs.dest + '/index.html',
						dirs.dest + '/app/**/*.js',
						dirs.dest + '/scripts/**/*.php',
						dirs.dest + '/scripts/**/*.sh'
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
	grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-replace');
	grunt.loadNpmTasks('grunt-htmlbuild');

	grunt.registerTask('build', [
		'clean',
		'htmlbuild',
		'concat',
		'requirejs',
		'less',
		'uglify',
		'copy',
		'replace:version'
    ]);

	grunt.registerTask('default', [ 'build' ]);
};
