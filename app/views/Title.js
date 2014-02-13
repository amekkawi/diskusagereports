"use strict";
define([
	'underscore',
	'marionette',
	'text!templates/Title.html'
], function(_, Marionette, Template) {
	return Marionette.ItemView.extend({
		tagName: 'h1',
		template: _.template(Template),
		modelEvents: {
			'change:name': 'render'
		},
		serializeData: function() {
			return {
				reportName: this.model.get('name')
			};
		}
	});
});
