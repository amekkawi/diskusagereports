define([
	'backbone'
], function(Backbone) {

	return Backbone.Router.extend({
		routes: {
			'': 'index',
			':hash(/:tab)(/:page)': 'index'
		}
	});
	
});