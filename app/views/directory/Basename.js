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
			var app = this.app;
			var settings = app.settings;

			return _.extend({
				app: app,
				isRoot: this.model.id === settings.get('root'),
				settings: settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		},

		onRender: function() {
			var isRoot = this.model.id === this.app.settings.get('root');
			this.$el.toggleClass('is-root', isRoot);
		}
	});
});
