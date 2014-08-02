define([
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Tabs.html'
], function(Marionette, Lang, Template) {
	"use strict";

	return Marionette.ItemView.extend({
		className: 'du-tabs',
		template: Template,
		serializeData: function() {
			return {
				Lang: Lang
			};
		}
	});
});
