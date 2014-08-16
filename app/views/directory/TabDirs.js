define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabDirs.html'
], function(_, Marionette, Lang, Template) {
	'use strict';

	function formatBytes(bytes) {
		if (bytes >= 1000 * 1024 * 1024 * 1024 * 1024) {
			return (Math.floor(bytes / 1024 / 1024 / 1024 / 1024 / 1024 * 10) / 10) + 'P';
		}
		else if (bytes >= 1000 * 1024 * 1024 * 1024) {
			return (Math.floor(bytes / 1024 / 1024 / 1024 / 1024 * 10) / 10) + 'T';
		}
		else if (bytes >= 1000 * 1024 * 1024) {
			return (Math.floor(bytes / 1024 / 1024 / 1024 * 10) / 10) + 'G';
		}
		else if (bytes >= 1000 * 1024) {
			return (Math.floor(bytes / 1024 / 1024 * 10) / 10) + 'M';
		}
		else if (bytes >= 1024) {
			return (Math.floor(bytes / 1024 * 10) / 10) + 'K';
		}
		else {
			return '' + bytes
		}
	}

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
				formatBytes: formatBytes,
				Lang: Lang
			}, Marionette.ItemView.prototype.serializeData.apply(this, arguments));
		}
	});
});
