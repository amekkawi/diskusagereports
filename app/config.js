require.config({

	deps: [
		"main"
	],

	paths: {

	},

	map: {
		"*": {
			"underscore": "lodash"
		}
	},

	packages: [
		{
			name: "backbone",
			location: "vendor/bower/backbone",
			main: "backbone.js"
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
			name: "requirejs-i18n",
			location: "vendor/bower/requirejs-i18n",
			main: "i18n.js"
		},
		{
			name: "requirejs-text",
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
