define([
	'jquery',
	'models/Dir'
], function($, ModelDir) {

	var responseCache = {};
	var responseCacheKeys = [];

	function pushToCache(hash, resp) {
		responseCache[hash] = resp;
		responseCacheKeys.push(hash);

		// Trim if over cache limit.
		if (responseCacheKeys.length > 10) {
			var firstKey = responseCacheKeys.shift();
			delete responseCache[firstKey];
		}
	}

	return function(hash) {
		var app = this;
		var settings = app.settings;
		var deferred = $.Deferred();
		var xhr;

		deferred.abort = function() {
			xhr && xhr.abort();
		};

		function resolveWith(dir) {
			deferred.resolveWith(app, [ new ModelDir(dir, { id: hash, settings: settings, parse: true }) ]);
		}

		if (!settings) {
			deferred.rejectWith(app, [ 'SETTINGS_NOT_LOADED' ]);
		}
		else if (responseCache[hash]) {
			resolveWith(responseCache[hash]);
		}
		else if (!settings.has('version') || settings.get('version') === '1.0') {
			xhr = $.ajax({
				dataType: 'json',
				url: app.urlRoot + '/' + hash + app.suffix
			})
				.done(function(resp) {
					pushToCache(hash, resp);
					resolveWith(resp);
				})
				.fail(function(xhr, status, error) {
					deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
				});
		}
		else {
			app.request('GetLookup')
				.done(function(dirmapLookup) {
					var found = _.find(dirmapLookup, function(item) {
						return hash.substr(0, item[0].length) >= item[0]
							&& hash.substr(0, item[1].length) <= item[1];
					});

					if (found) {
						xhr = $.ajax({
							dataType: 'json',
							url: app.urlRoot + '/dirmap_' + found[2] + app.suffix
						})
							.done(function(resp) {
								var dir = resp[hash];
								if (dir) {
									pushToCache(hash, dir);
									resolveWith(dir);
									app.vent.trigger('dirmapLookup:loaded', dirmapLookup);
								}
								else {
									deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'NOT_IN_MAP' ]);
								}
							})
							.fail(function(xhr, status, error) {
								deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
							});
					}
					else {
						deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'NOT_IN_LOOKUP' ]);
					}
				})
				.fail(function() {
					deferred.rejectWith(app, [ 'LOOKUP_NOT_FOUND' ]);
				});
		}

		return deferred;
	};
});
