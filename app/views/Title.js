define([
	'underscore',
	'marionette',
	'i18n!nls/report'
], function(_, Marionette, Lang) {
	'use strict';

	return Marionette.ItemView.extend({
		tagName: 'h1',

		template: function(args) {
			return args.name
				? _.template(args.Lang.title_long, { name: args.name })
				: args.Lang.title;
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
