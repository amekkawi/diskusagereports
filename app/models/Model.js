define([
	'underscore',
	'backbone'
], function(_, Backbone) {
	'use strict';

	return Backbone.Model.extend({
		constructor: function(attributes, options) {
			if (options)
				_.extend(this, _.pick(options, [ 'urlRoot', 'id', 'url', 'settings', 'suffix' ]));

			Backbone.Model.apply(this, arguments);
		}
	});
});
