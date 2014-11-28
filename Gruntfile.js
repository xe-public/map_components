module.exports = function(grunt) {
	"use strict";

	grunt.file.defaultEncoding = 'utf8';

	grunt.initConfig({
		uglify: {
			'map': {
				files: {
					'front/js/daum_maps.min.js': ['front/js/daum_maps.js'],
					'front/js/google_maps.min.js': ['front/js/google_maps.js'],
					'tpl/daum_map.min.js': ['tpl/daum_map.js'],
					'tpl/google_map.min.js': ['tpl/google_map.js'],
				}
			},
		},
		jshint: {
			files: [
				'Gruntfile.js',
				'front/js/*.js',
				'tpl/*.js'
			],
			options : {
				globalstrict: false,
				undef : false,
				eqeqeq: false,
				browser : true,
				globals: {
					"jQuery" : true,
					"console" : true,
					"window" : true
				},
				ignores : [
					'**/jquery*.js',
					'**/*.min.js',
				]
			}
		},
		cssmin: {
			'tpl-css': {
				files: {
					'tpl/pop.min.css': ['tpl/pop.css'],
				}
			},
		},
		csslint: {
			'common-css': {
				options: {
					'import' : 2,
					'adjoining-classes' : false,
					'box-sizing' : false,
					'duplicate-background-images' : false,
					'ids' : false,
					'important' : false,
					'overqualified-elements' : false,
					'qualified-headings' : false,
					'star-property-hack' : false,
					'underscore-property-hack' : false,
					'regex-selectors' : false,
				},
				src: [
					'tpl/*.css',
					'!**/*.min.css',
				]
			}
		},
		phplint: {
			default : {
				options: {
					phpCmd: "php",
				},

				src: [
					"**/*.php",
					"!node_modules/**",
				],
			},
		}
	});

	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-csslint');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-phplint');

	grunt.registerTask('default', ['jshint', 'csslint', 'phplint']);
	grunt.registerTask('lint', ['jshint', 'csslint', 'phplint']);
	grunt.registerTask('minify', ['uglify', 'cssmin']);
};
