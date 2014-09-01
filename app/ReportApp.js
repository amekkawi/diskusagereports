define([
	'underscore',
	'jquery',
	'marionette',
	'components/wreqr.GetFile',
	'components/wreqr.GetLookup',
	'components/wreqr.GetDirectory',
	'components/wreqr.GetCollection',
	'models/Settings',
	'views/Loader',
	'views/Title',
	'views/Error',
	'views/AjaxError',
	'views/directory/Directory'
], function(
	_, $, Marionette,
	GetFile, GetLookup, GetDirectory, GetCollection,
	ModelSettings,
	ViewLoader, ViewTitle, ViewError, ViewAjaxError, ViewDirectory
) {
	'use strict';

	var routeSortKeys =     ['dirs', 'files', 'modified', 'size', 'ext', 'top'];
	var routeSortValues =   ['nscd', 'ntsm',  'asc',      'rsc',  'esc', 'ntsmp'];
	var routeSortDefaults = ['s',    's',     's',        's',    's',   's'];

	var routeDefault = _.zipObject(routeSortKeys, routeSortDefaults);

	routeSortValues = _.map(routeSortValues, function(val) {
		return new RegExp('^[' + val + ']$', 'i');
	});

	return Marionette.Application.extend({
		constructor: function() {
			var app = this;
			Backbone.Marionette.Application.apply(app, arguments);

			this.setRoute();

			app.reqres.setHandler('GetLookup', GetFile({
				defaultFileName: 'dirmap_lookup',
				errorNotFound: 'DIRMAP_NOT_FOUND'
			}), app);

			app.reqres.setHandler('GetDirectory', GetDirectory, app);

			app.reqres.setHandler('GetSubDirs', GetCollection({
				attribute: 'dirs',
				mapPrefix: 'subdirsmap',
				segmentPrefix: 'subdirs',
				count: 'directSubDirCount',
				notFound: 'SUBDIRS_NOT_FOUND',
				pagesPerSegment: 'pagesPerSubdirs',
				sorting: {
					n: {
						attributes: ['name'],
						segmentIndex: 0
					},
					s: {
						attributes: ['size', 'name'],
						defaultReverse: true,
						segmentIndex: 1
					},
					c: {
						attributes: ['fileCount', 'name'],
						defaultReverse: true,
						segmentIndex: 2
					},
					d: {
						attributes: ['dirCount', 'name'],
						defaultReverse: true,
						segmentIndex: 3
					}
				}
			}), app);

			app.reqres.setHandler('GetFiles', GetCollection({
				attribute: 'files',
				mapPrefix: 'filesmap',
				segmentPrefix: 'files',
				count: 'directFileCount',
				notFound: 'FILES_NOT_FOUND',
				pagesPerSegment: 'pagesPerFiles',
				sorting: {
					n: {
						attributes: ['name'],
						segmentIndex: 0
					},
					s: {
						attributes: ['size', 'name'],
						defaultReverse: true,
						segmentIndex: 1
					},
					m: {
						attributes: ['date', 'time', 'name'],
						segmentIndex: 2
					}
				}
			}), app);

			app.reqres.setHandler('GetGroupModified', GetCollection({
				attribute: 'modifiedDates',
				mapPrefix: 'modifieddates',
				notFound: 'MODIFIED_NOT_FOUND',
				sorting: {
					a: {
						attributes: ['index']
					},
					s: {
						attributes: ['size', 'index'],
						defaultReverse: true
					},
					c: {
						attributes: ['files', 'index'],
						defaultReverse: true
					}
				}
			}), app);

			// General file fetching.
			app.reqres.setHandler('GetFile', GetFile(), app);

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
			var oldRoute = this._route;
			route = _.extend({}, route);

			// Validate the tab.
			if (!this.isValidTab(route.tab))
				route.tab = 'dirs';

			if (_.isString(route.sort)) {

				// Discard the sort if it is not the correct length.
				if (route.sort.length != routeSortKeys.length) {
					delete route.sort;
				}

				// Split and validate each sort key.
				else {
					route.sort = _.zipObject(routeSortKeys, _.map(route.sort.split(''), function(val, i) {
						return val.match(routeSortValues[i]) ? val : routeSortDefaults[i];
					}));
				}
			}

			// Set defaults for unset sort keys.
			route.sort = _.defaults(
				route.sort || {},
				oldRoute ? oldRoute.sort : routeDefault
			);

			// Clean the page num.
			route.page = Math.max(1, parseInt(route.page, 10) || 1);

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
			var baseRoute = this.getRoute() || {};
			route = _.defaults({}, route, baseRoute);

			var sort = route.sort;
			if (!_.isString(sort)) {
				sort = _.defaults(sort, baseRoute.sort);
				sort = _.reduce(routeSortKeys, function(ret, key) { return ret + sort[key]; }, '');
			}

			return [
				route.hash || this.settings && this.settings.get('root'),
				route.tab,
				sort,
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
