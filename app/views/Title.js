define([
	'underscore',
	'marionette',
	'text!templates/Title.html',
	'i18n!nls/report'
], function(_, Marionette, Template, Lang) {
	"use strict";

	return Marionette.ItemView.extend({
		tagName: 'h1',
		template: _.template(Template),
		modelEvents: {
			'change:name': 'render'
		},
		serializeData: function() {
			var settings = this.model;
			return {
				name: settings && settings.get('name'),
				Lang: Lang
			};
		}
	});
});
