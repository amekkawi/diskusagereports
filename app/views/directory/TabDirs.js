define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabDirs.html',
	'tpl!views/directory/ProgressBar.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-contents',
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
					console.log('GetSubDirs', model.id);

					_this.dirs = subDirs;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function() {
					console.log('GetFile subdirsmap fail', arguments);
				});
		},

		serializeData: function() {
			console.log('serialize', this.model.id);

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
				progressBar: TemplateProgressBar
			}, Marionette.ItemView.prototype.serializeData.apply(this, arguments));
		}
	});
});
