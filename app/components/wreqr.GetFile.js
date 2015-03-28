define([
	'jquery'
], function($) {

	// Global cache of in-progress requests.
	var activePromiseStore = {};

	GetFile.Build = Build;
	return GetFile;

	/**
	 * Build a GetFile WREQR request with preset options.
	 *
	 * @param {Object}  [options]
	 * @param {Object}  [options.fileName] The file name to always use, ignoring the passed file name.
	 * @param {Object}  [options.defaultFileName] The file name to use when a file name was not passed.
	 * @param {Boolean} [options.errorNotFound] The error code to return instead of "NOT_FOUND".
	 * @returns {GetFileRequest}
	 */
	function Build(options) {
		options = options || {};
		return GetFileRequest;

		/**
		 * GetFile WREQR request with preset options.
		 *
		 * @param fileName
		 * @returns {Object}
		 */
		function GetFileRequest(fileName) {
			console.log('GetFileRequest', options.fileName || fileName || options.defaultFileName);
			return GetFile(this, options.fileName || fileName || options.defaultFileName, options);
		}
	}

	/**
	 * Get a report file.
	 *
	 * @param {Object}        app The Marionette.Application.
	 * @param {String|Object} fileName
	 * @param {Object}        [options]
	 * @param {Boolean}       [options.errorNotFound] The error code to return instead of "NOT_FOUND".
	 * @returns {Object} jQuery.Deferred
	 */
	function GetFile(app, fileName, options) {
		var existingPromise = activePromiseStore[fileName];
		if (existingPromise) {
			// TODO: Destroy existing promise if too old?

			// Increment the number of promises listening to this promise.
			existingPromise._abortCount++;
			return wrapPromise(existingPromise);
		}

		var promise = $.Deferred();
		var xhr;

		// Add a counter to determine how many abort calls must be made before the XHR is actually aborted.
		promise._abortCount = 1;

		// Add XHR-like abort method.
		promise.abort = function() {
			console.log('abort', fileName, promise._abortCount - 1 === 0);
			if (--promise._abortCount <= 0)
				xhr && xhr.abort();
		};

		// Cache the new promise.
		activePromiseStore[fileName] = promise;

		if (app.settings) {
			xhr = $.ajax({
				dataType: 'json',
				url: app.urlRoot + '/' + fileName + app.suffix
			})
				.done(function(resp) {
					delete activePromiseStore[fileName];
					promise.resolveWith(app, [ resp ]);
				})
				.fail(function(xhr, status, error) {
					delete activePromiseStore[fileName];
					if (status === 'abort') {
						promise.rejectWith(app, [ 'ABORT' ]);
					}
					else {
						promise.rejectWith(app, [options.errorNotFound || 'NOT_FOUND', 'FETCH_FAIL', xhr, status, error]);
					}
				});
		}
		else {
			promise.rejectWith(app, [ 'SETTINGS_NOT_LOADED' ]);
		}

		return wrapPromise(promise);
	}

	// Wrap the promise but keep the abort handler.
	function wrapPromise(promise) {
		var wrapped = promise.promise();
		wrapped.abort = promise.abort;
		return wrapped;
	}
});
