requirejs.config({
	paths: {
		i18n: '../vendor/bower/requirejs-i18n/i18n',
		text: '../vendor/bower/requirejs-text/text',
		jquery: '../vendor/bower/jquery/jquery',
		lodash: '../vendor/bower/lodash/dist/lodash',
		backbone: '../vendor/bower/backbone/backbone',
		marionette: '../vendor/bower/backbone.marionette/lib/backbone.marionette',
		bootstrap: '../vendor/bower/bootstrap/js'
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
		boostrap: {
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
