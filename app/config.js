/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
require.config({

	baseUrl: 'app',

	config: {
		app: {
			reportsBaseURL: 'data/',

			report: null,

			suffix: [ '.txt', '' ],

			gradient: [ '007eff','0081f8','0084ef','0088e4','008dd8','0092cb','0097bd','009cae',
				'00a29f','00a88f','00ae80','00b275','00b66a','00ba60','00be56','00c24c',
				'00c642','00ca38','00cd2f','00d127','00d41f','00d717','00da10','03dd0a',
				'07e005','0be200','15e600','22eb00','31ef00','42f300','55f600','69fa00',
				'7dfd00','8bfe00','93ff00','9cff00','a5ff00','adff00','b5ff00','bdff00',
				'c5ff00','cdff00','d4ff00','dbff00','e2ff00','e8ff00','eeff00','f3ff00',
				'f8ff00','fcff00','ffff00','fffd00','fffa00','fff800','fff500','fff200',
				'ffee00','ffea00','ffe600','ffe200','ffde00','ffd900','ffd400','ffcf00',
				'ffca00','ffc500','ffc000','ffba00','ffb500','ffb000','ffaa00','ffa500',
				'ffa000','ff9a00','ff9500','ff9000','ff8d00','ff8900','ff8600','ff8200',
				'ff7f00','ff7b00','ff7700','ff7300','ff6f00','ff6b00','ff6700','ff6300',
				'ff5f00','ff5a00','ff5600','ff5200','ff4e00','ff4900','ff4500','ff4100',
				'ff3100','ff2200','ff1400','ff0900' ],

			pageMax: 100,

			languages: [
				'en-us'
			]
		}
	},

	deps: [
		"main"
	],

	paths: {

	},

	map: {

	},

	packages: [
		{
			name: "backbone",
			location: "vendor/bower/backbone",
			main: "backbone.js"
		},
		{
			name: "zepto",
			location: "vendor/bower/zepto",
			main: "zepto.js"
		},
		{
			name: "jquery",
			location: "vendor/bower/jquery",
			main: "jquery.js"
		},
		{
			name: "lodash",
			location: "vendor/bower/lodash",
			main: "lodash.js"
		},
		{
			name: "underscore",
			location: "vendor/bower/lodash",
			main: "lodash.js"
		},
		{
			name: "i18n",
			location: "vendor/bower/requirejs-i18n",
			main: "i18n.js"
		},
		{
			name: "text",
			location: "vendor/bower/requirejs-text",
			main: "text.js"
		},
		{
			name: "layoutmanager",
			location: "vendor/bower/layoutmanager",
			main: "backbone.layoutmanager.js"
		}
	],

	shim: {
		backbone: {
			deps: [
				"jquery",
				"underscore"
			],
			exports: "Backbone"
		},
		layoutmanager: {
			deps: [
				"backbone"
			],
			exports: "Backbone.Layout"
		}
	}

});

//>>includeStart("usezepto", pragmas.usezepto);
require.config({
	map: {
		'*': {
			'jquery': 'zepto'
		}
	}
})
//>>includeEnd("usezepto");