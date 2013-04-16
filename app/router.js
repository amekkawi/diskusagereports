define([
	'backbone'
], function(Backbone) {

	return Backbone.Router.extend({
		routes: {
			'(:hash(/:tab(/:page)))': 'index'
		}
	});

});