define([
	'underscore',
	'jquery',
	'marionette',
	'components/util.memoizepromise',
	'components/wreqr.GetFile',
	'components/wreqr.GetMapEntry',
	'components/wreqr.GetList',
	'models/Settings',
	'models/Dir',
	'views/Loader',
	'views/Title',
	'views/Error',
	'views/AjaxError',
	'views/directory/Directory'
], function(
	_, $, Marionette, MemoizePromise,
	GetFile, GetMapEntry, GetList,
	ModelSettings, ModelDir,
	ViewLoader, ViewTitle, ViewError, ViewAjaxError, ViewDirectory
) {
	'use strict';

	var routeSortKeys =     ['dirs', 'files', 'modified', 'sizes', 'ext', 'top'];
	var routeSortValues =   ['nscd', 'ntsm',  'asc',      'rsc',   'esc', 'nsp'];
	var routeSortDefaults = ['s',    's',     'a',        'r',     's',   's'];

	var routeDefault = _.zipObject(routeSortKeys, routeSortDefaults);

	routeSortValues = _.map(routeSortValues, function(val) {
		return new RegExp('^[' + val + ']$', 'i');
	});

	return Marionette.Application.extend({
		constructor: function() {
			var app = this;
			Backbone.Marionette.Application.apply(app, arguments);

			this.setRoute();

			// General file fetching.
			app.reqres.setHandler('GetFile', GetFile.Build(), app);

			// =================================
			// Directory requests
			// =================================

			app.reqres.setHandler('GetDirLookup', GetFile.Build({
				fileName: 'dirmap_lookup',
				errorNotFound: 'LOOKUP_NOT_FOUND'
			}).memoize({
				key: 'dirmap_lookup'
			}), app);

			// TODO: 1.0 reports need to use GetFile (url: app.urlRoot + '/' + hash + app.suffix)
			app.reqres.setHandler('GetDirectory', GetMapEntry.Build({
				mapPrefix: 'dirmap_',
				lookupRequest: 'GetDirLookup',
				parse: function(entry, key) {
					return new ModelDir(entry, { id: key, settings: app.settings, parse: true });
				}
			}).memoize({
				limit: 10
			}), app);

			// =================================
			// Subdirectory list requests
			// =================================

			app.reqres.setHandler('GetSubDirLookup', GetFile.Build({
				fileName: 'subdirmap_lookup',
				errorNotFound: 'LOOKUP_NOT_FOUND'
			}).memoize({
				key: 'subdirmap_lookup'
			}), app);

			app.reqres.setHandler('GetSubDirsMap', GetMapEntry.Build({
				mapPrefix: 'subdirsmap_',
				lookupRequest: 'GetSubDirLookup',
				parse: function(entry) {
					return ModelDir.parse({ dirs: entry }, { settings: app.settings });
				}
			}), app);

			app.reqres.setHandler('GetSubDirs', GetList.Build({
				attribute: 'dirs',
				mapPrefix: 'subdirsmap',
				segmentPrefix: 'subdirs',
				lookupRequest: 'GetSubDirLookup',
				sortedMap: 'GetSubDirsMap',
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

			// =================================
			// File list requests
			// =================================

			app.reqres.setHandler('GetFilesMap', GetMapEntry.Build({
				mapPrefix: 'filesmap_',
				parse: function(entry) {
					return ModelDir.parse({ files: entry }, { settings: app.settings });
				}
			}).memoize({
				limit: 5,
				resolver: function(key) {
					return _.isObject(key) ? key.id : key;
				}
			}), app);

			app.reqres.setHandler('GetFiles', GetList.Build({
				attribute: 'files',
				mapRequest: 'GetFilesMap',
				segmentPrefix: 'files',
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

			// =================================
			// Modified dates list requests
			// =================================

			app.reqres.setHandler('GetGroupModifiedMap', GetMapEntry.Build({
				mapPrefix: 'modifieddates_',
				parse: function(entry) {
					return ModelDir.parse({ modifiedDates: entry }, { settings: app.settings });
				}
			}).memoize({
				limit: 5,
				resolver: function(key) {
					return _.isObject(key) ? key.id : key;
				}
			}), app);

			app.reqres.setHandler('GetGroupModified', GetList.Build({
				attribute: 'modifiedDates',
				mapRequest: 'GetGroupModifiedMap',
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

			// =================================
			// File sizes list requests
			// =================================

			app.reqres.setHandler('GetGroupSizesMap', GetMapEntry.Build({
				mapPrefix: 'filesizes_',
				parse: function(entry) {
					return ModelDir.parse({ fileSizes: entry }, { settings: app.settings });
				}
			}).memoize({
				limit: 5,
				resolver: function(key) {
					return _.isObject(key) ? key.id : key;
				}
			}), app);

			app.reqres.setHandler('GetGroupSizes', GetList.Build({
				attribute: 'fileSizes',
				mapRequest: 'GetGroupSizesMap',
				sorting: {
					r: {
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

			// =================================
			// Top files list requests
			// =================================

			app.reqres.setHandler('GetGroupTopMap', GetMapEntry.Build({
				mapPrefix: 'topmap_',
				parse: function(entry) {
					return ModelDir.parse({ top: entry }, { settings: app.settings });
				}
			}).memoize({
				limit: 5,
				resolver: function(key) {
					return _.isObject(key) ? key.id : key;
				}
			}), app);

			app.reqres.setHandler('GetGroupTop', GetList.Build({
				attribute: 'top',
				mapRequest: 'GetGroupTopMap',
				sorting: {
					n: {
						attributes: ['name']
					},
					s: {
						attributes: ['size', 'name'],
						defaultReverse: true
					},
					p: {
						attributes: ['path', 'name']
					}
				}
			}), app);

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
