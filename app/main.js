"use strict";
requirejs.config({
	baseUrl: './app',
	paths: {
		i18n: '../vendor/bower/requirejs-i18n/i18n',
		text: '../vendor/bower/requirejs-text/text',
		jquery: '../vendor/bower/jquery/jquery',
		lodash: '../vendor/bower/lodash/dist/lodash',
		backbone: '../vendor/bower/backbone/backbone',
		marionette: '../vendor/bower/backbone.marionette/lib/core/amd/backbone.marionette',
		'backbone.babysitter': '../vendor/bower/backbone.babysitter/lib/amd/backbone.babysitter',
		'backbone.eventbinder': '../vendor/bower/backbone.eventbinder/lib/amd/backbone.eventbinder',
		'backbone.wreqr': '../vendor/bower/backbone.wreqr/lib/amd/backbone.wreqr',
		'bootstrap.dropdown': '../vendor/bower/bootstrap/js/dropdown'
	},
	shim: {
		jquery: {
			exports: 'jQuery'
		},
		lodash: {
			exports: '_'
		},
		backbone: {
			deps: ['jquery', 'underscore'],
			exports: 'Backbone'
		},
		marionette: {
			deps: ['jquery', 'underscore', 'backbone'],
			exports: 'Marionette'
		},
		'bootstrap.dropdown': {
			deps: ['jquery']
		}
	},
	map: {
		'*': {
			underscore : 'lodash'
		}
	}
});

require([
	'marionette',
	'ReportApp'
], function(Marionette, ReportApp) {
	console.log('loaded');

	var reportsBaseURL = '/git/diskusage-data/',
		report = window.location.search.substring(1),
		suffix = [ '.txt', '' ];

	var app = new ReportApp();
	app.start({
		url: reportsBaseURL + report,
		suffix: suffix
	});

	var Router = Marionette.AppRouter.extend({
		appRoutes: {
			'': 'loadDirectory',
			':hash': 'loadDirectory',
			':hash/:tab': 'loadDirectory',
			':hash/:tab/:sort': 'loadDirectory',
			':hash/:tab/:sort/:page': 'loadDirectory'
		}
	});

	new Router({
		controller: {
			loadDirectory: function (hash, tab, sort, page) {
				if (this._hash != hash)
					app.vent.trigger('route:directory', hash || '', tab, sort, page);
			}
		}
	});

}, function() {
	console.log('err', arguments);
});
