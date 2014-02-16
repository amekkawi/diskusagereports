"use strict";
define([
	'underscore',
	'marionette',
	'text!templates/Title.html',
	'i18n!nls/report'
], function(_, Marionette, Template, Lang) {
	return Marionette.ItemView.extend({
		tagName: 'h1',
		template: _.template(Template),
		modelEvents: {
			'change:name': 'render'
		},
		serializeData: function() {
			return {
				name: this.model.get('name'),
				Lang: Lang
			};
		}
	});
});
