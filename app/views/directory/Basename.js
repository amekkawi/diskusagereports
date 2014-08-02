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

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		serializeData: function() {
			var model = this.model;
			var settings = model.settings;

			return _.extend({
				app: this.app,
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
