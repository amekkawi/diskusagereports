"use strict";
define([
	'underscore',
	'models/Model'
], function(_, Model) {
	return Model.extend({
		url: function() {
			return Model.prototype.url.apply(this, arguments) + this.suffix;
		}
	});
});
