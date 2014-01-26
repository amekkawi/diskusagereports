define([
	'underscore',
	'views/Error',
	'text!templates/AjaxError.html'
], function(_, ViewError, Template) {
	return ViewError.extend({
		template: _.template(Template)
	});
});
