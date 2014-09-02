define([
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/Tabs.html'
], function(Marionette, Lang, Template) {
	'use strict';

	var tabs = [
		{ route: 'dirs', lang: 'tab_dirs', icon: 'folder-open', isDefault: true },
		{ route: 'files', lang: 'tab_files', icon: 'file' },
		{ route: 'modified', lang: 'tab_modified', icon: 'time' },
		{ route: 'sizes', lang: 'tab_sizes', icon: 'hdd' },
		//{ route: 'ext', lang: 'tab_ext', icon: 'asterisk' },
		{ route: 'top', lang: 'tab_top', icon: 'signal' }
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
				app: this.app,
				hash: model.id,
				route: this.app.getRoute(),
				settings: settings.attributes,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		}
	});
});
