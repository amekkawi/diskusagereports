"use strict";

window.has = function() {
	return true;
};

requirejs.config({
	baseUrl: './app',
	paths: {
		i18n: '../vendor/bower/requirejs-i18n/i18n',
		text: '../vendor/bower/requirejs-text/text',
		tpl: 'tpl',
		jquery: '../vendor/bower/jquery/dist/jquery',
		lodash: '../vendor/bower/lodash/dist/lodash',
		backbone: '../vendor/bower/backbone/backbone',
		marionette: '../vendor/bower/backbone.marionette/lib/core/amd/backbone.marionette',
		'backbone.babysitter': '../vendor/bower/backbone.babysitter/lib/backbone.babysitter',
		'backbone.eventbinder': '../vendor/bower/backbone.eventbinder/lib/backbone.eventbinder',
		'backbone.wreqr': '../vendor/bower/backbone.wreqr/lib/backbone.wreqr',
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
	'app',
	'marionette',
	'views/Title'
], function(app, Marionette, TitleView) {
	"use strict";

	var reportsBaseURL = '/git/diskusage-data/',
		report = window.location.search.substring(1),
		suffix = [ '.txt', '' ];

	var titleView = new TitleView();
	titleView.$el.appendTo('#AppContainer > .du-title > .container');
	titleView.render();

	// Re-render the title view to display new information from the settings.
	app.vent.on('settings:loaded', function(evt) {
		titleView.model = evt.settings;
		titleView.render();
	});

	// Re-render the title view to display new information from the settings.
	app.vent.on('route', function(route) {
	});

	app.start({
		container: '#Report',
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
				app.setRoute({ hash: hash, tab: tab, sort: sort, page: page });
			}
		}
	});

	Backbone.history.start();

}/*, function() {
	console.log('err', arguments);
}*/);
