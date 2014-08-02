define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Breadcrumb.html'
], function(_, Marionette, Lang, Template) {
	return Marionette.ItemView.extend({

		className: 'du-breadcrumb',
		template: Template,

		serializeData: function() {
			return _.extend({
				hash: this.model.id,
				settings: this.model.settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		}
	});
});
