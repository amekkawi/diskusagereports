define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Breadcrumb.html'
], function(_, Marionette, Lang, Template) {
	return Marionette.ItemView.extend({

		className: 'du-breadcrumb',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		serializeData: function() {
			return _.extend({
				app: this.app,
				hash: this.model.id,
				settings: this.model.settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		}
	});
});
