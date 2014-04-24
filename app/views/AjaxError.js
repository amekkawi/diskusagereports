define([
	'underscore',
	'views/Error',
	'text!templates/AjaxError.html'
], function(_, ViewError, Template) {
	"use strict";

	return ViewError.extend({
		template: _.template(Template)
	});
});
