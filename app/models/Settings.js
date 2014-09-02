define([
	'underscore',
	'models/Model'
], function(_, Model) {
	"use strict";

	return Model.extend(/** @lends Settings.prototype */{

		url: function() {
			return Model.prototype.url.apply(this, arguments) + this.suffix;
		}

		/**
		 * Backbone model for settings data.
		 *
		 * @extends {Model}
		 * @constructs
		 * @name Settings
		 */
	});
});
