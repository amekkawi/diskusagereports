define([
	'underscore',
	'jquery',
	'marionette',
	'components/wreqr.GetLookup',
	'components/wreqr.GetDirectory',
	'models/Settings',
	'views/Loader',
	'views/Title',
	'views/Error',
	'views/AjaxError',
	'views/directory/Directory'
], function(
	_, $, Marionette,
	GetLookup, GetDirectory,
	ModelSettings,
	ViewLoader, ViewTitle, ViewError, ViewAjaxError, ViewDirectory
) {
	'use strict';

	return Marionette.Application.extend({
		constructor: function() {
			var app = this;
			Backbone.Marionette.Application.apply(app, arguments);

			this.setRoute();

			// Handlers for lookup and directory data requests.
			app.reqres.setHandler('GetLookup', _.once(GetLookup), app);
			app.reqres.setHandler('GetDirectory', GetDirectory, app);

			// Main initialization of the application.
			app.addInitializer(function(options) {
				app.addRegions({
					container: options.container
				});

				app._loadSettings(options);
			});

			// Load the directory after the settings are loaded.
			app.vent.once('settings:loaded', function() {
				app._loadDirectory();
			});
		},

		getRoute: function() {
			return this._route;
		},

		setRoute: function(route) {
			route = _.extend({}, route);

			// Validate the tab.
			if (!this.isValidTab(route.tab))
				route.tab = 'dirs';

			// Validate the sort.
			if (!_.isString(route.sort) || route.sort.match(/^[nsc][ntsm][asc][rsc][asc][esc][ntsmp]$/))
				route.sort = 'ssssss';

			// Clean the page.
			route.page = Math.max(1, parseInt(route.page, 10) || 1);

			var oldRoute = this._route;
			this._route = route;

			if (oldRoute) {
				// Trigger route event.
				this.vent.trigger('route', route);

				// Load directory if settings are loaded.
				if (this.settings)
					this._loadDirectory();
			}

			return this;
		},

		buildRouteUrl: function(route) {
			route = _.defaults({}, route, this.getRoute());

			return [
				route.hash,
				route.tab,
				route.sort,
				route.page
			].join('/');
		},

		isValidTab: function(tab) {
			return !!tab && (
				tab === 'dirs'
				|| tab === 'files'
				|| tab === 'modified'
				|| tab === 'sizes'
				|| tab === 'ext'
				|| tab === 'top'
			);
		},

		_loadDirectory: function() {
			var app = this;
			var route = app.getRoute();
			var routeHash = route && route.hash || app.settings.get('root');

			app.request('GetDirectory', routeHash)
				.done(function(dir) {
					//console.log('_loadDirectory success', routeHash, dir);
					app.container.show(
						new ViewDirectory({
							model: dir,
							app: app
						})
					);
				})
				.fail(function() {
					console.log('_loadDirectory fail', routeHash, arguments);
				});
		},

		_loadSettings: function(options) {
			var app = this;
			var suffixList = options.suffix.slice(0);
			var urlRoot = app.urlRoot = options.url.replace(/\/+$/, '');
			var attemptNum = 1;

			// Attempts to load the settings file using the current extension.
			function attempt() {
				attemptNum++;

				var suffix = suffixList.shift();

				//console.log('settings:loading', attemptNum);
				app.vent.trigger('settings:loading', { attemptNum: attemptNum, suffix: suffix, app: app });

				var xhr = $.ajax({
					dataType: 'json',
					url: urlRoot + '/settings' + suffix
				});

				if (xhr === false) {
					console.log('settings:failed', urlRoot);
					app.vent.trigger('settings:failed', { urlRoot: urlRoot, ajax: false });
					return;
				}

				xhr.done(function(resp) {
					// Attempt is successful.
					var settings = app.settings = new ModelSettings(resp);
					app.suffix = suffix;

					//console.log('settings:loaded', settings, urlRoot, suffix);
					app.vent.trigger('settings:loaded', { settings: settings, app: app, urlRoot: urlRoot, suffix: suffix });
					app.vent.trigger('settings:afterload', { urlRoot: urlRoot });
				});

				xhr.fail(function(xhr, status, error) {
					// Attempt using next extension.
					if (suffixList.length) {
						attempt();
					}

					// Fail after trying each extension.
					else {
						console.log('settings:failed', urlRoot, { xhr: xhr, status: status, error: error });
						app.vent.trigger('settings:failed', { urlRoot: urlRoot, ajax: { xhr: xhr, status: status, error: error } });
						app.vent.trigger('settings:afterload', { urlRoot: urlRoot });
					}
				});
			}

			app.vent.trigger('settings:beforeload', { urlRoot: urlRoot });

			// First attempt.
			attempt();
		}
	});
});
