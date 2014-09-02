define([
	'underscore',
	'backbone'
], function(_, Backbone) {
	'use strict';

	return Backbone.Model.extend(/** @lends Model.prototype */{

		/**
		 * Base model.
		 *
		 * @constructs
		 * @name Model
		 * @extends Backbone.Model
		 *
		 * @param attributes
		 * @param {Object} [options]
		 * @param {String} [options.urlRoot]
		 * @param {String} [options.id]
		 * @param {String} [options.url]
		 * @param {Settings} [options.settings]
		 * @param {String} [options.suffix]
		 */
		constructor: function(attributes, options) {
			if (options)
				_.extend(this, _.pick(options, [ 'urlRoot', 'id', 'url', 'settings', 'suffix' ]));

			Backbone.Model.apply(this, arguments);
		}
	});
});
