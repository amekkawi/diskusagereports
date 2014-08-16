define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabDirs.html',
	'tpl!views/directory/ProgressBar.html',
	'tpl!views/directory/SortLink.html',
	'tpl!views/directory/SortDropdown.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-dirs',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'dirs', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var model = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();
			app.request('GetSubDirs', model, route.sort.dirs, route.page)
				.done(function(subDirs, isPage) {
					_this.dirs = subDirs;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function() {
					// TODO: Show error message.
					console.log('GetFile subdirsmap fail', arguments);
				});
		},

		serializeData: function() {
			var app = this.app;
			var model = this.model;
			var settings = model.settings;

			return _.defaults({
				hash: model.id,
				dirs: this.dirs,
				app: app,
				route: app.getRoute(),
				settings: settings.attributes,
				Lang: Lang,
				progressBar: TemplateProgressBar,
				sortLink: TemplateSortLink,
				sortDropdown: TemplateSortDropdown
			}, Marionette.ItemView.prototype.serializeData.apply(this, arguments));
		}
	});
});
