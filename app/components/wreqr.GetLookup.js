define([
	'jquery'
], function($) {
	return function() {
		var app = this;
		var deferred = $.Deferred();
		var xhr;

		deferred.abort = function() {
			xhr && xhr.abort();
		};

		if (app.dirmapLookup) {
			deferred.resolveWith(app, [ app.dirmapLookup ]);
		}
		else if (app.settings) {
			xhr = $.ajax({
				dataType: 'json',
				url: app.urlRoot + '/dirmap_lookup' + app.suffix
			})
			.done(function(resp) {
				app.dirmapLookup = resp;
				deferred.resolveWith(app, [ app.dirmapLookup ]);
			})
			.fail(function(xhr, status, error) {
				deferred.rejectWith(app, [ 'DIRMAP_NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
			});
		}
		else {
			deferred.rejectWith(app, [ 'SETTINGS_NOT_LOADED' ]);
		}

		return deferred;
	};
});
