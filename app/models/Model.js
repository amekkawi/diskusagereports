define([
	'underscore',
	'backbone'
], function(_, Backbone) {
	"use strict";

	return Backbone.Model.extend({
		initialize: function(attributes, options) {
			Backbone.Model.prototype.initialize.apply(this, arguments);
			_.extend(this, _.pick(options, [ 'urlRoot', 'id', 'url', 'settings', 'suffix' ]));
		}
	});
});
