define([
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Tabs.html'
], function(Marionette, Lang, Template) {
	'use strict';

	var tabs = [
		{ route: 'dirs', lang: 'tab_dirs', isDefault: true },
		{ route: 'files', lang: 'tab_files' },
		{ route: 'modified', lang: 'tab_modified' },
		{ route: 'sizes', lang: 'tab_sizes' },
		{ route: 'ext', lang: 'tab_ext' },
		{ route: 'top', lang: 'tab_top' }
	];

	return Marionette.ItemView.extend({

		className: 'du-tabs',
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
				tabs: tabs,
				hash: model.id,
				route: this.app.getRoute(),
				settings: settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		}
	});
});
