define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Basename.html',

	'bootstrap.dropdown'
], function(_, Marionette, Lang, Template) {
	return Marionette.ItemView.extend({

		className: 'du-basename',
		template: Template,

		serializeData: function() {
			var model = this.model;
			var settings = model.settings;

			return _.extend({
				isRoot: model.id === settings.get('root'),
				settings: settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		},

		onRender: function() {
			var model = this.model;
			var isRoot = model.id === model.settings.get('root');

			this.$el.toggleClass('is-root', isRoot);
		}
	});
});
